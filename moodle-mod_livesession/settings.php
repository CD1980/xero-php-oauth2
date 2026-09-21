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
 * Site administration settings for mod_livesession.
 *
 * @package    mod_livesession
 * @copyright  2026 Aspire Education and Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_heading(
        'mod_livesession/s2sheading',
        get_string('settings:s2sheading', 'mod_livesession'),
        get_string('settings:s2sheading_desc', 'mod_livesession')
    ));

    $settings->add(new admin_setting_configtext(
        'mod_livesession/accountid',
        get_string('settings:accountid', 'mod_livesession'),
        get_string('settings:accountid_desc', 'mod_livesession'),
        '',
        PARAM_ALPHANUMEXT
    ));

    $settings->add(new admin_setting_configtext(
        'mod_livesession/clientid',
        get_string('settings:clientid', 'mod_livesession'),
        get_string('settings:clientid_desc', 'mod_livesession'),
        '',
        PARAM_RAW_TRIMMED
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'mod_livesession/clientsecret',
        get_string('settings:clientsecret', 'mod_livesession'),
        get_string('settings:clientsecret_desc', 'mod_livesession'),
        ''
    ));

    $settings->add(new admin_setting_heading(
        'mod_livesession/sdkheading',
        get_string('settings:sdkheading', 'mod_livesession'),
        get_string('settings:sdkheading_desc', 'mod_livesession')
    ));

    $settings->add(new admin_setting_configtext(
        'mod_livesession/sdkclientid',
        get_string('settings:sdkclientid', 'mod_livesession'),
        get_string('settings:sdkclientid_desc', 'mod_livesession'),
        '',
        PARAM_RAW_TRIMMED
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'mod_livesession/sdkclientsecret',
        get_string('settings:sdkclientsecret', 'mod_livesession'),
        get_string('settings:sdkclientsecret_desc', 'mod_livesession'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'mod_livesession/sdkversion',
        get_string('settings:sdkversion', 'mod_livesession'),
        get_string('settings:sdkversion_desc', 'mod_livesession'),
        '6.5.0',
        PARAM_RAW_TRIMMED
    ));

    $settings->add(new admin_setting_configtext(
        'mod_livesession/sdkurl',
        get_string('settings:sdkurl', 'mod_livesession'),
        get_string('settings:sdkurl_desc', 'mod_livesession'),
        '',
        PARAM_RAW_TRIMMED
    ));

    $settings->add(new admin_setting_description(
        'mod_livesession/testlink',
        get_string('test:title', 'mod_livesession'),
        html_writer::link(
            new moodle_url('/mod/livesession/testconnection.php'),
            get_string('test:run', 'mod_livesession'),
            ['class' => 'btn btn-secondary']
        ) . html_writer::tag(
            'p',
            get_string('test:settingsdesc', 'mod_livesession'),
            ['class' => 'mt-2 text-muted']
        )
    ));

    $settings->add(new admin_setting_heading(
        'mod_livesession/defaultsheading',
        get_string('settings:defaultsheading', 'mod_livesession'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'mod_livesession/defaulthost',
        get_string('settings:defaulthost', 'mod_livesession'),
        get_string('settings:defaulthost_desc', 'mod_livesession'),
        'me',
        PARAM_RAW_TRIMMED
    ));

    $settings->add(new admin_setting_configduration(
        'mod_livesession/defaultduration',
        get_string('settings:defaultduration', 'mod_livesession'),
        get_string('settings:defaultduration_desc', 'mod_livesession'),
        HOURSECS
    ));

    $settings->add(new admin_setting_configduration(
        'mod_livesession/heartbeatinterval',
        get_string('settings:heartbeatinterval', 'mod_livesession'),
        get_string('settings:heartbeatinterval_desc', 'mod_livesession'),
        60
    ));

    $settings->add(new admin_setting_configduration(
        'mod_livesession/staletimeout',
        get_string('settings:staletimeout', 'mod_livesession'),
        get_string('settings:staletimeout_desc', 'mod_livesession'),
        5 * MINSECS
    ));

    $settings->add(new admin_setting_heading(
        'mod_livesession/privacyheading',
        get_string('settings:privacyheading', 'mod_livesession'),
        get_string('settings:privacyheading_desc', 'mod_livesession')
    ));

    $settings->add(new admin_setting_configcheckbox(
        'mod_livesession/recordip',
        get_string('settings:recordip', 'mod_livesession'),
        get_string('settings:recordip_desc', 'mod_livesession'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'mod_livesession/ipinfeedback',
        get_string('settings:ipinfeedback', 'mod_livesession'),
        get_string('settings:ipinfeedback_desc', 'mod_livesession'),
        1
    ));

    $settings->add(new admin_setting_configduration(
        'mod_livesession/logretention',
        get_string('settings:logretention', 'mod_livesession'),
        get_string('settings:logretention_desc', 'mod_livesession'),
        0
    ));
}
