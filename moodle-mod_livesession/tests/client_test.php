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

namespace mod_livesession;

use mod_livesession\local\zoom\client;

/**
 * Tests for the Zoom REST client.
 *
 * @package    mod_livesession
 * @copyright  2026 Aspire Education and Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_livesession\local\zoom\client
 */
final class client_test extends \advanced_testcase {
    /**
     * The token body must be a urlencoded string.
     *
     * Handing Moodle's curl::post() an array instead makes libcurl send a
     * multipart/form-data body while the Content-Type header still says
     * urlencoded, and Zoom answers "Bad Request" to that.
     *
     * @return void
     */
    public function test_token_body_is_urlencoded_string(): void {
        $body = client::build_token_body('ACCT123');

        $this->assertIsString($body);
        $this->assertSame('grant_type=account_credentials&account_id=ACCT123', $body);
        $this->assertStringNotContainsString('Content-Disposition', $body);
        $this->assertStringNotContainsString('boundary', $body);
    }

    /**
     * Account ids are escaped rather than injected raw.
     *
     * @return void
     */
    public function test_token_body_escapes_the_account_id(): void {
        $body = client::build_token_body('a b&c=d');

        parse_str($body, $parsed);
        $this->assertSame('account_credentials', $parsed['grant_type']);
        $this->assertSame('a b&c=d', $parsed['account_id']);
    }

    /**
     * The client refuses to run until every credential is present.
     *
     * @return void
     */
    public function test_is_configured(): void {
        $this->resetAfterTest();

        set_config('accountid', '', 'mod_livesession');
        set_config('clientid', '', 'mod_livesession');
        set_config('clientsecret', '', 'mod_livesession');
        $this->assertFalse(client::is_configured());

        set_config('accountid', 'ACCT123', 'mod_livesession');
        set_config('clientid', 'CID', 'mod_livesession');
        $this->assertFalse(client::is_configured(), 'still missing the secret');

        set_config('clientsecret', 'SECRET', 'mod_livesession');
        $this->assertTrue(client::is_configured());

        // Whitespace is not a credential.
        set_config('clientsecret', '   ', 'mod_livesession');
        $this->assertFalse(client::is_configured());
    }
}
