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

namespace mod_livesession;

use core\activity_dates;

/**
 * Supplies the start and end shown under the activity name on the course page.
 *
 * @package    mod_livesession
 * @copyright  2026 Aspire Education and Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dates extends activity_dates {
    /**
     * The dates to display for this activity.
     *
     * @return array
     */
    protected function get_dates(): array {
        $starttime = (int) ($this->cm->customdata['starttime'] ?? 0);
        $duration = (int) ($this->cm->customdata['duration'] ?? 0);

        if (!$starttime) {
            return [];
        }

        return [
            [
                'dataid'    => 'starttime',
                'label'     => get_string('starts', 'mod_livesession') . ':',
                'timestamp' => $starttime,
            ],
            [
                'dataid'    => 'endtime',
                'label'     => get_string('ends', 'mod_livesession') . ':',
                'timestamp' => $starttime + $duration,
            ],
        ];
    }
}
