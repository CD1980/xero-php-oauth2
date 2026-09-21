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
 * Upgrade steps for mod_livesession.
 *
 * @package    mod_livesession
 * @copyright  2026 Aspire Education and Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute the mod_livesession upgrade from the given old version.
 *
 * @param int $oldversion the version we are upgrading from
 * @return bool
 */
function xmldb_livesession_upgrade($oldversion) {

    if ($oldversion < 2026092104) {
        // The Meeting SDK version default moved from 3.13.2 to 6.5.0, because Zoom no
        // longer serves the 3.x bundle. Changing the default in settings.php does not
        // touch a value already stored, so a site that never edited this setting would
        // keep failing with "Could not load the Zoom Meeting SDK" after upgrading.
        // Only the stale default is replaced; a deliberate choice is left alone.
        if ((string) get_config('mod_livesession', 'sdkversion') === '3.13.2') {
            set_config('sdkversion', '6.5.0', 'mod_livesession');
        }

        upgrade_mod_savepoint(true, 2026092104, 'livesession');
    }

    return true;
}
