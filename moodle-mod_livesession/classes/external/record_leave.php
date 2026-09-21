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

/**
 * Closes the current user's attendance segment when they leave the embedded meeting.
 *
 * @package    mod_livesession
 * @copyright  2026 Aspire Education and Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class record_leave extends external_api {

    use session_trait;

    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'   => new external_value(PARAM_INT, 'Course module id of the live session'),
            'reason' => new external_value(PARAM_ALPHANUMEXT, 'Why the client left', VALUE_DEFAULT, 'user'),
        ]);
    }

    /**
     * Stop the clock for this user.
     *
     * @param int $cmid
     * @param string $reason
     * @return array
     */
    public static function execute(int $cmid, string $reason = 'user'): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid, 'reason' => $reason]);

        [$cm, $course, $livesession, $context] = self::load_session($params['cmid']);

        $record = attendance::close_segment($livesession, (int) $USER->id);

        return [
            'duration' => $record ? (int) $record->duration : 0,
            'status'   => $record ? (string) $record->status : attendance::STATUS_ABSENT,
        ];
    }

    /**
     * Return value definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'duration' => new external_value(PARAM_INT, 'Accumulated attended seconds'),
            'status'   => new external_value(PARAM_ALPHA, 'Final attendance status'),
        ]);
    }
}
