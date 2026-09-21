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
 * Manually correct one participant's attendance record.
 *
 * @package    mod_livesession
 * @copyright  2026 Aspire Education and Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use mod_livesession\local\attendance;
use mod_livesession\form\override_form;

$id = required_param('id', PARAM_INT);
$userid = required_param('userid', PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'livesession');
require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/livesession:manageattendance', $context);

$livesession = $DB->get_record('livesession', ['id' => $cm->instance], '*', MUST_EXIST);
$user = core_user::get_user($userid, '*', MUST_EXIST);

// Only someone enrolled here can have an attendance record here.
if (!is_enrolled($context, $user, 'mod/livesession:join')) {
    throw new moodle_exception('error:usernotenrolled', 'mod_livesession');
}

$returnurl = new moodle_url('/mod/livesession/attendance.php', ['id' => $cm->id]);
$pageurl = new moodle_url('/mod/livesession/override.php', ['id' => $cm->id, 'userid' => $userid]);

$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_title(get_string('editattendance', 'mod_livesession'));
$PAGE->set_heading(format_string($course->fullname));

$record = attendance::get_record((int) $livesession->id, $userid);

$form = new override_form($pageurl, ['fullname' => fullname($user)]);
$form->set_data([
    'id'              => $cm->id,
    'userid'          => $userid,
    'status'          => $record->status ?? attendance::STATUS_ABSENT,
    'durationminutes' => $record ? (int) round($record->duration / MINSECS) : 0,
    'remarks'         => $record->remarks ?? '',
    'clearoverride'   => 0,
]);

if ($form->is_cancelled()) {
    redirect($returnurl);
}

if ($data = $form->get_data()) {
    if (!empty($data->clearoverride)) {
        attendance::clear_override($livesession, $userid);
    } else {
        attendance::override(
            $livesession,
            $userid,
            $data->status,
            (int) $data->durationminutes,
            (string) $data->remarks
        );
    }
    redirect(
        $returnurl,
        get_string('attendancesaved', 'mod_livesession'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('editattendance', 'mod_livesession'));
$form->display();
echo $OUTPUT->footer();
