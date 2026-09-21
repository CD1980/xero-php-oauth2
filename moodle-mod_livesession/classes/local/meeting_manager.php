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

namespace mod_livesession\local;

use mod_livesession\local\zoom\client;
use mod_livesession\local\zoom\zoom_exception;
use stdClass;

/**
 * Keeps the Zoom meeting behind an activity instance in step with the Moodle record.
 *
 * @package    mod_livesession
 * @copyright  2026 Aspire Education and Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class meeting_manager {
    /** @var string Zoom meeting type: scheduled. */
    const TYPE_SCHEDULED = 2;

    /**
     * Create the Zoom meeting for a session, or update it if one already exists.
     *
     * Failure here is recorded on the instance rather than thrown, so that saving the
     * activity form never fails outright because Zoom was briefly unavailable - the
     * teacher gets a warning on the view page and can retry.
     *
     * @param stdClass $livesession row from {livesession}, updated in place
     * @return stdClass the same record, with sync fields refreshed
     */
    public static function sync(stdClass $livesession): stdClass {
        global $DB;

        try {
            $zoom = client::instance();
            $payload = self::build_payload($livesession);

            if (!empty($livesession->meetingid)) {
                $zoom->update_meeting($livesession->meetingid, $payload);
                $meeting = $zoom->get_meeting($livesession->meetingid);
            } else {
                $host = trim((string) $livesession->zoomhostid);
                if ($host === '') {
                    $host = (string) get_config('mod_livesession', 'defaulthost');
                }
                if ($host === '') {
                    $host = 'me';
                }
                $meeting = $zoom->create_meeting($host, $payload);
            }

            $livesession->meetingid = (string) ($meeting['id'] ?? $livesession->meetingid);
            $livesession->meetinguuid = (string) ($meeting['uuid'] ?? $livesession->meetinguuid);
            $livesession->passcode = (string) ($meeting['password'] ?? '');
            $livesession->joinurl = (string) ($meeting['join_url'] ?? '');
            $livesession->syncstatus = 'ok';
            $livesession->syncerror = null;
        } catch (zoom_exception $e) {
            $livesession->syncstatus = 'error';
            $livesession->syncerror = $e->getMessage();
        }

        $livesession->timemodified = time();
        $DB->update_record('livesession', $livesession);

        return $livesession;
    }

    /**
     * Translate a session record into the Zoom meeting object.
     *
     * @param stdClass $livesession
     * @return array
     */
    protected static function build_payload(stdClass $livesession): array {
        $topic = shorten_text(format_string($livesession->name, true), 190, true, '');

        $agenda = '';
        if (!empty($livesession->intro)) {
            $agenda = shorten_text(html_to_text($livesession->intro, 0, false), 1900, true, '');
        }

        $waitingroom = !empty($livesession->waitingroom);
        // Zoom refuses a meeting that has both; the waiting room is the stricter choice,
        // so when a teacher asks for both we honour the waiting room and drop join-before-host.
        $joinbeforehost = !empty($livesession->joinbeforehost) && !$waitingroom;

        $autorecord = in_array($livesession->autorecord, ['none', 'local', 'cloud'], true)
            ? $livesession->autorecord
            : 'none';

        return [
            'topic'      => $topic,
            'type'       => self::TYPE_SCHEDULED,
            'start_time' => gmdate('Y-m-d\TH:i:s\Z', (int) $livesession->starttime),
            'duration'   => max(1, (int) ceil($livesession->duration / MINSECS)),
            'timezone'   => 'UTC',
            'agenda'     => $agenda,
            'settings'   => [
                'host_video'             => true,
                'participant_video'      => false,
                'join_before_host'       => $joinbeforehost,
                'jbh_time'               => 0,
                'mute_upon_entry'        => !empty($livesession->muteonentry),
                'waiting_room'           => $waitingroom,
                'approval_type'          => 2,
                'auto_recording'         => $autorecord,
                'meeting_authentication' => false,
                'show_share_button'      => true,
            ],
        ];
    }

    /**
     * Remove the Zoom meeting backing a session.
     *
     * @param stdClass $livesession
     * @return void
     */
    public static function delete(stdClass $livesession): void {
        if (empty($livesession->meetingid) || !client::is_configured()) {
            return;
        }
        try {
            client::instance()->delete_meeting($livesession->meetingid);
        } catch (zoom_exception $e) {
            // The activity is going away regardless; leaving an orphaned Zoom meeting
            // behind is preferable to blocking the delete, so note it and move on.
            debugging('mod_livesession: could not delete Zoom meeting ' . $livesession->meetingid
                . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * The window during which students may enter the embedded meeting.
     *
     * @param stdClass $livesession
     * @return array [int opentime, int closetime]
     */
    public static function join_window(stdClass $livesession): array {
        $open = (int) $livesession->starttime - (int) $livesession->joinwindowbefore;
        $close = (int) $livesession->starttime + (int) $livesession->duration + (int) $livesession->joinwindowafter;
        return [$open, $close];
    }

    /**
     * Whether the session is open for joining right now.
     *
     * @param stdClass $livesession
     * @param int|null $now defaults to the current time
     * @return bool
     */
    public static function is_joinable(stdClass $livesession, ?int $now = null): bool {
        $now = $now ?? time();
        [$open, $close] = self::join_window($livesession);
        return $now >= $open && $now <= $close;
    }

    /**
     * When the meeting is considered finished for reconciliation purposes.
     *
     * @param stdClass $livesession
     * @return int
     */
    public static function endtime(stdClass $livesession): int {
        return (int) $livesession->starttime + (int) $livesession->duration;
    }
}
