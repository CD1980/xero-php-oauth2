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
 * Backup task definition for mod_livesession.
 *
 * @package    mod_livesession
 * @copyright  2026 Aspire Education and Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/livesession/backup/moodle2/backup_livesession_stepslib.php');

/**
 * Backup task for the livesession activity.
 *
 * @package    mod_livesession
 * @copyright  2026 Aspire Education and Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_livesession_activity_task extends backup_activity_task {
    /**
     * No activity-specific backup settings.
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
        $this->add_step(new backup_livesession_activity_structure_step(
            'livesession_structure',
            'livesession.xml'
        ));
    }

    /**
     * Encode links to this activity so they survive a restore elsewhere.
     *
     * @param string $content
     * @return string
     */
    public static function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, '/');

        $content = preg_replace(
            '/(' . $base . '\/mod\/livesession\/index.php\?id\=)([0-9]+)/',
            '$@LIVESESSIONINDEX*$2@$',
            $content
        );

        $content = preg_replace(
            '/(' . $base . '\/mod\/livesession\/view.php\?id\=)([0-9]+)/',
            '$@LIVESESSIONVIEWBYID*$2@$',
            $content
        );

        return $content;
    }
}
