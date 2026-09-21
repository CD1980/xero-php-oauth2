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

use core\task\scheduled_task;
use mod_livesession\local\attendance;

/**
 * Closes attendance segments whose browser stopped calling in.
 *
 * A student who closes the laptop lid never sends a leave event, so their record would
 * otherwise sit open with a stale lastseen. This finalises those rows so the grade
 * settles without waiting for the Zoom reconciliation.
 *
 * @package    mod_livesession
 * @copyright  2026 Aspire Education and Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class close_stale_segments extends scheduled_task {
    /**
     * Task name for the admin UI.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task:closestalesegments', 'mod_livesession');
    }

    /**
     * Run the sweep.
     *
     * @return void
     */
    public function execute() {
        global $DB;

        $timeout = attendance::stale_timeout();
        $cutoff = time() - $timeout;

        $sql = "SELECT a.*
                  FROM {livesession_attendance} a
                 WHERE a.lastseen > 0
                       AND a.lastseen < :cutoff
                       AND a.lastleave < a.lastseen
                       AND a.overridden = 0";
        $records = $DB->get_records_sql($sql, ['cutoff' => $cutoff]);
        if (!$records) {
            return;
        }

        $sessionids = array_unique(array_map(static fn($r) => (int) $r->livesessionid, $records));
        $sessions = $DB->get_records_list('livesession', 'id', $sessionids);

        foreach ($records as $record) {
            if (!isset($sessions[$record->livesessionid])) {
                continue;
            }
            $livesession = $sessions[$record->livesessionid];

            // The last heartbeat is the last moment we know they were there; nothing
            // after it is credited.
            $record->lastleave = (int) $record->lastseen;
            $record->timemodified = time();
            $DB->update_record('livesession_attendance', $record);

            attendance::log($livesession, $record, 'leave', null, ['reason' => 'stale']);
            attendance::recalculate($livesession, $record);
        }

        mtrace('mod_livesession: closed ' . count($records) . ' stale attendance segment(s).');
    }
}
