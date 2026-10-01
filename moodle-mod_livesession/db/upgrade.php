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
 * Upgrade steps for mod_livesession.
 *
 * @package    mod_livesession
 * @copyright  2026 Aspire Education and Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute the mod_livesession upgrade from the given old version.
 *
 * @param int $oldversion the version we are upgrading from
 * @return bool
 */
function xmldb_livesession_upgrade($oldversion) {
    global $DB;

    if ($oldversion < 2026092104) {
        // The Meeting SDK version default moved from 3.13.2 to 6.5.0, because Zoom no
        // longer serves the 3.x bundle. Changing the default in settings.php does not
        // touch a value already stored, so a site that never edited this setting would
        // keep failing with "Could not load the Zoom Meeting SDK" after upgrading.
        // Only the stale default is replaced; a deliberate choice is left alone.
        if ((string) get_config('mod_livesession', 'sdkversion') === '3.13.2') {
            set_config('sdkversion', '6.5.0', 'mod_livesession');
        }

        upgrade_mod_savepoint(true, 2026092104, 'livesession');
    }

    if ($oldversion < 2026093000) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('livesession');

        // A session may lend its Zoom meeting to other sessions running at the same time,
        // so that one instructor hosts a single room that several cohorts join.
        $field = new xmldb_field('ismaster', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'autorecord');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('mastersessionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'ismaster');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $index = new xmldb_index('mastersessionid', XMLDB_INDEX_NOTUNIQUE, ['mastersessionid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Attendance is present-on-join from here on. Existing sessions are moved to it
        // as well rather than left behind on the timed behaviour: it is the site-wide
        // change that was asked for, and any session that still wants timed attendance
        // is one dropdown away on the activity settings form.
        $field = new xmldb_field('attendancemode', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'mastersessionid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Every existing record was graded against a requirement that no longer applies,
        // so a student who joined but left early is still sitting at "partial". The
        // recalculation writes to the gradebook, which cannot be done from inside an
        // upgrade, so it is handed to cron to run the moment the site is whole again.
        \core\task\manager::queue_adhoc_task(
            new \mod_livesession\task\refresh_attendance_modes()
        );

        upgrade_mod_savepoint(true, 2026093000, 'livesession');
    }

    return true;
}
