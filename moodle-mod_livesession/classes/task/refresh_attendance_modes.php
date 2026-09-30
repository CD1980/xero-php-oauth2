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

namespace mod_livesession\task;

use core\task\adhoc_task;

/**
 * Brings stored statuses and grades into line after the attendance mode changed.
 *
 * Queued by the upgrade that introduced attendance modes. Every record on the site was
 * graded against a time requirement that may no longer apply, so a student who joined
 * and left early is still sitting at "partial" until something recalculates them.
 *
 * This is a task rather than part of the upgrade step itself because writing to the
 * gradebook needs get_fast_modinfo(), which refuses to run while an upgrade is in
 * progress. Cron picks this up immediately afterwards, when the site is whole again.
 *
 * @package    mod_livesession
 * @copyright  2026 Aspire Education and Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class refresh_attendance_modes extends adhoc_task {
    /**
     * Recalculate every attendance record on the site.
     *
     * @return void
     */
    public function execute() {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/livesession/lib.php');

        $sessions = $DB->get_recordset('livesession');
        foreach ($sessions as $livesession) {
            // Teacher overrides are safe: recalculate() refuses to move a record someone
            // has corrected by hand.
            livesession_recalculate_all($livesession);
        }
        $sessions->close();
    }
}
