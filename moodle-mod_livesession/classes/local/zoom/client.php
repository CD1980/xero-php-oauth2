<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_livesession\local\zoom;

use cache;
use curl;

/**
 * Thin client for the Zoom REST API, authenticated with a server-to-server OAuth app.
 *
 * Meeting lifecycle (create, update, delete) and post-meeting participant reports go
 * through here. The in-page meeting client is authenticated separately - see
 * {@see signature} - because the Meeting SDK uses its own credential pair.
 *
 * @package    mod_livesession
 * @copyright  2026 Aspire Education and Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class client {
    /** @var string Base URL of the Zoom REST API. */
    const API_BASE = 'https://api.zoom.us/v2';

    /** @var string Token endpoint for the account_credentials grant. */
    const TOKEN_URL = 'https://zoom.us/oauth/token';

    /** @var int Seconds to wait on a Zoom call before giving up. */
    const TIMEOUT = 30;

    /** @var string Zoom account id. */
    protected $accountid;

    /** @var string Server-to-server OAuth client id. */
    protected $clientid;

    /** @var string Server-to-server OAuth client secret. */
    protected $clientsecret;

    /**
     * Construct a client for one set of server-to-server OAuth credentials.
     *
     * @param string $accountid
     * @param string $clientid
     * @param string $clientsecret
     */
    public function __construct(string $accountid, string $clientid, string $clientsecret) {
        $this->accountid = $accountid;
        $this->clientid = $clientid;
        $this->clientsecret = $clientsecret;
    }

    /**
     * Build a client from the site configuration.
     *
     * @return self
     * @throws zoom_exception if the plugin has not been configured yet
     */
    public static function instance(): self {
        if (!self::is_configured()) {
            throw new zoom_exception(get_string('error:notconfigured', 'mod_livesession'));
        }
        return new self(
            (string) get_config('mod_livesession', 'accountid'),
            (string) get_config('mod_livesession', 'clientid'),
            (string) get_config('mod_livesession', 'clientsecret')
        );
    }

    /**
     * Whether the server-to-server credentials have all been entered.
     *
     * @return bool
     */
    public static function is_configured(): bool {
        foreach (['accountid', 'clientid', 'clientsecret'] as $key) {
            if (trim((string) get_config('mod_livesession', $key)) === '') {
                return false;
            }
        }
        return true;
    }

    /**
     * Build a curl instance, making sure the class is actually loaded first.
     *
     * The curl class lives in lib/filelib.php and is not autoloadable. A web request
     * usually pulls filelib in along the way, but a cron run does not, so the
     * scheduled tasks would otherwise die on "Class curl not found".
     *
     * @return \curl
     */
    protected static function make_curl(): \curl {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        return new \curl();
    }

    /**
     * Fetch a bearer token, reusing the cached one while it is still valid.
     *
     * The cache key includes the client id so that rotating the credentials in site
     * admin immediately invalidates the old token rather than serving it until TTL.
     *
     * @param bool $forcerefresh skip the cache, e.g. after a 401
     * @return string
     * @throws zoom_exception
     */
    protected function get_access_token(bool $forcerefresh = false): string {
        $cache = cache::make('mod_livesession', 'zoomtoken');
        $key = sha1($this->accountid . '|' . $this->clientid);

        if (!$forcerefresh) {
            $cached = $cache->get($key);
            if (is_array($cached) && !empty($cached['token']) && $cached['expires'] > time() + 60) {
                return $cached['token'];
            }
        }

        $curl = static::make_curl();
        $curl->setHeader([
            'Authorization: Basic ' . base64_encode($this->clientid . ':' . $this->clientsecret),
            'Content-Type: application/x-www-form-urlencoded',
        ]);
        // The body must be a pre-encoded string. Handing curl::post() an array makes
        // libcurl build a multipart/form-data body and append its boundary to the
        // Content-Type header above, and Zoom answers "Bad Request" to that.
        $response = $curl->post(static::TOKEN_URL, self::build_token_body($this->accountid), [
            'CURLOPT_TIMEOUT' => self::TIMEOUT,
            'CURLOPT_CONNECTTIMEOUT' => 10,
        ]);

        $info = $curl->get_info();
        $status = (int) ($info['http_code'] ?? 0);
        if ($curl->get_errno() || (!$status && !empty($curl->error))) {
            throw new zoom_exception(
                get_string('error:tokenunreachable', 'mod_livesession', s((string) $curl->error)),
                0
            );
        }

        $decoded = json_decode($response, true);
        if ($status !== 200 || !is_array($decoded) || empty($decoded['access_token'])) {
            $parts = [];
            if (is_array($decoded)) {
                foreach (['reason', 'error', 'errorMessage', 'message'] as $key) {
                    if (!empty($decoded[$key]) && is_string($decoded[$key])) {
                        $parts[] = $decoded[$key];
                    }
                }
            }
            $parts[] = 'HTTP ' . $status;
            throw new zoom_exception(
                get_string('error:tokenrejected', 'mod_livesession', s(implode(' / ', array_unique($parts)))),
                $status
            );
        }

        $expiresin = (int) ($decoded['expires_in'] ?? 3600);
        $cache->set($key, [
            'token'   => $decoded['access_token'],
            'expires' => time() + $expiresin,
        ]);

        return $decoded['access_token'];
    }

    /**
     * Build the form body for the account_credentials token request.
     *
     * @param string $accountid
     * @return string urlencoded body
     */
    public static function build_token_body(string $accountid): string {
        return http_build_query([
            'grant_type' => 'account_credentials',
            'account_id' => $accountid,
        ], '', '&');
    }

    /**
     * Perform an authenticated API call.
     *
     * @param string $method GET, POST, PATCH, PUT or DELETE
     * @param string $path path below the API base, starting with a slash
     * @param array|null $payload request body, JSON encoded when present
     * @param array $query query string parameters
     * @param bool $retried internal - set when this is the post-401 retry
     * @return array decoded response body; an empty array for 204 responses
     * @throws zoom_exception
     */
    public function request(
        string $method,
        string $path,
        ?array $payload = null,
        array $query = [],
        bool $retried = false
    ): array {

        $method = strtoupper($method);
        $url = static::API_BASE . $path;
        if ($query) {
            $url .= '?' . http_build_query($query, '', '&');
        }

        $curl = static::make_curl();
        $curl->setHeader([
            'Authorization: Bearer ' . $this->get_access_token($retried),
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
        $options = [
            'CURLOPT_TIMEOUT' => self::TIMEOUT,
            'CURLOPT_CONNECTTIMEOUT' => 10,
            'CURLOPT_CUSTOMREQUEST' => $method,
        ];
        $body = $payload === null ? '' : json_encode($payload);

        switch ($method) {
            case 'GET':
                $response = $curl->get($url, [], $options);
                break;
            case 'DELETE':
                $response = $curl->delete($url, [], $options);
                break;
            default:
                $response = $curl->post($url, $body, $options);
                break;
        }

        $info = $curl->get_info();
        $status = (int) ($info['http_code'] ?? 0);

        if ($curl->get_errno() || (!$status && !empty($curl->error))) {
            throw new zoom_exception(
                get_string('error:apiunreachable', 'mod_livesession', s((string) $curl->error)),
                0
            );
        }

        // A cached token can still be revoked server side; take exactly one more run at it.
        if ($status === 401 && !$retried) {
            return $this->request($method, $path, $payload, $query, true);
        }

        if ($status === 204 || trim((string) $response) === '') {
            if ($status >= 400) {
                throw new zoom_exception(get_string('error:apistatus', 'mod_livesession', $status), $status);
            }
            return [];
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new zoom_exception(get_string('error:apimalformed', 'mod_livesession'), $status);
        }

        if ($status >= 400) {
            $detail = (string) ($decoded['message'] ?? get_string('error:apistatus', 'mod_livesession', $status));
            throw new zoom_exception(s($detail), $status, (int) ($decoded['code'] ?? 0));
        }

        return $decoded;
    }

    /**
     * Create a scheduled meeting owned by the given Zoom user.
     *
     * @param string $hostid Zoom user id, email address, or the literal 'me'
     * @param array $payload Zoom meeting object
     * @return array the created meeting
     */
    public function create_meeting(string $hostid, array $payload): array {
        return $this->request('POST', '/users/' . rawurlencode($hostid) . '/meetings', $payload);
    }

    /**
     * Update an existing meeting in place.
     *
     * @param string $meetingid
     * @param array $payload partial Zoom meeting object
     * @return void
     */
    public function update_meeting(string $meetingid, array $payload): void {
        $this->request('PATCH', '/meetings/' . rawurlencode($meetingid), $payload);
    }

    /**
     * Fetch a meeting.
     *
     * @param string $meetingid
     * @return array
     */
    public function get_meeting(string $meetingid): array {
        return $this->request('GET', '/meetings/' . rawurlencode($meetingid));
    }

    /**
     * Delete a meeting. A meeting Zoom has already forgotten is treated as success.
     *
     * @param string $meetingid
     * @return void
     */
    public function delete_meeting(string $meetingid): void {
        try {
            $this->request('DELETE', '/meetings/' . rawurlencode($meetingid));
        } catch (zoom_exception $e) {
            if ($e->httpstatus !== 404) {
                throw $e;
            }
        }
    }

    /**
     * Retrieve the participant report for a finished meeting instance.
     *
     * Meeting UUIDs that begin with a slash or contain a double slash must be encoded
     * twice before they go in the path - this is a documented Zoom quirk, not ours.
     *
     * @param string $uuid meeting instance UUID
     * @return array list of participant records, all pages concatenated
     */
    public function get_past_participants(string $uuid): array {
        $encoded = rawurlencode($uuid);
        if (strpos($uuid, '/') === 0 || strpos($uuid, '//') !== false) {
            $encoded = rawurlencode($encoded);
        }

        $participants = [];
        $token = '';
        $guard = 0;
        do {
            $query = ['page_size' => 300];
            if ($token !== '') {
                $query['next_page_token'] = $token;
            }
            $page = $this->request('GET', '/report/meetings/' . $encoded . '/participants', null, $query);
            foreach (($page['participants'] ?? []) as $participant) {
                $participants[] = $participant;
            }
            $token = (string) ($page['next_page_token'] ?? '');
        } while ($token !== '' && ++$guard < 50);

        return $participants;
    }

    /**
     * List the meeting instances Zoom has retained for a recurring or repeated meeting id.
     *
     * @param string $meetingid
     * @return array list of ['uuid' => ..., 'start_time' => ...]
     */
    public function get_past_instances(string $meetingid): array {
        $response = $this->request('GET', '/past_meetings/' . rawurlencode($meetingid) . '/instances');
        return $response['meetings'] ?? [];
    }
}
