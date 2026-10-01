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

/**
 * Scheduled task definitions for mod_livesession.
 *
 * @package    mod_livesession
 * @copyright  2026 Aspire Education and Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        // Pull authoritative participant durations back from Zoom and finalise grades.
        'classname' => 'mod_livesession\task\reconcile_attendance',
        'blocking'  => 0,
        'minute'    => '*/15',
        'hour'      => '*',
        'day'       => '*',
        'dayofweek' => '*',
        'month'     => '*',
    ],
    [
        // Close out attendance rows whose browser vanished without sending a leave event.
        'classname' => 'mod_livesession\task\close_stale_segments',
        'blocking'  => 0,
        'minute'    => '*/5',
        'hour'      => '*',
        'day'       => '*',
        'dayofweek' => '*',
        'month'     => '*',
    ],
];
