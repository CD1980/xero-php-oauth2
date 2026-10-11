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
 * Admin settings and report page registration.
 *
 * @package    local_qualtracker
 * @copyright  2026 Aspire Education and Training
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// The tracker page lives under Site administration > Reports so anyone with the capability can find it.
$ADMIN->add('reports', new admin_externalpage(
    'local_qualtracker',
    new lang_string('pluginname', 'local_qualtracker'),
    new moodle_url('/local/qualtracker/index.php'),
    'local/qualtracker:view'
));

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_qualtracker_settings', new lang_string('settings', 'local_qualtracker'));

    $settings->add(new admin_setting_configtextarea(
        'local_qualtracker/flags',
        new lang_string('flags', 'local_qualtracker'),
        new lang_string('flags_desc', 'local_qualtracker'),
        'qualification'
    ));

    $settings->add(new admin_setting_configselect(
        'local_qualtracker/linkmode',
        new lang_string('linkmode', 'local_qualtracker'),
        new lang_string('linkmode_desc', 'local_qualtracker'),
        'category',
        [
            'category' => new lang_string('linkmode_category', 'local_qualtracker'),
            'categorytree' => new lang_string('linkmode_categorytree', 'local_qualtracker'),
            'field' => new lang_string('linkmode_field', 'local_qualtracker'),
        ]
    ));

    $settings->add(new admin_setting_configtext(
        'local_qualtracker/linkfield',
        new lang_string('linkfield', 'local_qualtracker'),
        new lang_string('linkfield_desc', 'local_qualtracker'),
        'qualificationcode',
        PARAM_ALPHANUMEXT
    ));

    $ADMIN->add('localplugins', $settings);
}
