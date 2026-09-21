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
 * Restore structure steps for mod_livesession.
 *
 * @package    mod_livesession
 * @copyright  2026 Aspire Education and Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restores the livesession activity structure.
 *
 * @package    mod_livesession
 * @copyright  2026 Aspire Education and Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_livesession_activity_structure_step extends restore_activity_structure_step {
    /**
     * Elements this step can restore.
     *
     * @return array
     */
    protected function define_structure() {
        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('livesession', '/activity/livesession');

        if ($userinfo) {
            $paths[] = new restore_path_element(
                'livesession_attendance',
                '/activity/livesession/attendances/attendance'
            );
            $paths[] = new restore_path_element(
                'livesession_log',
                '/activity/livesession/logs/log'
            );
        }

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restore the activity instance.
     *
     * The Zoom meeting itself is deliberately not recreated here: the restored copy
     * points at the original meeting id, which may belong to a different Zoom account
     * or may no longer exist. Marking it pending makes the teacher re-save the
     * activity, which creates a fresh meeting under the right host.
     *
     * @param array $data
     * @return void
     */
    protected function process_livesession($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $data->starttime = $this->apply_date_offset($data->starttime);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        // Do not carry another site's meeting across; force a re-sync.
        $data->meetingid = null;
        $data->meetinguuid = null;
        $data->passcode = null;
        $data->joinurl = null;
        $data->syncstatus = 'pending';
        $data->syncerror = null;
        $data->lastreconciled = 0;

        $newitemid = $DB->insert_record('livesession', $data);
        $this->apply_activity_instance($newitemid);
        $this->set_mapping('livesession', $oldid, $newitemid);
    }

    /**
     * Restore one attendance record.
     *
     * @param array $data
     * @return void
     */
    protected function process_livesession_attendance($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->livesessionid = $this->get_new_parentid('livesession');
        $data->userid = $this->get_mappingid('user', $data->userid);
        if (!empty($data->overriddenby)) {
            $data->overriddenby = (int) $this->get_mappingid('user', $data->overriddenby);
        }
        if (empty($data->userid)) {
            return;
        }

        $newitemid = $DB->insert_record('livesession_attendance', $data);
        $this->set_mapping('livesession_attendance', $oldid, $newitemid);
    }

    /**
     * Restore one audit log row.
     *
     * @param array $data
     * @return void
     */
    protected function process_livesession_log($data) {
        global $DB;

        $data = (object) $data;
        $data->livesessionid = $this->get_new_parentid('livesession');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->attendanceid = (int) $this->get_mappingid('livesession_attendance', $data->attendanceid);
        if (empty($data->userid)) {
            return;
        }

        $DB->insert_record('livesession_log', $data);
    }

    /**
     * Re-attach intro files after the structure is in place.
     *
     * @return void
     */
    protected function after_execute() {
        $this->add_related_files('mod_livesession', 'intro', null);
    }
}
