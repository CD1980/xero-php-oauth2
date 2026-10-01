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

use mod_livesession\local\zoom\signature;
use mod_livesession\local\zoom\zoom_exception;

/**
 * Tests for the Zoom Meeting SDK signature.
 *
 * @package    mod_livesession
 * @copyright  2026 Aspire Education and Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_livesession\local\zoom\signature
 */
final class signature_test extends \advanced_testcase {
    /**
     * Decode a JWT segment.
     *
     * @param string $segment
     * @return array
     */
    protected function decode(string $segment): array {
        $padded = strtr($segment, '-_', '+/');
        $padded .= str_repeat('=', (4 - strlen($padded) % 4) % 4);
        return json_decode(base64_decode($padded), true);
    }

    /**
     * A signed token carries the right claims and verifies against the secret.
     *
     * @return void
     */
    public function test_signature_structure(): void {
        $this->resetAfterTest();

        set_config('sdkclientid', 'TESTSDKKEY', 'mod_livesession');
        set_config('sdkclientsecret', 'testsdksecret', 'mod_livesession');

        $token = signature::create('81234567890', signature::ROLE_ATTENDEE);
        $parts = explode('.', $token);
        $this->assertCount(3, $parts);

        $header = $this->decode($parts[0]);
        $this->assertEquals('HS256', $header['alg']);
        $this->assertEquals('JWT', $header['typ']);

        $payload = $this->decode($parts[1]);
        $this->assertEquals('TESTSDKKEY', $payload['appKey']);
        $this->assertEquals('TESTSDKKEY', $payload['sdkKey']);
        $this->assertEquals('81234567890', $payload['mn']);
        $this->assertEquals(0, $payload['role']);
        $this->assertGreaterThan(time(), $payload['exp']);
        $this->assertLessThanOrEqual(time(), $payload['iat']);
        $this->assertEquals($payload['exp'], $payload['tokenExp']);

        $expected = rtrim(strtr(base64_encode(
            hash_hmac('sha256', $parts[0] . '.' . $parts[1], 'testsdksecret', true)
        ), '+/', '-_'), '=');
        $this->assertEquals($expected, $parts[2]);
    }

    /**
     * Hosts are signed in with the host role.
     *
     * @return void
     */
    public function test_host_role(): void {
        $this->resetAfterTest();

        set_config('sdkclientid', 'TESTSDKKEY', 'mod_livesession');
        set_config('sdkclientsecret', 'testsdksecret', 'mod_livesession');

        $token = signature::create('81234567890', signature::ROLE_HOST);
        $payload = $this->decode(explode('.', $token)[1]);

        $this->assertEquals(1, $payload['role']);
    }

    /**
     * Without credentials no token is minted.
     *
     * @return void
     */
    public function test_requires_configuration(): void {
        $this->resetAfterTest();

        set_config('sdkclientid', '', 'mod_livesession');
        set_config('sdkclientsecret', '', 'mod_livesession');

        $this->assertFalse(signature::is_configured());
        $this->expectException(zoom_exception::class);
        signature::create('81234567890', signature::ROLE_ATTENDEE);
    }
}
