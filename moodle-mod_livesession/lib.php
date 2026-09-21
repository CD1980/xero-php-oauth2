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
 * Core module callbacks for mod_livesession.
 *
 * @package    mod_livesession
 * @copyright  2026 Aspire Education and Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_livesession\local\attendance;
use mod_livesession\local\meeting_manager;

/**
 * Declare which optional Moodle features this module supports.
 *
 * @param string $feature FEATURE_xx constant
 * @return mixed true, false, a string, or null when the feature is unknown
 */
function livesession_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
        case FEATURE_SHOW_DESCRIPTION:
        case FEATURE_GRADE_HAS_GRADE:
        case FEATURE_BACKUP_MOODLE2:
        case FEATURE_COMPLETION_TRACKS_VIEWS:
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GROUPS:
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_GRADE_OUTCOMES:
        case FEATURE_ADVANCED_GRADING:
            return false;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_COMMUNICATION;
        default:
            return null;
    }
}

/**
 * Create a new live session and its backing Zoom meeting.
 *
 * @param stdClass $data form data
 * @param mod_livesession_mod_form|null $mform
 * @return int the new instance id
 */
function livesession_add_instance($data, $mform = null) {
    global $DB;

    $data->timecreated = time();
    $data->timemodified = $data->timecreated;
    $data->duration = (int) $data->duration;
    $data->id = $DB->insert_record('livesession', $data);

    $livesession = $DB->get_record('livesession', ['id' => $data->id], '*', MUST_EXIST);
    meeting_manager::sync($livesession);

    livesession_grade_item_update($livesession);
    livesession_update_calendar_event($livesession);

    return $data->id;
}

/**
 * Update an existing live session and push the change to Zoom.
 *
 * @param stdClass $data form data
 * @param mod_livesession_mod_form|null $mform
 * @return bool
 */
function livesession_update_instance($data, $mform = null) {
    global $DB;

    $data->id = $data->instance;
    $data->timemodified = time();
    $data->duration = (int) $data->duration;
    $DB->update_record('livesession', $data);

    $livesession = $DB->get_record('livesession', ['id' => $data->id], '*', MUST_EXIST);
    meeting_manager::sync($livesession);

    livesession_grade_item_update($livesession);
    livesession_update_calendar_event($livesession);
    livesession_recalculate_all($livesession);

    return true;
}

/**
 * Delete a live session, its attendance data and its Zoom meeting.
 *
 * @param int $id instance id
 * @return bool
 */
function livesession_delete_instance($id) {
    global $DB;

    $livesession = $DB->get_record('livesession', ['id' => $id]);
    if (!$livesession) {
        return false;
    }

    meeting_manager::delete($livesession);

    $DB->delete_records('livesession_log', ['livesessionid' => $id]);
    $DB->delete_records('livesession_attendance', ['livesessionid' => $id]);
    $DB->delete_records('event', ['modulename' => 'livesession', 'instance' => $id]);
    $DB->delete_records('livesession', ['id' => $id]);

    livesession_grade_item_delete($livesession);

    return true;
}

/**
 * Create or update the gradebook item, optionally sending grades with it.
 *
 * @param stdClass $livesession
 * @param mixed $grades a single grade object, an array of them, or 'reset'
 * @return int GRADE_UPDATE_OK and friends
 */
function livesession_grade_item_update($livesession, $grades = null) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $params = [
        'itemname' => clean_param($livesession->name, PARAM_NOTAGS),
    ];

    if ((int) $livesession->grade > 0 && (int) $livesession->gradingmethod !== attendance::GRADING_NONE) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = (int) $livesession->grade;
        $params['grademin']  = 0;
    } else {
        $params['gradetype'] = GRADE_TYPE_NONE;
    }

    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    return grade_update(
        'mod/livesession',
        $livesession->course,
        'mod',
        'livesession',
        $livesession->id,
        0,
        $grades,
        $params
    );
}

/**
 * Remove the gradebook item for a session.
 *
 * @param stdClass $livesession
 * @return int
 */
function livesession_grade_item_delete($livesession) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    return grade_update(
        'mod/livesession',
        $livesession->course,
        'mod',
        'livesession',
        $livesession->id,
        0,
        null,
        ['deleted' => 1]
    );
}

/**
 * Push attendance grades into the gradebook.
 *
 * @param stdClass $livesession
 * @param int $userid a single user, or 0 for everyone
 * @param bool $nullifnone write a null grade for users with no attendance record
 * @return void
 */
