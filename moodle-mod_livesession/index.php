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
 * Lists every live session in a course.
 *
 * @package    mod_livesession
 * @copyright  2026 Aspire Education and Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use mod_livesession\local\attendance;

$id = required_param('id', PARAM_INT);

$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
require_login($course);

$context = context_course::instance($course->id);

$PAGE->set_url('/mod/livesession/index.php', ['id' => $id]);
$PAGE->set_title(format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$event = \core\event\course_module_instance_list_viewed::create(['context' => $context]);
$event->add_record_snapshot('course', $course);
$event->trigger();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'mod_livesession'));

$instances = get_all_instances_in_course('livesession', $course);
if (!$instances) {
    echo $OUTPUT->notification(get_string('nosessions', 'mod_livesession'), 'info');
    echo $OUTPUT->footer();
    exit;
}

// Upcoming sessions first is what a student wants; the schedule is the point of the list.
usort($instances, fn($a, $b) => $a->starttime <=> $b->starttime);

$table = new html_table();
$table->attributes['class'] = 'generaltable';
$table->head = [
    get_string('sessionname', 'mod_livesession'),
    get_string('starts', 'mod_livesession'),
    get_string('duration', 'mod_livesession'),
    get_string('yourattendance', 'mod_livesession'),
];

foreach ($instances as $instance) {
    $link = html_writer::link(
        new moodle_url('/mod/livesession/view.php', ['id' => $instance->coursemodule]),
        format_string($instance->name),
        $instance->visible ? [] : ['class' => 'dimmed']
    );

    $record = attendance::get_record((int) $instance->id, (int) $USER->id);
    $mine = $record
        ? get_string('status:' . $record->status, 'mod_livesession')
            . ' (' . format_time((int) $record->duration) . ')'
        : get_string('status:absent', 'mod_livesession');

    $table->data[] = [
        $link,
        userdate($instance->starttime),
        format_time((int) $instance->duration),
        $mine,
    ];
}

echo html_writer::table($table);
echo $OUTPUT->footer();
