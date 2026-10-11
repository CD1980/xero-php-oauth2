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
 * Qualification tracker: overview of every qualification / funded program and its students.
 *
 * @package    local_qualtracker
 * @copyright  2026 Aspire Education and Training
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$openid = optional_param('program', 0, PARAM_INT);

admin_externalpage_setup('local_qualtracker', '', ['program' => $openid]);
require_capability('local/qualtracker:view', context_system::instance());

$PAGE->set_title(get_string('pluginname', 'local_qualtracker'));
$PAGE->set_heading(get_string('pluginname', 'local_qualtracker'));

$tracker = new \local_qualtracker\tracker();
$programs = [];
$totals = ['enrolled' => 0, 'completed' => 0, 'working' => 0];
foreach ($tracker->build_report() as $program) {
    $exported = \local_qualtracker\tracker::export_program($program, $openid == $program->course->id);
    $totals['enrolled'] += $exported['enrolled'];
    $totals['completed'] += $exported['completed'];
    $totals['working'] += $exported['working'];
    $programs[] = $exported;
}

$context = [
    'programs' => $programs,
    'hasprograms' => !empty($programs),
    'programcount' => count($programs),
    'totals' => $totals,
    'settingsurl' => has_capability('moodle/site:config', context_system::instance())
        ? (new moodle_url('/admin/settings.php', ['section' => 'local_qualtracker_settings']))->out(false) : null,
];

// Plain JS so the plugin needs no AMD build step: filter the student rows of a program by name or status.
$PAGE->requires->js_init_code(<<<'JS'
document.querySelectorAll('.local-qualtracker-filter').forEach(function(form) {
    var table = document.getElementById(form.dataset.table);
    var apply = function() {
        var text = form.querySelector('[name=q]').value.trim().toLowerCase();
        var status = form.querySelector('[name=status]').value;
        table.querySelectorAll('tbody tr').forEach(function(row) {
            var show = (!text || row.dataset.name.indexOf(text) !== -1) && (!status || row.dataset.status === status);
            row.hidden = !show;
        });
    };
    form.addEventListener('input', apply);
    form.addEventListener('change', apply);
    form.addEventListener('submit', function(e) { e.preventDefault(); });
});
JS
);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_qualtracker/overview', $context);
echo $OUTPUT->footer();
