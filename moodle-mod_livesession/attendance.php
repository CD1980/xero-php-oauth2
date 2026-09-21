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
 * Attendance report for a live session.
 *
 * Shows every participant's captured attendance, including the IP address and sign-in
 * details behind their gradebook mark, and offers a CSV export of the same evidence.
 *
 * @package    mod_livesession
 * @copyright  2026 Aspire Education and Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/csvlib.class.php');

use mod_livesession\local\attendance;

$id = required_param('id', PARAM_INT);
$download = optional_param('download', '', PARAM_ALPHA);
$groupid = optional_param('group', 0, PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'livesession');
require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/livesession:viewattendance', $context);

$livesession = $DB->get_record('livesession', ['id' => $cm->instance], '*', MUST_EXIST);

$pageurl = new moodle_url('/mod/livesession/attendance.php', ['id' => $cm->id]);
$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($livesession->name) . ': ' . get_string('attendance', 'mod_livesession'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$canmanage = has_capability('mod/livesession:manageattendance', $context);
$showip = !empty($livesession->recordip) && get_config('mod_livesession', 'recordip');

// Participants are everyone who could have joined, so absentees show up too.
$currentgroup = groups_get_activity_group($cm, true);
$users = get_enrolled_users($context, 'mod/livesession:join', $currentgroup,
    'u.*', 'u.lastname ASC, u.firstname ASC');

$records = attendance::get_records((int) $livesession->id);
$byuser = [];
foreach ($records as $record) {
    $byuser[(int) $record->userid] = $record;
}

$required = attendance::required_seconds($livesession);
$graded = (int) $livesession->grade > 0 && (int) $livesession->gradingmethod !== attendance::GRADING_NONE;

// --- CSV export -----------------------------------------------------------------

if ($download === 'csv') {
    $filename = clean_filename(format_string($livesession->name) . '-attendance');
    $csv = new csv_export_writer();
    $csv->set_filename($filename);

    $header = [
        get_string('fullname'),
        get_string('username'),
        get_string('email'),
        get_string('status', 'mod_livesession'),
        get_string('firstjoin', 'mod_livesession'),
        get_string('lastleave', 'mod_livesession'),
        get_string('attendedfor', 'mod_livesession'),
        get_string('joincount', 'mod_livesession'),
        get_string('lastlogin', 'mod_livesession'),
    ];
    if ($showip) {
        $header[] = get_string('firstip', 'mod_livesession');
        $header[] = get_string('lastip', 'mod_livesession');
    }
    $header[] = get_string('useragent', 'mod_livesession');
    if ($graded) {
        $header[] = get_string('grade');
    }
    $header[] = get_string('overridden', 'mod_livesession');
    $csv->add_data($header);

    foreach ($users as $user) {
        $record = $byuser[$user->id] ?? null;
        $row = [
            fullname($user),
            $user->username,
            $user->email,
            get_string('status:' . ($record->status ?? attendance::STATUS_ABSENT), 'mod_livesession'),
            ($record && $record->firstjoin) ? userdate($record->firstjoin) : '',
            ($record && $record->lastleave) ? userdate($record->lastleave) : '',
            $record ? format_time((int) $record->duration) : '',
            $record ? (int) $record->joincount : 0,
            ($record && $record->lastlogin) ? userdate($record->lastlogin) : '',
        ];
        if ($showip) {
            $row[] = $record->firstip ?? '';
            $row[] = $record->lastip ?? '';
        }
        $row[] = $record->useragent ?? '';
        if ($graded) {
            $row[] = ($record && $record->grade !== null) ? format_float($record->grade, 2) : '';
        }
        $row[] = ($record && $record->overridden) ? get_string('yes') : get_string('no');
        $csv->add_data($row);
    }

    $csv->download_file();
    exit;
}

// --- Screen output ----------------------------------------------------------------

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($livesession->name) . ': '
    . get_string('attendance', 'mod_livesession'));

groups_print_activity_menu($cm, $pageurl);

$summary = new html_table();
$summary->attributes['class'] = 'generaltable';
$summary->data = [
    [get_string('starts', 'mod_livesession'), userdate($livesession->starttime)],
    [get_string('attendancerequirement', 'mod_livesession'), format_time($required)],
    [get_string('participants'), count($users)],
];
echo html_writer::table($summary);

if ($showip) {
    echo $OUTPUT->notification(get_string('ipnotice', 'mod_livesession'), 'info');
}

$table = new html_table();
$table->attributes['class'] = 'generaltable mod-livesession-attendance';
$table->head = [
    get_string('fullname'),
    get_string('status', 'mod_livesession'),
    get_string('firstjoin', 'mod_livesession'),
    get_string('attendedfor', 'mod_livesession'),
    get_string('joincount', 'mod_livesession'),
];
if ($showip) {
    $table->head[] = get_string('ipaddress', 'mod_livesession');
}
$table->head[] = get_string('useragent', 'mod_livesession');
if ($graded) {
    $table->head[] = get_string('grade');
}
if ($canmanage) {
    $table->head[] = get_string('actions');
}

foreach ($users as $user) {
    $record = $byuser[$user->id] ?? null;
    $status = $record->status ?? attendance::STATUS_ABSENT;

    $statuscell = new html_table_cell(get_string('status:' . $status, 'mod_livesession'));
    $statuscell->attributes['class'] = 'status-' . $status;
    if ($record && $record->overridden) {
        $statuscell->text .= ' ' . html_writer::tag('span',
            get_string('overriddenshort', 'mod_livesession'), ['class' => 'badge bg-secondary']);
    }

    $row = [
        html_writer::link(new moodle_url('/user/view.php',
            ['id' => $user->id, 'course' => $course->id]), fullname($user)),
        $statuscell,
        ($record && $record->firstjoin) ? userdate($record->firstjoin) : '-',
        $record ? format_time((int) $record->duration) : '-',
        $record ? (int) $record->joincount : 0,
    ];

    if ($showip) {
        $ip = '-';
        if ($record && $record->firstip) {
            $ip = s($record->firstip);
            if ($record->lastip && $record->lastip !== $record->firstip) {
                $ip .= html_writer::empty_tag('br') . s($record->lastip);
            }
        }
        $ipcell = new html_table_cell($ip);
        $ipcell->attributes['class'] = 'ipaddress';
        $row[] = $ipcell;
    }

    $uacell = new html_table_cell($record && $record->useragent ? s($record->useragent) : '-');
    $uacell->attributes['class'] = 'useragent';
    $row[] = $uacell;

    if ($graded) {
        $row[] = ($record && $record->grade !== null)
            ? format_float($record->grade, 2) . ' / ' . (int) $livesession->grade
            : '-';
    }

    if ($canmanage) {
        $row[] = html_writer::link(
            new moodle_url('/mod/livesession/override.php', ['id' => $cm->id, 'userid' => $user->id]),
            get_string('edit'),
            ['class' => 'btn btn-sm btn-secondary']
        );
    }

    $table->data[] = $row;
}

echo html_writer::table($table);

echo html_writer::tag('p', html_writer::link(
    new moodle_url($pageurl, ['download' => 'csv', 'group' => $currentgroup]),
    get_string('downloadcsv', 'mod_livesession'),
    ['class' => 'btn btn-secondary']
));

if ($graded) {
    echo html_writer::tag('p', html_writer::link(
        new moodle_url('/grade/report/grader/index.php', ['id' => $course->id]),
        get_string('gotogradebook', 'mod_livesession')
    ));
}

echo $OUTPUT->footer();
