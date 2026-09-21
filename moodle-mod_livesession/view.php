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
 * Student and teacher view of a live session, with the meeting embedded in the page.
 *
 * @package    mod_livesession
 * @copyright  2026 Aspire Education and Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use mod_livesession\local\attendance;
use mod_livesession\local\meeting_manager;
use mod_livesession\local\zoom\client;
use mod_livesession\local\zoom\signature;

$id = required_param('id', PARAM_INT);
$left = optional_param('left', 0, PARAM_BOOL);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'livesession');
require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/livesession:view', $context);

$livesession = $DB->get_record('livesession', ['id' => $cm->instance], '*', MUST_EXIST);

$PAGE->set_url('/mod/livesession/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($livesession->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->add_body_class('mod-livesession-view');

// Log the view and tick "viewed" completion.
$event = \mod_livesession\event\course_module_viewed::create([
    'objectid' => $livesession->id,
    'context'  => $context,
]);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('livesession', $livesession);
$event->trigger();

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$ishost = has_capability('mod/livesession:host', $context);
$canjoin = has_capability('mod/livesession:join', $context);
[$windowopen, $windowclose] = meeting_manager::join_window($livesession);
$now = time();

// Moodle 4.x renders the activity name and description in the activity header, so
// neither is repeated here.
echo $OUTPUT->header();

// Schedule.
$schedule = new html_table();
$schedule->attributes['class'] = 'generaltable mod-livesession-schedule';
$schedule->data = [
    [get_string('starts', 'mod_livesession'), userdate($livesession->starttime)],
    [get_string('ends', 'mod_livesession'), userdate($livesession->starttime + $livesession->duration)],
    [get_string('duration', 'mod_livesession'), format_time((int) $livesession->duration)],
    [get_string('joinopens', 'mod_livesession'), userdate($windowopen)],
];
if ((int) $livesession->gradingmethod !== attendance::GRADING_NONE && (int) $livesession->grade > 0) {
    $schedule->data[] = [
        get_string('attendancerequirement', 'mod_livesession'),
        format_time(attendance::required_seconds($livesession)),
    ];
}
echo html_writer::table($schedule);

// Configuration problems, shown only to people who can act on them.
if ($ishost) {
    if (!client::is_configured() || !signature::is_configured()) {
        echo $OUTPUT->notification(get_string('error:notconfigured', 'mod_livesession'), 'error');
    } else if ($livesession->syncstatus === 'error') {
        echo $OUTPUT->notification(
            get_string('error:syncfailed', 'mod_livesession', s($livesession->syncerror)),
            'error'
        );
    }
    if (!empty($livesession->joinurl)) {
        echo html_writer::tag('p', html_writer::link(
            new moodle_url($livesession->joinurl),
            get_string('openinzoom', 'mod_livesession'),
            ['target' => '_blank', 'rel' => 'noreferrer noopener', 'class' => 'small']
        ));
    }
}

// The meeting itself.
if ($left) {
    echo $OUTPUT->notification(get_string('youleft', 'mod_livesession'), 'info');
}

if (!$canjoin) {
    echo $OUTPUT->notification(get_string('error:cannotjoin', 'mod_livesession'), 'warning');
} else if (empty($livesession->meetingid)) {
    echo $OUTPUT->notification(get_string('error:nomeeting', 'mod_livesession'), 'warning');
} else if (!$ishost && $now < $windowopen) {
    echo $OUTPUT->notification(
        get_string('notopenyet', 'mod_livesession', userdate($windowopen)),
        'info'
    );
} else if (!$ishost && $now > $windowclose) {
    echo $OUTPUT->notification(get_string('sessionclosed', 'mod_livesession'), 'info');
} else {
    $ids = [
        'rootid'    => 'livesession-root-' . $cm->id,
        'buttonid'  => 'livesession-join-' . $cm->id,
        'statusid'  => 'livesession-status-' . $cm->id,
        'counterid' => 'livesession-counter-' . $cm->id,
    ];

    $notice = !empty($livesession->recordip) && get_config('mod_livesession', 'recordip')
        ? get_string('attendancenoticeip', 'mod_livesession')
        : get_string('attendancenotice', 'mod_livesession');

    echo $OUTPUT->render_from_template('mod_livesession/embed', $ids + [
        'buttonlabel' => $ishost
            ? get_string('startsession', 'mod_livesession')
            : get_string('joinsession', 'mod_livesession'),
        'notice' => $notice,
        'ishost' => $ishost,
    ]);

    $PAGE->requires->js_call_amd('mod_livesession/embed', 'init', [
        $cm->id, $ids['rootid'], $ids['buttonid'], $ids['statusid'], $ids['counterid'],
    ]);
}

// Your own attendance so far.
$myrecord = attendance::get_record((int) $livesession->id, (int) $USER->id);
if ($myrecord) {
    echo $OUTPUT->heading(get_string('yourattendance', 'mod_livesession'), 3);
    $mine = new html_table();
    $mine->attributes['class'] = 'generaltable';
    $mine->data = [
        [get_string('status', 'mod_livesession'),
            get_string('status:' . $myrecord->status, 'mod_livesession')],
        [get_string('attendedfor', 'mod_livesession'),
            attendance::format_attended((int) $myrecord->duration)],
    ];
    if ($myrecord->firstjoin) {
        $mine->data[] = [get_string('firstjoin', 'mod_livesession'), userdate($myrecord->firstjoin)];
    }
    echo html_writer::table($mine);
}

// Teacher shortcut.
if (has_capability('mod/livesession:viewattendance', $context)) {
    echo html_writer::tag('p', html_writer::link(
        new moodle_url('/mod/livesession/attendance.php', ['id' => $cm->id]),
        get_string('viewattendance', 'mod_livesession'),
        ['class' => 'btn btn-secondary']
    ));
}

echo $OUTPUT->footer();
