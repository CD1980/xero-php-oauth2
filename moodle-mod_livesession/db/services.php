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
 * External service (AJAX) declarations for mod_livesession.
 *
 * @package    mod_livesession
 * @copyright  2026 Aspire Education and Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_livesession_join_session' => [
        'classname'   => 'mod_livesession\external\join_session',
        'description' => 'Mint a Zoom Meeting SDK signature and open an attendance record for the current user.',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'mod/livesession:join',
    ],
    'mod_livesession_record_heartbeat' => [
        'classname'   => 'mod_livesession\external\record_heartbeat',
        'description' => 'Record that the current user is still in the embedded meeting.',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'mod/livesession:join',
    ],
    'mod_livesession_record_leave' => [
        'classname'   => 'mod_livesession\external\record_leave',
        'description' => 'Close the current user\'s attendance segment when they leave the meeting.',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'mod/livesession:join',
    ],
];
