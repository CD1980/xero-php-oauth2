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
 * Restore task definition for mod_livesession.
 *
 * @package    mod_livesession
 * @copyright  2026 Aspire Education and Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/livesession/backup/moodle2/restore_livesession_stepslib.php');

/**
 * Restore task for the livesession activity.
 *
 * @package    mod_livesession
 * @copyright  2026 Aspire Education and Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_livesession_activity_task extends restore_activity_task {
    /**
     * No activity-specific restore settings.
     *
     * @return void
     */
    protected function define_my_settings() {
    }

    /**
     * Add the structure step.
     *
     * @return void
     */
    protected function define_my_steps() {
        $this->add_step(new restore_livesession_activity_structure_step(
            'livesession_structure',
            'livesession.xml'
        ));
    }

    /**
     * File areas this activity owns.
     *
     * @return array
     */
    public static function define_decode_contents() {
        return [
            new restore_decode_content('livesession', ['intro'], 'livesession'),
        ];
    }

    /**
     * Link decoding rules.
     *
     * @return array
     */
    public static function define_decode_rules() {
        return [
            new restore_decode_rule(
                'LIVESESSIONVIEWBYID',
                '/mod/livesession/view.php?id=$1',
                'course_module'
            ),
            new restore_decode_rule(
                'LIVESESSIONINDEX',
                '/mod/livesession/index.php?id=$1',
                'course'
            ),
        ];
    }
}
