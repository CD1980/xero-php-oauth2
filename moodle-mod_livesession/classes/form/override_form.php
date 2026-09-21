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

namespace mod_livesession\form;

use mod_livesession\local\attendance;
use moodleform;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Lets a teacher correct an automatically captured attendance record.
 *
 * @package    mod_livesession
 * @copyright  2026 Aspire Education and Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class override_form extends moodleform {

    /**
     * Build the form.
     *
     * @return void
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'userid');
        $mform->setType('userid', PARAM_INT);

        $mform->addElement('static', 'participant', get_string('fullname'),
            $this->_customdata['fullname']);

        $mform->addElement('select', 'status', get_string('status', 'mod_livesession'), [
            attendance::STATUS_PRESENT => get_string('status:present', 'mod_livesession'),
            attendance::STATUS_LATE    => get_string('status:late', 'mod_livesession'),
            attendance::STATUS_PARTIAL => get_string('status:partial', 'mod_livesession'),
            attendance::STATUS_ABSENT  => get_string('status:absent', 'mod_livesession'),
            attendance::STATUS_EXCUSED => get_string('status:excused', 'mod_livesession'),
        ]);

        $mform->addElement('text', 'durationminutes',
            get_string('attendedminutes', 'mod_livesession'), ['size' => 5]);
        $mform->setType('durationminutes', PARAM_INT);
        $mform->addHelpButton('durationminutes', 'attendedminutes', 'mod_livesession');

        $mform->addElement('textarea', 'remarks', get_string('remarks', 'mod_livesession'),
            ['rows' => 3, 'cols' => 60]);
        $mform->setType('remarks', PARAM_TEXT);

        $mform->addElement('advcheckbox', 'clearoverride',
            get_string('clearoverride', 'mod_livesession'));
        $mform->addHelpButton('clearoverride', 'clearoverride', 'mod_livesession');

        $this->add_action_buttons();
    }

    /**
     * Validate the submitted correction.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if ((int) $data['durationminutes'] < 0) {
            $errors['durationminutes'] = get_string('error:negativeminutes', 'mod_livesession');
        }
        return $errors;
    }
}
