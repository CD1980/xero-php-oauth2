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
 * Test data generator for mod_livesession.
 *
 * @package    mod_livesession
 * @copyright  2026 Aspire Education and Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Creates live session activity instances for tests.
 *
 * @package    mod_livesession
 * @copyright  2026 Aspire Education and Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_livesession_generator extends testing_module_generator {

    /**
     * Create a live session instance with sensible test defaults.
     *
     * A meeting id is pre-set so tests exercise the attendance logic without Zoom
     * credentials; with none configured the instance is simply marked unsynced.
     *
     * @param array|stdClass|null $record
     * @param array|null $options
     * @return stdClass
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (object) (array) $record;

        $defaults = [
            'name'                 => 'Test live session',
            'intro'                => 'Test session description',
            'introformat'          => FORMAT_HTML,
            'starttime'            => time(),
            'duration'             => HOURSECS,
            'joinwindowbefore'     => 15 * MINSECS,
            'joinwindowafter'      => 15 * MINSECS,
            'zoomhostid'           => 'me',
            'meetingid'            => '81234567890',
            'meetinguuid'          => 'aBcDeFgH/1234==',
            'passcode'             => '123456',
            'joinurl'              => 'https://example.zoom.us/j/81234567890',
            'waitingroom'          => 0,
            'joinbeforehost'       => 1,
            'muteonentry'          => 1,
            'autorecord'           => 'none',
            'gradingmethod'        => 1,
            'requiredpercent'      => 80,
            'requiredminutes'      => 0,
            'latethreshold'        => 5 * MINSECS,
            'recordip'             => 1,
            'ipinfeedback'         => 1,
            'grade'                => 100,
            'completionattendance' => 0,
            'syncstatus'           => 'ok',
        ];

        foreach ($defaults as $key => $value) {
            if (!isset($record->{$key})) {
                $record->{$key} = $value;
            }
        }

        return parent::create_instance($record, (array) $options);
    }
}
