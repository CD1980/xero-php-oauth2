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

namespace mod_livesession\privacy;

use context;
use context_module;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy API implementation for mod_livesession.
 *
 * This plugin stores attendance evidence - times, IP addresses, browser user agents and
 * a hash of the Moodle session - and sends a display name (and, for hosts, an email
 * address) to Zoom so the meeting can be joined.
 *
 * @package    mod_livesession
 * @copyright  2026 Aspire Education and Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        \core_privacy\local\request\core_userlist_provider {

    /**
     * Describe the personal data this plugin stores and transmits.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {

        $collection->add_database_table('livesession_attendance', [
            'userid'              => 'privacy:metadata:attendance:userid',
            'status'              => 'privacy:metadata:attendance:status',
            'firstjoin'           => 'privacy:metadata:attendance:firstjoin',
            'lastseen'            => 'privacy:metadata:attendance:lastseen',
            'lastleave'           => 'privacy:metadata:attendance:lastleave',
            'duration'            => 'privacy:metadata:attendance:duration',
            'joincount'           => 'privacy:metadata:attendance:joincount',
            'firstip'             => 'privacy:metadata:attendance:firstip',
            'lastip'              => 'privacy:metadata:attendance:lastip',
            'useragent'           => 'privacy:metadata:attendance:useragent',
            'sessionhash'         => 'privacy:metadata:attendance:sessionhash',
            'usernamesnapshot'    => 'privacy:metadata:attendance:usernamesnapshot',
            'lastlogin'           => 'privacy:metadata:attendance:lastlogin',
            'zoomparticipantuuid' => 'privacy:metadata:attendance:zoomparticipantuuid',
            'grade'               => 'privacy:metadata:attendance:grade',
            'remarks'             => 'privacy:metadata:attendance:remarks',
        ], 'privacy:metadata:attendance');

        $collection->add_database_table('livesession_log', [
            'userid'      => 'privacy:metadata:log:userid',
            'action'      => 'privacy:metadata:log:action',
            'ipaddress'   => 'privacy:metadata:log:ipaddress',
            'useragent'   => 'privacy:metadata:log:useragent',
            'sessionhash' => 'privacy:metadata:log:sessionhash',
            'extra'       => 'privacy:metadata:log:extra',
            'timecreated' => 'privacy:metadata:log:timecreated',
        ], 'privacy:metadata:log');

        $collection->add_external_location_link('zoom', [
            'fullname' => 'privacy:metadata:zoom:fullname',
            'email'    => 'privacy:metadata:zoom:email',
        ], 'privacy:metadata:zoom');

        return $collection;
    }

    /**
     * Contexts in which the given user has attendance data.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT DISTINCT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {livesession} ls ON ls.id = cm.instance
                  JOIN {livesession_attendance} a ON a.livesessionid = ls.id
                 WHERE a.userid = :userid";

        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_MODULE,
            'modname'      => 'livesession',
            'userid'       => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Users who have attendance data in the given context.
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof context_module) {
            return;
        }

        $sql = "SELECT a.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {livesession} ls ON ls.id = cm.instance
                  JOIN {livesession_attendance} a ON a.livesessionid = ls.id
                 WHERE cm.id = :cmid";

        $userlist->add_from_sql('userid', $sql, [
            'modname' => 'livesession',
            'cmid'    => $context->instanceid,
        ]);

        $sql = "SELECT l.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {livesession} ls ON ls.id = cm.instance
                  JOIN {livesession_log} l ON l.livesessionid = ls.id
                 WHERE cm.id = :cmid";

        $userlist->add_from_sql('userid', $sql, [
            'modname' => 'livesession',
            'cmid'    => $context->instanceid,
        ]);
    }

    /**
     * Export the user's attendance data for the approved contexts.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (!count($contextlist)) {
            return;
        }

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_module) {
                continue;
            }

            $cm = get_coursemodule_from_id('livesession', $context->instanceid);
            if (!$cm) {
                continue;
            }

            $record = $DB->get_record('livesession_attendance', [
                'livesessionid' => $cm->instance,
                'userid'        => $user->id,
            ]);

            if ($record) {
                $data = (object) [
                    'status'        => $record->status,
                    'firstjoin'     => $record->firstjoin ? transform::datetime($record->firstjoin) : null,
                    'lastseen'      => $record->lastseen ? transform::datetime($record->lastseen) : null,
                    'lastleave'     => $record->lastleave ? transform::datetime($record->lastleave) : null,
                    'durationsecs'  => (int) $record->duration,
                    'joincount'     => (int) $record->joincount,
                    'firstip'       => $record->firstip,
                    'lastip'        => $record->lastip,
                    'useragent'     => $record->useragent,
                    'username'      => $record->usernamesnapshot,
                    'lastlogin'     => $record->lastlogin ? transform::datetime($record->lastlogin) : null,
                    'grade'         => $record->grade,
                    'remarks'       => $record->remarks,
                ];
                writer::with_context($context)
                    ->export_data([get_string('privacy:attendancepath', 'mod_livesession')], $data);
            }

            $logs = $DB->get_records('livesession_log', [
                'livesessionid' => $cm->instance,
                'userid'        => $user->id,
            ], 'timecreated ASC');

            if ($logs) {
                $rows = [];
                foreach ($logs as $log) {
                    $rows[] = (object) [
                        'action'      => $log->action,
                        'ipaddress'   => $log->ipaddress,
                        'useragent'   => $log->useragent,
                        'extra'       => $log->extra,
                        'timecreated' => transform::datetime($log->timecreated),
                    ];
                }
                writer::with_context($context)
                    ->export_data([get_string('privacy:logpath', 'mod_livesession')], (object) ['events' => $rows]);
            }
        }
    }

    /**
     * Delete all attendance data for everyone in a context.
     *
     * @param context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context) {
        global $DB;

        if (!$context instanceof context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('livesession', $context->instanceid);
        if (!$cm) {
            return;
        }

        $DB->delete_records('livesession_log', ['livesessionid' => $cm->instance]);
        $DB->delete_records('livesession_attendance', ['livesessionid' => $cm->instance]);
    }

    /**
     * Delete one user's attendance data across the approved contexts.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $userid = (int) $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('livesession', $context->instanceid);
            if (!$cm) {
                continue;
            }
            $DB->delete_records('livesession_log', [
                'livesessionid' => $cm->instance,
                'userid'        => $userid,
            ]);
            $DB->delete_records('livesession_attendance', [
                'livesessionid' => $cm->instance,
                'userid'        => $userid,
            ]);
        }
    }

    /**
     * Delete attendance data for the listed users in one context.
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('livesession', $context->instanceid);
        if (!$cm) {
            return;
        }

        $userids = $userlist->get_userids();
        if (!$userids) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['livesessionid'] = $cm->instance;

        $DB->delete_records_select('livesession_log',
            "livesessionid = :livesessionid AND userid $insql", $params);
        $DB->delete_records_select('livesession_attendance',
            "livesessionid = :livesessionid AND userid $insql", $params);
    }
}