function livesession_update_grades($livesession, $userid = 0, $nullifnone = true) {
    global $CFG, $DB;
    require_once($CFG->libdir . '/gradelib.php');

    if ((int) $livesession->grade <= 0 || (int) $livesession->gradingmethod === attendance::GRADING_NONE) {
        livesession_grade_item_update($livesession);
        return;
    }

    $conditions = ['livesessionid' => $livesession->id];
    if ($userid) {
        $conditions['userid'] = $userid;
    }
    $records = $DB->get_records('livesession_attendance', $conditions);

    $grades = [];
    foreach ($records as $record) {
        $grades[$record->userid] = (object) [
            'userid'         => $record->userid,
            'rawgrade'       => $record->grade,
            'feedback'       => attendance::build_feedback($livesession, $record),
            'feedbackformat' => FORMAT_HTML,
            'dategraded'     => $record->gradedtime,
        ];
    }

    if ($userid && $nullifnone && !isset($grades[$userid])) {
        $grades[$userid] = (object) ['userid' => $userid, 'rawgrade' => null];
    }

    livesession_grade_item_update($livesession, $grades ?: null);
}

/**
 * Re-derive every attendance record for a session, e.g. after the schedule changed.
 *
 * @param stdClass $livesession
 * @return void
 */
function livesession_recalculate_all($livesession) {
    foreach (attendance::get_records($livesession->id) as $record) {
        attendance::recalculate($livesession, $record);
    }
}

/**
 * Keep the course calendar entry in step with the session schedule.
 *
 * @param stdClass $livesession
 * @return void
 */
function livesession_update_calendar_event($livesession) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/calendar/lib.php');

    $event = $DB->get_record('event', [
        'modulename' => 'livesession',
        'instance'   => $livesession->id,
        'eventtype'  => 'open',
    ]);

    // The raw intro is used rather than format_module_intro(). This function runs during
    // livesession_add_instance(), at which point the course module row exists but its
    // instance column is still zero, so there is no module context to format against yet.
    $data = (object) [
        'name'         => $livesession->name,
        'description'  => $livesession->intro ?? '',
        'format'       => (int) ($livesession->introformat ?? FORMAT_HTML),
        'courseid'     => $livesession->course,
        'groupid'      => 0,
        'userid'       => 0,
        'modulename'   => 'livesession',
        'instance'     => $livesession->id,
        'eventtype'    => 'open',
        'type'         => CALENDAR_EVENT_TYPE_ACTION,
        'timestart'    => $livesession->starttime,
        'timeduration' => $livesession->duration,
        'timesort'     => $livesession->starttime,
        'visible'      => 1,
    ];

    // A calendar problem must never abort the activity being saved. Moodle writes the
    // course module row before calling our add_instance and fills in its instance column
    // from our return value, so throwing here leaves a course module pointing at nothing
    // and every later click on it fails with "Invalid course module ID".
    try {
        if ($event) {
            $calendarevent = calendar_event::load($event->id);
            $calendarevent->update($data, false);
        } else {
            calendar_event::create($data, false);
        }
    } catch (Throwable $e) {
        debugging('mod_livesession: could not update the calendar event for session '
            . $livesession->id . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
    }
}

/**
 * Provide the "Join" action shown against this activity in the timeline block.
 *
 * Required because the calendar event above is a CALENDAR_EVENT_TYPE_ACTION event.
 *
 * @param calendar_event $event
 * @param \core_calendar\action_factory $factory
 * @param int $userid
 * @return \core_calendar\local\event\entities\action_interface|null
 */
function mod_livesession_core_calendar_provide_event_action(
    calendar_event $event,
    \core_calendar\action_factory $factory,
    int $userid = 0
) {
    global $USER, $DB;

    $userid = $userid ?: $USER->id;

    $cm = get_fast_modinfo($event->courseid, $userid)->instances['livesession'][$event->instance] ?? null;
    if (!$cm || !$cm->uservisible) {
        return null;
    }

    $livesession = $DB->get_record('livesession', ['id' => $event->instance]);
    if (!$livesession) {
        return null;
    }

    // Once the join window has closed there is nothing left to act on.
    [, $windowclose] = \mod_livesession\local\meeting_manager::join_window($livesession);
    if (time() > $windowclose) {
        return null;
    }

    return $factory->create_instance(
        get_string('joinsession', 'mod_livesession'),
        new moodle_url('/mod/livesession/view.php', ['id' => $cm->id]),
        1,
        \mod_livesession\local\meeting_manager::is_joinable($livesession)
    );
}

/**
 * Short summary of a user's participation, shown in course reports.
 *
 * @param stdClass $course
 * @param stdClass $user
 * @param cm_info $mod
 * @param stdClass $livesession
 * @return stdClass|null
 */
