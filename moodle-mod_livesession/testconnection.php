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
 * Checks the configured Zoom credentials against Zoom, live.
 *
 * The activity page can only show the error recorded the last time an activity was
 * saved, which is easily mistaken for a current one. This page talks to Zoom now.
 *
 * @package    mod_livesession
 * @copyright  2026 Aspire Education and Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use mod_livesession\local\zoom\client;
use mod_livesession\local\zoom\signature;

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$run = optional_param('run', 0, PARAM_BOOL);

$pageurl = new moodle_url('/mod/livesession/testconnection.php');
$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('test:title', 'mod_livesession'));
$PAGE->set_heading(get_string('test:title', 'mod_livesession'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('test:title', 'mod_livesession'));

// Which build is actually running. If this does not match the ZIP that was installed,
// the upgrade has not been applied and nothing below is meaningful.
$pluginfo = core_plugin_manager::instance()->get_plugin_info('mod_livesession');
$versiontable = new html_table();
$versiontable->attributes['class'] = 'generaltable';
$versiontable->data = [
    [get_string('test:running', 'mod_livesession'),
        html_writer::tag('strong', s($pluginfo->release ?? '?') . ' (' . (int) ($pluginfo->versiondisk ?? 0) . ')')],
    [get_string('test:installed', 'mod_livesession'), (int) ($pluginfo->versiondb ?? 0)],
];
echo html_writer::table($versiontable);

if ((int) ($pluginfo->versiondisk ?? 0) !== (int) ($pluginfo->versiondb ?? 0)) {
    echo $OUTPUT->notification(get_string('test:upgradepending', 'mod_livesession'), 'warning');
}

// What is configured, without ever printing a secret.
$mask = function (string $value): string {
    $value = trim($value);
    if ($value === '') {
        return html_writer::tag(
            'span',
            get_string('test:notset', 'mod_livesession'),
            ['class' => 'badge bg-danger']
        );
    }
    return html_writer::tag('code', str_repeat('*', max(0, strlen($value) - 4))
        . substr($value, -4)) . ' (' . strlen($value) . ')';
};

$conf = new html_table();
$conf->attributes['class'] = 'generaltable';
$conf->head = [get_string('test:setting', 'mod_livesession'), get_string('test:value', 'mod_livesession')];
foreach (['accountid', 'clientid', 'clientsecret', 'sdkclientid', 'sdkclientsecret'] as $key) {
    $conf->data[] = [
        get_string('settings:' . $key, 'mod_livesession'),
        $mask((string) get_config('mod_livesession', $key)),
    ];
}
$defaulthost = trim((string) get_config('mod_livesession', 'defaulthost'));
$conf->data[] = [get_string('settings:defaulthost', 'mod_livesession'),
    $defaulthost === '' ? get_string('test:notset', 'mod_livesession') : s($defaulthost)];
echo $OUTPUT->heading(get_string('test:configuration', 'mod_livesession'), 3);
echo html_writer::table($conf);

echo $OUTPUT->heading(get_string('test:result', 'mod_livesession'), 3);

if (!$run) {
    echo html_writer::tag('p', get_string('test:intro', 'mod_livesession'));
    echo html_writer::tag('p', html_writer::link(
        new moodle_url($pageurl, ['run' => 1, 'sesskey' => sesskey()]),
        get_string('test:run', 'mod_livesession'),
        ['class' => 'btn btn-primary']
    ));
} else {
    require_sesskey();

    if (!client::is_configured()) {
        echo $OUTPUT->notification(get_string('error:notconfigured', 'mod_livesession'), 'error');
    } else {
        $steps = client::instance()->test_connection($defaulthost);

        $results = new html_table();
        $results->attributes['class'] = 'generaltable';
        $results->head = [
            get_string('test:step', 'mod_livesession'),
            get_string('test:outcome', 'mod_livesession'),
            get_string('test:detail', 'mod_livesession'),
        ];
        $allok = true;
        foreach ($steps as $step) {
            $allok = $allok && $step['ok'];
            $results->data[] = [
                s($step['step']),
                html_writer::tag(
                    'span',
                    $step['ok'] ? get_string('test:pass', 'mod_livesession')
                                : get_string('test:fail', 'mod_livesession'),
                    ['class' => 'badge ' . ($step['ok'] ? 'bg-success' : 'bg-danger')]
                ),
                s($step['detail']),
            ];
        }
        echo html_writer::table($results);

        if ($allok) {
            echo $OUTPUT->notification(get_string('test:allpassed', 'mod_livesession'), 'success');
        }
    }

    if (!signature::is_configured()) {
        echo $OUTPUT->notification(get_string('error:sdknotconfigured', 'mod_livesession'), 'warning');
    }

    echo html_writer::tag('p', html_writer::link(
        new moodle_url($pageurl, ['run' => 1, 'sesskey' => sesskey()]),
        get_string('test:runagain', 'mod_livesession'),
        ['class' => 'btn btn-secondary']
    ));
}

echo html_writer::tag('p', html_writer::link(
    new moodle_url('/admin/settings.php', ['section' => 'modsettinglivesession']),
    get_string('test:backtosettings', 'mod_livesession')
));

echo $OUTPUT->footer();
