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

        // --- Schedule -------------------------------------------------------

        $mform->addElement('header', 'scheduleheader', get_string('schedule', 'mod_livesession'));
        $mform->setExpanded('scheduleheader');

        $mform->addElement('date_time_selector', 'starttime', get_string('starttime', 'mod_livesession'));
        $mform->setDefault('starttime', time() + HOURSECS);
        $mform->addHelpButton('starttime', 'starttime', 'mod_livesession');

        $mform->addElement('duration', 'duration', get_string('duration', 'mod_livesession'),
            ['optional' => false, 'defaultunit' => MINSECS]);
        $mform->setDefault('duration', (int) get_config('mod_livesession', 'defaultduration') ?: HOURSECS);
        $mform->addHelpButton('duration', 'duration', 'mod_livesession');

        $mform->addElement('duration', 'joinwindowbefore', get_string('joinwindowbefore', 'mod_livesession'),
            ['optional' => false, 'defaultunit' => MINSECS]);
        $mform->setDefault('joinwindowbefore', 15 * MINSECS);
        $mform->addHelpButton('joinwindowbefore', 'joinwindowbefore', 'mod_livesession');

        $mform->addElement('duration', 'joinwindowafter', get_string('joinwindowafter', 'mod_livesession'),
            ['optional' => false, 'defaultunit' => MINSECS]);
        $mform->setDefault('joinwindowafter', 15 * MINSECS);
        $mform->addHelpButton('joinwindowafter', 'joinwindowafter', 'mod_livesession');

        // --- Meeting options ------------------------------------------------

        $mform->addElement('header', 'meetingheader', get_string('meetingoptions', 'mod_livesession'));

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

        // --- Attendance -----------------------------------------------------

        $mform->addElement('header', 'attendanceheader', get_string('attendanceandgrading', 'mod_livesession'));
        $mform->setExpanded('attendanceheader');

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
        $mform->addHelpButton('requiredpercent', 'requiredpercent', 'mod_livesession');

        $mform->addElement('text', 'requiredminutes', get_string('requiredminutes', 'mod_livesession'), ['size' => 5]);
        $mform->setType('requiredminutes', PARAM_INT);
        $mform->setDefault('requiredminutes', 0);
        $mform->hideIf('requiredminutes', 'gradingmethod', 'eq', attendance::GRADING_NONE);
        $mform->addHelpButton('requiredminutes', 'requiredminutes', 'mod_livesession');

        $mform->addElement('duration', 'latethreshold', get_string('latethreshold', 'mod_livesession'),
            ['optional' => false, 'defaultunit' => MINSECS]);
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
            $mform->createElement('checkbox', 'completionattendanceenabled', '',
                get_string('completionattendance', 'mod_livesession')),
            $mform->createElement('text', 'completionattendance', '', ['size' => 4]),
        ];
        $mform->setType('completionattendance', PARAM_INT);
        $mform->addGroup($group, 'completionattendancegroup',
            get_string('completionattendancegroup', 'mod_livesession'), [' '], false);
        $mform->hideIf('completionattendance', 'completionattendanceenabled', 'notchecked');
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
        $minutes = (int) ($defaultvalues['completionattendance'] ?? 0);
        $defaultvalues['completionattendanceenabled'] = $minutes > 0 ? 1 : 0;
        if ($minutes <= 0) {
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
            }
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

        $requiredseconds = (int) ($data['requiredminutes'] ?? 0) * MINSECS;
        if ($requiredseconds > (int) $data['duration']) {
            $errors['requiredminutes'] = get_string('error:requiredexceedsduration', 'mod_livesession');
        }

        return $errors;
    }
}
