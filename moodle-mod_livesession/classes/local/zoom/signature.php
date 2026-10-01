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

/**
 * Mints the short-lived JWT the Zoom Meeting SDK needs to let a browser into a meeting.
 *
 * This is a different credential pair from the REST client: the REST API uses the
 * server-to-server OAuth app, while the in-page client uses a Meeting SDK app. The
 * secret never leaves the server - only the signed token is handed to the browser.
 *
 * @package    mod_livesession
 * @copyright  2026 Aspire Education and Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class signature {
    /** @var int Role value for an ordinary attendee. */
    const ROLE_ATTENDEE = 0;

    /** @var int Role value for the meeting host. */
    const ROLE_HOST = 1;

    /** @var int How long a minted signature stays usable, in seconds. */
    const LIFETIME = 7200;

    /**
     * Whether the Meeting SDK credentials have been entered.
     *
     * @return bool
     */
    public static function is_configured(): bool {
        return trim((string) get_config('mod_livesession', 'sdkclientid')) !== ''
            && trim((string) get_config('mod_livesession', 'sdkclientsecret')) !== '';
    }

    /**
     * Sign a Meeting SDK JWT for one user joining one meeting.
     *
     * @param string $meetingnumber the numeric Zoom meeting id
     * @param int $role self::ROLE_ATTENDEE or self::ROLE_HOST
     * @return string the compact JWT
     * @throws zoom_exception if the SDK credentials are missing
     */
    public static function create(string $meetingnumber, int $role): string {
        if (!self::is_configured()) {
            throw new zoom_exception(get_string('error:sdknotconfigured', 'mod_livesession'));
        }

        $key = (string) get_config('mod_livesession', 'sdkclientid');
        $secret = (string) get_config('mod_livesession', 'sdkclientsecret');

        // Backdate iat slightly so a little clock skew between us and Zoom is harmless.
        $iat = time() - 30;
        $exp = $iat + self::LIFETIME;

        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $payload = [
            'appKey'   => $key,
            'sdkKey'   => $key,
            'mn'       => $meetingnumber,
            'role'     => $role,
            'iat'      => $iat,
            'exp'      => $exp,
            'tokenExp' => $exp,
        ];

        $signinginput = self::base64url(json_encode($header)) . '.' . self::base64url(json_encode($payload));
        $signature = hash_hmac('sha256', $signinginput, $secret, true);

        return $signinginput . '.' . self::base64url($signature);
    }

    /**
     * URL-safe base64 with the padding stripped, as JWT requires.
     *
     * @param string $data
     * @return string
     */
    protected static function base64url(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
