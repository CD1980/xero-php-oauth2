<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * English strings for the qualification tracker.
 *
 * @package    local_qualtracker
 * @copyright  2026 Aspire Education and Training
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['allstatuses'] = 'All statuses';
$string['breakdown'] = '{$a->inprogress} in progress, {$a->notstarted} not started';
$string['completed'] = 'completed';
$string['configure'] = 'Tracker settings';
$string['enrolled'] = 'enrolled';
$string['flags'] = 'Program flag fields';
$string['flags_desc'] = 'Course custom fields that mark a course as a qualification or funded program, one per line. Use just the field shortname (e.g. <code>qualification</code>) to match a ticked checkbox or any non-empty value, or <code>shortname=value</code> (e.g. <code>coursetype=Qualification</code>) to match a specific value or menu option. A course matching any line is listed as a program.';
$string['linkfield'] = 'Unit link field';
$string['linkfield_desc'] = 'Only used when units are linked by custom field: the shortname of the course custom field on each unit course that holds the qualification course\'s shortname, ID number or course id. Several qualifications can be separated by commas.';
$string['linkmode'] = 'How units are found';
$string['linkmode_category'] = 'Other courses in the same category as the qualification course';
$string['linkmode_categorytree'] = 'Other courses in the qualification course\'s category and its sub-categories';
$string['linkmode_desc'] = 'Students are those enrolled in the flagged qualification course. Their progress is measured across the unit courses found this way. Other flagged program courses are never counted as units.';
$string['linkmode_field'] = 'Courses whose unit link field names the qualification';
$string['noprograms'] = '<p>No qualification or funded program courses were found.</p><p>Programs are courses flagged by the course custom fields listed in the tracker settings.</p>';
$string['nostudents'] = 'No students are enrolled in this program.';
$string['nounits'] = 'No unit courses were found for this program, so progress is based on completion of the qualification course itself.';
$string['pluginname'] = 'Qualification tracker';
$string['privacy:metadata'] = 'The qualification tracker only displays existing enrolment and completion data and stores no personal data.';
$string['progress'] = 'Progress';
$string['programs'] = 'programs';
$string['qualtracker:view'] = 'View the qualification tracker';
$string['searchstudents'] = 'Search students';
$string['settings'] = 'Qualification tracker settings';
$string['status_completed'] = 'Completed';
$string['status_inprogress'] = 'In progress';
$string['status_notstarted'] = 'Not started';
$string['student'] = 'Student';
$string['unit_complete'] = 'Completed';
$string['unit_inprogress'] = 'In progress';
$string['unit_notenrolled'] = 'Not enrolled';
$string['unit_notstarted'] = 'Not started';
$string['unitcount'] = '{$a} unit course(s)';
$string['unitsdone'] = '{$a->done} / {$a->total} units';
$string['viewcourse'] = 'Open qualification course';
$string['working'] = 'still working';
