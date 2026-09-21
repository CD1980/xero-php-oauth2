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

namespace mod_livesession\completion;

use core_completion\activity_custom_completion;
use mod_livesession\local\attendance;

/**
 * Attendance-based activity completion for mod_livesession.
 *
 * @package    mod_livesession
 * @copyright  2026 Aspire Education and Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_completion extends activity_custom_completion {
    /**
     * Evaluate a custom completion rule for the current user.
     *
     * @param string $rule the rule name
     * @return int COMPLETION_COMPLETE or COMPLETION_INCOMPLETE
     */
    public function get_state(string $rule): int {
        global $DB;

        $this->validate_rule($rule);

        $livesession = $DB->get_record('livesession', ['id' => $this->cm->instance], '*', MUST_EXIST);
        $requiredminutes = (int) $livesession->completionattendance;
        if ($requiredminutes <= 0) {
            return COMPLETION_INCOMPLETE;
        }

        $record = attendance::get_record((int) $livesession->id, (int) $this->userid);
        if ($record === false) {
            return COMPLETION_INCOMPLETE;
        }

        return ((int) $record->duration >= $requiredminutes * MINSECS)
            ? COMPLETION_COMPLETE
            : COMPLETION_INCOMPLETE;
    }

    /**
     * The rules this module defines.
     *
     * @return array
     */
    public static function get_defined_custom_rules(): array {
        return ['completionattendance'];
    }

    /**
     * Human readable descriptions of each rule.
     *
     * @return array
     */
    public function get_custom_rule_descriptions(): array {
        $minutes = (int) ($this->cm->customdata['customcompletionrules']['completionattendance'] ?? 0);
        return [
            'completionattendance' => get_string('completiondetail:attendance', 'mod_livesession', $minutes),
        ];
    }

    /**
     * Order in which completion conditions are shown.
     *
     * @return array
     */
    public function get_sort_order(): array {
        return ['completionview', 'completionattendance', 'completionusegrade'];
    }
}
