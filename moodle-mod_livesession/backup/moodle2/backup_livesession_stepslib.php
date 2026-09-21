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
 * Backup structure steps for mod_livesession.
 *
 * @package    mod_livesession
 * @copyright  2026 Aspire Education and Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Defines the complete livesession structure for backup.
 *
 * @package    mod_livesession
 * @copyright  2026 Aspire Education and Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_livesession_activity_structure_step extends backup_activity_structure_step {

    /**
     * Build the backup structure.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        $livesession = new backup_nested_element('livesession', ['id'], [
            'name', 'intro', 'introformat', 'starttime', 'duration', 'sessiontimezone',
            'joinwindowbefore', 'joinwindowafter', 'hostuserid', 'zoomhostid', 'meetingid',
            'meetinguuid', 'passcode', 'joinurl', 'waitingroom', 'joinbeforehost',
            'muteonentry', 'autorecord', 'gradingmethod', 'requiredpercent', 'requiredminutes',
            'latethreshold', 'recordip', 'ipinfeedback', 'grade', 'completionattendance',
            'syncstatus', 'timecreated', 'timemodified',
        ]);

        $attendances = new backup_nested_element('attendances');
        $attendance = new backup_nested_element('attendance', ['id'], [
            'userid', 'status', 'firstjoin', 'lastseen', 'lastleave', 'duration', 'joincount',
            'firstip', 'lastip', 'useragent', 'sessionhash', 'usernamesnapshot', 'lastlogin',
            'zoomparticipantuuid', 'grade', 'gradedtime', 'overridden', 'overriddenby',
            'remarks', 'timecreated', 'timemodified',
        ]);

        $logs = new backup_nested_element('logs');
        $log = new backup_nested_element('log', ['id'], [
            'attendanceid', 'userid', 'action', 'ipaddress', 'useragent', 'sessionhash',
            'extra', 'timecreated',
        ]);

        $livesession->add_child($attendances);
        $attendances->add_child($attendance);
        $livesession->add_child($logs);
        $logs->add_child($log);

        $livesession->set_source_table('livesession', ['id' => backup::VAR_ACTIVITYID]);

        if ($userinfo) {
            $attendance->set_source_table('livesession_attendance',
                ['livesessionid' => backup::VAR_PARENTID]);
            $log->set_source_table('livesession_log',
                ['livesessionid' => backup::VAR_PARENTID]);
        }

        $attendance->annotate_ids('user', 'userid');
        $attendance->annotate_ids('user', 'overriddenby');
        $log->annotate_ids('user', 'userid');
        $livesession->annotate_ids('user', 'hostuserid');

        $livesession->annotate_files('mod_livesession', 'intro', null);

        return $this->prepare_activity_structure($livesession);
    }
}