function livesession_user_outline($course, $user, $mod, $livesession) {
    $record = attendance::get_record($livesession->id, $user->id);
    if ($record === false) {
        return null;
    }

    return (object) [
        'info' => get_string('status:' . $record->status, 'mod_livesession')
            . ' (' . attendance::format_attended((int) $record->duration) . ')',
        'time' => (int) $record->timemodified,
    ];
}

/**
 * Detailed view of a user's participation, shown in course reports.
 *
 * @param stdClass $course
 * @param stdClass $user
 * @param cm_info $mod
 * @param stdClass $livesession
 * @return void
 */
function livesession_user_complete($course, $user, $mod, $livesession) {
    $record = attendance::get_record($livesession->id, $user->id);
    if ($record === false) {
        echo get_string('status:absent', 'mod_livesession');
        return;
    }
    echo attendance::build_feedback($livesession, $record)
        ?: get_string('status:' . $record->status, 'mod_livesession');
}

/**
 * Add the attendance report link to the activity administration menu.
 *
 * @param settings_navigation $settings
 * @param navigation_node $node
 * @return void
 */
function livesession_extend_settings_navigation($settings, $node) {
    global $PAGE;

    if (empty($PAGE->cm) || !has_capability('mod/livesession:viewattendance', $PAGE->cm->context)) {
        return;
    }

    $node->add(
        get_string('attendance', 'mod_livesession'),
        new moodle_url('/mod/livesession/attendance.php', ['id' => $PAGE->cm->id]),
        navigation_node::TYPE_SETTING,
        null,
        'livesessionattendance',
        new pix_icon('i/report', '')
    );
}

/**
 * Supply course-page metadata: activity dates and custom completion rules.
 *
 * @param stdClass $coursemodule
 * @return cached_cm_info|null
 */
function livesession_get_coursemodule_info($coursemodule) {
    global $DB;

    $fields = 'id, name, intro, introformat, starttime, duration, completionattendance';
    $livesession = $DB->get_record('livesession', ['id' => $coursemodule->instance], $fields);
    if (!$livesession) {
        return null;
    }

    $info = new cached_cm_info();
    $info->name = $livesession->name;

    if ($coursemodule->showdescription) {
        $info->content = format_module_intro('livesession', $livesession, $coursemodule->id, false);
    }

    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $info->customdata['customcompletionrules']['completionattendance'] =
            (int) $livesession->completionattendance;
    }

    $info->customdata['starttime'] = (int) $livesession->starttime;
    $info->customdata['duration'] = (int) $livesession->duration;

    return $info;
}

/**
 * Bound the dates a calendar event for this module may be dragged to.
 *
 * A live session can legitimately be rescheduled to any time, so no bound is imposed.
 *
 * @param calendar_event $event
 * @param stdClass $livesession
 * @return array [int|null min, int|null max]
 */
function mod_livesession_core_calendar_get_valid_event_timestart_range(\calendar_event $event, \stdClass $livesession) {
    return [null, null];
}

/**
 * Declare what this module's course reset can clear.
 *
 * @param MoodleQuickForm $mform
 * @return void
 */
function livesession_reset_course_form_definition($mform) {
    $mform->addElement('header', 'livesessionheader', get_string('modulenameplural', 'mod_livesession'));
    $mform->addElement(
        'advcheckbox',
        'reset_livesession_attendance',
        get_string('resetattendance', 'mod_livesession')
    );
}

/**
 * Default the course reset checkboxes to on.
 *
 * @param stdClass $course
 * @return array
 */
function livesession_reset_course_form_defaults($course) {
    return ['reset_livesession_attendance' => 1];
}

/**
 * Clear attendance data as part of a course reset.
 *
 * @param stdClass $data
 * @return array
 */
function livesession_reset_userdata($data) {
    global $DB;

    $status = [];
    if (empty($data->reset_livesession_attendance)) {
        return $status;
    }

    $sessions = $DB->get_records('livesession', ['course' => $data->courseid]);
    foreach ($sessions as $livesession) {
        $DB->delete_records('livesession_log', ['livesessionid' => $livesession->id]);
        $DB->delete_records('livesession_attendance', ['livesessionid' => $livesession->id]);
        livesession_grade_item_update($livesession, 'reset');
    }

    $status[] = [
        'component' => get_string('modulenameplural', 'mod_livesession'),
        'item'      => get_string('resetattendance', 'mod_livesession'),
        'error'     => false,
    ];

    return $status;
}
