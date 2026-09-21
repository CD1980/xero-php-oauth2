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

namespace mod_livesession\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_livesession\local\attendance;
use mod_livesession\local\meeting_manager;
use mod_livesession\local\zoom\signature;
use moodle_exception;
use moodle_url;

/**
 * Hands the browser a short-lived Zoom Meeting SDK signature and opens an attendance record.
 *
 * The SDK secret stays on the server; the browser only ever receives a signed token that
 * is good for this one meeting, in this one role.
 *
 * @package    mod_livesession
 * @copyright  2026 Aspire Education and Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class join_session extends external_api {
    use session_trait;

    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id of the live session'),
        ]);
    }

    /**
     * Authorise the current user into the meeting and start recording their attendance.
     *
     * @param int $cmid
     * @return array
     * @throws moodle_exception
     */
    public static function execute(int $cmid): array {
        global $USER;

        ['cmid' => $cmid] = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);

        [$cm, $course, $livesession, $context] = self::load_session($cmid);

        if (empty($livesession->meetingid)) {
            throw new moodle_exception('error:nomeeting', 'mod_livesession');
        }

        $ishost = has_capability('mod/livesession:host', $context);

        // Hosts may open the room early to set up; students are held to the join window.
        if (!$ishost && !meeting_manager::is_joinable($livesession)) {
            throw new moodle_exception('error:outsidejoinwindow', 'mod_livesession');
        }

        $role = $ishost ? signature::ROLE_HOST : signature::ROLE_ATTENDEE;
        $token = signature::create((string) $livesession->meetingid, $role);

        attendance::open_segment($livesession, (int) $USER->id);

        $event = \mod_livesession\event\session_joined::create([
            'context'  => $context,
            'objectid' => $livesession->id,
        ]);
        $event->trigger();

        return [
            'signature'         => $token,
            'sdkkey'            => (string) get_config('mod_livesession', 'sdkclientid'),
            'sdkversion'        => (string) get_config('mod_livesession', 'sdkversion'),
            'sdkurl'            => (string) get_config('mod_livesession', 'sdkurl'),
            'meetingnumber'     => (string) $livesession->meetingid,
            'passcode'          => (string) $livesession->passcode,
            'username'          => fullname($USER),
            'useremail'         => $ishost ? (string) $USER->email : '',
            'role'              => $role,
            'heartbeatinterval' => attendance::heartbeat_interval(),
            'leaveurl'          => (new moodle_url(
                '/mod/livesession/view.php',
                ['id' => $cm->id, 'left' => 1]
            ))->out(false),
        ];
    }

    /**
     * Return value definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'signature'         => new external_value(PARAM_RAW, 'Meeting SDK JWT'),
            'sdkkey'            => new external_value(PARAM_RAW, 'Meeting SDK client id'),
            'sdkversion'        => new external_value(PARAM_RAW, 'Meeting SDK version to load'),
            'sdkurl'            => new external_value(PARAM_RAW, 'Full SDK URL override, empty to use the defaults'),
            'meetingnumber'     => new external_value(PARAM_RAW, 'Zoom meeting number'),
            'passcode'          => new external_value(PARAM_RAW, 'Meeting passcode'),
            'username'          => new external_value(PARAM_TEXT, 'Display name inside the meeting'),
            'useremail'         => new external_value(PARAM_RAW, 'Email, sent only for hosts'),
            'role'              => new external_value(PARAM_INT, '0 attendee, 1 host'),
            'heartbeatinterval' => new external_value(PARAM_INT, 'Seconds between heartbeats'),
            'leaveurl'          => new external_value(PARAM_URL, 'Where the SDK returns the user on leave'),
        ]);
    }
}
