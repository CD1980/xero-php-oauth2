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

use context_module;
use moodle_exception;

/**
 * Shared loading and access checks for the mod_livesession external functions.
 *
 * @package    mod_livesession
 * @copyright  2026 Aspire Education and Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait session_trait {
    /**
     * Resolve a course module id into everything the endpoints need, with access checked.
     *
     * @param int $cmid course module id
     * @return array [cm_info $cm, \stdClass $course, \stdClass $livesession, context_module $context]
     * @throws moodle_exception
     */
    protected static function load_session(int $cmid): array {
        global $DB;

        [$course, $cm] = get_course_and_cm_from_cmid($cmid, 'livesession');
        $context = context_module::instance($cm->id);

        self::validate_context($context);
        require_capability('mod/livesession:join', $context);

        $livesession = $DB->get_record('livesession', ['id' => $cm->instance], '*', MUST_EXIST);

        if (!$cm->uservisible) {
            throw new moodle_exception('error:notavailable', 'mod_livesession');
        }

        return [$cm, $course, $livesession, $context];
    }
}
