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
 * Activity settings form for mod_livesession.
 *
 * @package    mod_livesession
 * @copyright  2026 Aspire Education and Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use mod_livesession\local\attendance;
use mod_livesession\local\meeting_manager;

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Lets a teacher schedule a live session and set how attendance becomes a grade.
 *
 * @package    mod_livesession
 * @copyright  2026 Aspire Education and Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_livesession_mod_form extends moodleform_mod {
    /**
     * Build the form.
     *
     * @return void
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('sessionname', 'mod_livesession'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements(get_string('sessiondescription', 'mod_livesession'));

        // Schedule.
        $mform->addElement('header', 'scheduleheader', get_string('schedule', 'mod_livesession'));
        $mform->setExpanded('scheduleheader');

        $mform->addElement('date_time_selector', 'starttime', get_string('starttime', 'mod_livesession'));
        $mform->setDefault('starttime', time() + HOURSECS);
        $mform->addHelpButton('starttime', 'starttime', 'mod_livesession');

        $mform->addElement(
            'duration',
            'duration',
            get_string('duration', 'mod_livesession'),
            ['optional' => false, 'defaultunit' => MINSECS]
        );
        $mform->setDefault('duration', (int) get_config('mod_livesession', 'defaultduration') ?: HOURSECS);
        $mform->addHelpButton('duration', 'duration', 'mod_livesession');

        $mform->addElement(
            'duration',
            'joinwindowbefore',
            get_string('joinwindowbefore', 'mod_livesession'),
            ['optional' => false, 'defaultunit' => MINSECS]
        );
        $mform->setDefault('joinwindowbefore', 15 * MINSECS);
        $mform->addHelpButton('joinwindowbefore', 'joinwindowbefore', 'mod_livesession');

        $mform->addElement(
            'duration',
            'joinwindowafter',
            get_string('joinwindowafter', 'mod_livesession'),
            ['optional' => false, 'defaultunit' => MINSECS]
        );
        $mform->setDefault('joinwindowafter', 15 * MINSECS);
        $mform->addHelpButton('joinwindowafter', 'joinwindowafter', 'mod_livesession');

        // Meeting options.
        $mform->addElement('header', 'meetingheader', get_string('meetingoptions', 'mod_livesession'));

        $mform->addElement(
            'select',
            'mastersessionid',
            get_string('mastersessionid', 'mod_livesession'),
            meeting_manager::master_menu((int) ($this->_instance ?? 0))
        );
        $mform->setDefault('mastersessionid', 0);
        $mform->addHelpButton('mastersessionid', 'mastersessionid', 'mod_livesession');

        $mform->addElement('advcheckbox', 'ismaster', get_string('ismaster', 'mod_livesession'));
        $mform->setDefault('ismaster', 0);
        $mform->addHelpButton('ismaster', 'ismaster', 'mod_livesession');

        // A session either owns a room or borrows one; it cannot do both, and the
        // meeting's own settings belong to whoever owns it.
        $mform->hideIf('ismaster', 'mastersessionid', 'neq', 0);
        $mform->hideIf('mastersessionid', 'ismaster', 'checked');

        $mform->addElement('text', 'zoomhostid', get_string('zoomhostid', 'mod_livesession'), ['size' => 48]);
        $mform->setType('zoomhostid', PARAM_RAW_TRIMMED);
        $mform->setDefault('zoomhostid', (string) get_config('mod_livesession', 'defaulthost'));
        $mform->addHelpButton('zoomhostid', 'zoomhostid', 'mod_livesession');

        $mform->addElement('advcheckbox', 'waitingroom', get_string('waitingroom', 'mod_livesession'));
        $mform->setDefault('waitingroom', 0);
        $mform->addHelpButton('waitingroom', 'waitingroom', 'mod_livesession');

        $mform->addElement('advcheckbox', 'joinbeforehost', get_string('joinbeforehost', 'mod_livesession'));
        $mform->setDefault('joinbeforehost', 1);
        $mform->disabledIf('joinbeforehost', 'waitingroom', 'checked');

        $mform->addElement('advcheckbox', 'muteonentry', get_string('muteonentry', 'mod_livesession'));
        $mform->setDefault('muteonentry', 1);

        $mform->addElement('select', 'autorecord', get_string('autorecord', 'mod_livesession'), [
            'none'  => get_string('autorecord:none', 'mod_livesession'),
            'local' => get_string('autorecord:local', 'mod_livesession'),
            'cloud' => get_string('autorecord:cloud', 'mod_livesession'),
        ]);
        $mform->setDefault('autorecord', 'none');

        foreach (['zoomhostid', 'waitingroom', 'joinbeforehost', 'muteonentry', 'autorecord'] as $meetingsetting) {
            $mform->hideIf($meetingsetting, 'mastersessionid', 'neq', 0);
        }

        // Attendance.
        $mform->addElement('header', 'attendanceheader', get_string('attendanceandgrading', 'mod_livesession'));
        $mform->setExpanded('attendanceheader');

        $mform->addElement('select', 'attendancemode', get_string('attendancemode', 'mod_livesession'), [
            attendance::MODE_PRESENCE => get_string('attendancemode:presence', 'mod_livesession'),
            attendance::MODE_DURATION => get_string('attendancemode:duration', 'mod_livesession'),
        ]);
        $mform->setDefault('attendancemode', attendance::MODE_PRESENCE);
        $mform->addHelpButton('attendancemode', 'attendancemode', 'mod_livesession');

        $mform->addElement('select', 'gradingmethod', get_string('gradingmethod', 'mod_livesession'), [
            attendance::GRADING_NONE         => get_string('gradingmethod:none', 'mod_livesession'),
            attendance::GRADING_THRESHOLD    => get_string('gradingmethod:threshold', 'mod_livesession'),
            attendance::GRADING_PROPORTIONAL => get_string('gradingmethod:proportional', 'mod_livesession'),
        ]);
        $mform->setDefault('gradingmethod', attendance::GRADING_THRESHOLD);
        $mform->addHelpButton('gradingmethod', 'gradingmethod', 'mod_livesession');

        $mform->addElement('text', 'requiredpercent', get_string('requiredpercent', 'mod_livesession'), ['size' => 5]);
        $mform->setType('requiredpercent', PARAM_INT);
        $mform->setDefault('requiredpercent', 80);
        $mform->hideIf('requiredpercent', 'gradingmethod', 'eq', attendance::GRADING_NONE);
        $mform->hideIf('requiredpercent', 'attendancemode', 'eq', attendance::MODE_PRESENCE);
        $mform->addHelpButton('requiredpercent', 'requiredpercent', 'mod_livesession');

        $mform->addElement('text', 'requiredminutes', get_string('requiredminutes', 'mod_livesession'), ['size' => 5]);
        $mform->setType('requiredminutes', PARAM_INT);
        $mform->setDefault('requiredminutes', 0);
        $mform->hideIf('requiredminutes', 'gradingmethod', 'eq', attendance::GRADING_NONE);
        $mform->hideIf('requiredminutes', 'attendancemode', 'eq', attendance::MODE_PRESENCE);
        $mform->addHelpButton('requiredminutes', 'requiredminutes', 'mod_livesession');

        $mform->addElement(
            'duration',
            'latethreshold',
            get_string('latethreshold', 'mod_livesession'),
            ['optional' => false, 'defaultunit' => MINSECS]
        );
        $mform->setDefault('latethreshold', 5 * MINSECS);
        $mform->addHelpButton('latethreshold', 'latethreshold', 'mod_livesession');

        $mform->addElement('advcheckbox', 'recordip', get_string('recordip', 'mod_livesession'));
        $mform->setDefault('recordip', (int) get_config('mod_livesession', 'recordip'));
        $mform->addHelpButton('recordip', 'recordip', 'mod_livesession');

        $mform->addElement('advcheckbox', 'ipinfeedback', get_string('ipinfeedback', 'mod_livesession'));
        $mform->setDefault('ipinfeedback', (int) get_config('mod_livesession', 'ipinfeedback'));
        $mform->hideIf('ipinfeedback', 'recordip', 'notchecked');
        $mform->addHelpButton('ipinfeedback', 'ipinfeedback', 'mod_livesession');

        $this->standard_grading_coursemodule_elements();
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Add the attendance-based completion rule.
     *
     * @return array names of the added form elements
     */
    public function add_completion_rules() {
        $mform = $this->_form;

        $group = [
            $mform->createElement(
                'checkbox',
                'completionattendanceenabled',
                '',
                get_string('completionattendance', 'mod_livesession')
            ),
            $mform->createElement('text', 'completionattendance', '', ['size' => 4]),
        ];
        $mform->setType('completionattendance', PARAM_INT);
        $mform->addGroup(
            $group,
            'completionattendancegroup',
            get_string('completionattendancegroup', 'mod_livesession'),
            [' '],
            false
        );
        $mform->hideIf('completionattendance', 'completionattendanceenabled', 'notchecked');
        $mform->hideIf('completionattendance', 'attendancemode', 'eq', attendance::MODE_PRESENCE);
        $mform->addHelpButton('completionattendancegroup', 'completionattendancegroup', 'mod_livesession');

        return ['completionattendancegroup'];
    }

    /**
     * Whether the custom completion rule is switched on.
     *
     * @param array $data submitted form data
     * @return bool
     */
    public function completion_rule_enabled($data) {
        return !empty($data['completionattendanceenabled']) && (int) $data['completionattendance'] > 0;
    }

    /**
     * Turn the stored minute count back into the checkbox plus value pair.
     *
     * @param array $defaultvalues
     * @return void
     */
    public function data_preprocessing(&$defaultvalues) {
        $stored = (int) ($defaultvalues['completionattendance'] ?? 0);
        $defaultvalues['completionattendanceenabled'] = $stored > 0 ? 1 : 0;

        // In present-on-join mode the stored value is the flag 1, not a minute count, so
        // showing it in the minutes box would offer "1 minute" to anyone who switched
        // the session back to timed attendance.
        $presence = (int) ($defaultvalues['attendancemode'] ?? attendance::MODE_PRESENCE)
            === attendance::MODE_PRESENCE;
        if ($stored <= 0 || $presence) {
            $defaultvalues['completionattendance'] = 15;
        }
    }

    /**
     * Collapse the completion checkbox back into the stored minute count.
     *
     * @return object|null
     */
    public function get_data() {
        $data = parent::get_data();
        if (!$data) {
            return $data;
        }
        if (!empty($data->completionunlocked)) {
            $autocompletion = !empty($data->completion) && $data->completion == COMPLETION_TRACKING_AUTOMATIC;
            if (empty($data->completionattendanceenabled) || !$autocompletion) {
                $data->completionattendance = 0;
            } else if ((int) $data->attendancemode === attendance::MODE_PRESENCE) {
                // The minutes box is hidden in this mode, so it carries no useful value.
                // Store 1 to mean "the rule is on"; custom_completion reads it as
                // "must have joined" rather than as a number of minutes.
                $data->completionattendance = 1;
            }
        }

        // A session that lends its room cannot also borrow one, and vice versa. The form
        // hides whichever is irrelevant, but a hidden element still submits its value.
        if (!empty($data->mastersessionid)) {
            $data->ismaster = 0;
        }

        return $data;
    }

    /**
     * Validate the schedule and attendance settings.
     *
     * @param array $data
     * @param array $files
     * @return array errors keyed by element name
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if ((int) $data['duration'] < MINSECS) {
            $errors['duration'] = get_string('error:durationtooshort', 'mod_livesession');
        }

        if ((int) ($data['grade'] ?? 0) < 0) {
            // A negative grade id means a scale was chosen. Attendance produces a numeric
            // proportion, which cannot be mapped onto an arbitrary scale meaningfully.
            $errors['grade'] = get_string('error:scalesnotsupported', 'mod_livesession');
        }

        $percent = (int) ($data['requiredpercent'] ?? 0);
        if ($percent < 0 || $percent > 100) {
            $errors['requiredpercent'] = get_string('error:percentrange', 'mod_livesession');
        }

        if ((int) ($data['requiredminutes'] ?? 0) < 0) {
            $errors['requiredminutes'] = get_string('error:negativeminutes', 'mod_livesession');
        }

        $presence = (int) ($data['attendancemode'] ?? attendance::MODE_PRESENCE) === attendance::MODE_PRESENCE;

        $requiredseconds = (int) ($data['requiredminutes'] ?? 0) * MINSECS;
        if (!$presence && $requiredseconds > (int) $data['duration']) {
            $errors['requiredminutes'] = get_string('error:requiredexceedsduration', 'mod_livesession');
        }

        // Proportional marking apportions a grade across the time attended, and
        // present-on-join measures no time to apportion.
        if ($presence && (int) ($data['gradingmethod'] ?? 0) === attendance::GRADING_PROPORTIONAL) {
            $errors['gradingmethod'] = get_string('error:proportionalneedstime', 'mod_livesession');
        }

        $errors += $this->validate_shared_room($data);

        return $errors;
    }

    /**
     * Check the shared-room choices against what actually exists.
     *
     * @param array $data submitted form data
     * @return array errors keyed by element name
     */
    protected function validate_shared_room(array $data): array {
        global $DB;

        $errors = [];
        $masterid = (int) ($data['mastersessionid'] ?? 0);
        $instanceid = (int) ($this->_instance ?? 0);

        // Choosing a room to borrow settles the question: this session cannot also lend
        // one. The lending checkbox is hidden at that point but still submits whatever it
        // held, so it is resolved here exactly as get_data() resolves it - otherwise a
        // session that lends a room today could be turned into a borrower tomorrow while
        // the stale checkbox hides the fact that its children are about to be cut off.
        $lendsaroom = !$masterid && !empty($data['ismaster']);

        // Re-check against the menu rather than the table: the menu is what applies the
        // capability test, so a hand-posted id cannot reach a course the user could not
        // schedule a session in.
        if ($masterid && !array_key_exists($masterid, meeting_manager::master_menu($instanceid))) {
            $errors['mastersessionid'] = get_string('error:masterunavailable', 'mod_livesession');
        }

        // Withdrawing a room that other sessions are relying on would cut them off with
        // no warning, so it has to be undone from those sessions first.
        if ($instanceid && !$lendsaroom) {
            $children = $DB->count_records('livesession', ['mastersessionid' => $instanceid]);
            if ($children > 0) {
                // The message hangs off whichever control the teacher just used, so it
                // lands somewhere they can see rather than on the hidden checkbox.
                $field = $masterid ? 'mastersessionid' : 'ismaster';
                $errors[$field] = get_string('error:masterhaschildren', 'mod_livesession', $children);
            }
        }

        return $errors;
    }
}
