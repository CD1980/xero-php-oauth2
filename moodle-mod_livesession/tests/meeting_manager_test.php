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

use mod_livesession\local\meeting_manager;

/**
 * Tests for sessions that share one Zoom meeting.
 *
 * A master session owns a room; any number of other sessions may point at it so that
 * every cohort running at the same time lands in front of the same instructor, while
 * each keeps its own attendance.
 *
 * @package    mod_livesession
 * @copyright  2026 Aspire Education and Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_livesession\local\meeting_manager
 */
final class meeting_manager_test extends \advanced_testcase {
    /** @var \stdClass */
    protected $course;

    /**
     * Set up a course to hang the sessions off.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
    }

    /**
     * Build a session instance and return its database row.
     *
     * @param array $overrides
     * @return \stdClass
     */
    protected function create_session(array $overrides = []): \stdClass {
        global $DB;

        $instance = $this->getDataGenerator()->create_module(
            'livesession',
            ['course' => $this->course->id] + $overrides
        );

        return $DB->get_record('livesession', ['id' => $instance->id], '*', MUST_EXIST);
    }

    /**
     * Re-read a session from the database.
     *
     * @param int $id
     * @return \stdClass
     */
    protected function reload(int $id): \stdClass {
        global $DB;
        return $DB->get_record('livesession', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * A session pointed at a master takes the master's room rather than asking Zoom.
     *
     * @return void
     */
    public function test_child_borrows_the_master_room(): void {
        $master = $this->create_session([
            'ismaster'   => 1,
            'meetingid'  => '85555555555',
            'passcode'   => 'abc123',
            'joinurl'    => 'https://example.zoom.us/j/85555555555',
            'zoomhostid' => 'trainer@example.com',
        ]);

        $child = $this->create_session([
            'mastersessionid' => $master->id,
            'meetingid'       => null,
            'passcode'        => null,
            'joinurl'         => null,
        ]);

        $child = meeting_manager::sync($child);

        $this->assertEquals('85555555555', $child->meetingid);
        $this->assertEquals('abc123', $child->passcode);
        $this->assertEquals('https://example.zoom.us/j/85555555555', $child->joinurl);
        $this->assertEquals('trainer@example.com', $child->zoomhostid);
        $this->assertEquals('linked', $child->syncstatus);
    }

    /**
     * Changing the master's room moves every cohort sharing it.
     *
     * @return void
     */
    public function test_master_changes_reach_its_children(): void {
        $master = $this->create_session(['ismaster' => 1, 'meetingid' => '81111111111']);
        $child = $this->create_session(['mastersessionid' => $master->id]);
        meeting_manager::sync($child);

        $master->meetingid = '82222222222';
        $master->joinurl = 'https://example.zoom.us/j/82222222222';
        meeting_manager::propagate_to_children($master);

        $this->assertEquals('82222222222', $this->reload((int) $child->id)->meetingid);
    }

    /**
     * Deleting a child must never take the shared room with it.
     *
     * @return void
     */
    public function test_deleting_a_child_leaves_the_room_alone(): void {
        $master = $this->create_session(['ismaster' => 1, 'meetingid' => '83333333333']);
        $child = $this->create_session(['mastersessionid' => $master->id]);
        meeting_manager::sync($child);

        // With no Zoom credentials configured, a call that reached the API would record a
        // sync error; the guard means nothing is attempted at all.
        meeting_manager::delete($this->reload((int) $child->id));

        $this->assertEquals('83333333333', $this->reload((int) $master->id)->meetingid);
    }

    /**
     * Losing the master leaves the children flagged rather than silently roomless.
     *
     * @return void
     */
    public function test_unlink_children_explains_itself(): void {
        $master = $this->create_session(['ismaster' => 1, 'meetingid' => '84444444444']);
        $child = $this->create_session(['mastersessionid' => $master->id]);
        meeting_manager::sync($child);

        meeting_manager::unlink_children($master);

        $child = $this->reload((int) $child->id);
        $this->assertEquals(0, $child->mastersessionid);
        $this->assertEmpty($child->meetingid);
        $this->assertEquals('error', $child->syncstatus);
        $this->assertEquals(
            get_string('error:masterdeleted', 'mod_livesession'),
            $child->syncerror
        );
    }

    /**
     * A chain of borrowed rooms is refused; only a session that owns one can lend it.
     *
     * @return void
     */
    public function test_a_master_may_not_itself_be_borrowing(): void {
        $owner = $this->create_session(['ismaster' => 1, 'meetingid' => '86666666666']);
        $middle = $this->create_session(['mastersessionid' => $owner->id, 'ismaster' => 1]);
        $tail = $this->create_session(['mastersessionid' => $middle->id]);

        $this->assertNull(meeting_manager::get_master($tail));

        $tail = meeting_manager::sync($tail);
        $this->assertEquals('error', $tail->syncstatus);
        $this->assertEquals(
            get_string('error:mastermissing', 'mod_livesession'),
            $tail->syncerror
        );
    }

    /**
     * A master with no room of its own yet says so, rather than handing out nothing.
     *
     * @return void
     */
    public function test_child_of_an_unsynced_master_reports_why(): void {
        $master = $this->create_session(['ismaster' => 1, 'meetingid' => null]);
        $child = $this->create_session(['mastersessionid' => $master->id]);

        $child = meeting_manager::sync($child);

        $this->assertEquals('error', $child->syncstatus);
        $this->assertEquals(
            get_string('error:masternotready', 'mod_livesession'),
            $child->syncerror
        );
    }

    /**
     * Only sessions that own a room and offer it appear in the menu.
     *
     * @return void
     */
    public function test_master_menu_lists_only_lenders(): void {
        $this->setAdminUser();

        $lender = $this->create_session(['name' => 'Master room', 'ismaster' => 1]);
        $ordinary = $this->create_session(['name' => 'Ordinary session']);
        $borrower = $this->create_session(['name' => 'Borrowing session', 'mastersessionid' => $lender->id]);

        $menu = meeting_manager::master_menu();

        $this->assertArrayHasKey(0, $menu);
        $this->assertArrayHasKey((int) $lender->id, $menu);
        $this->assertArrayNotHasKey((int) $ordinary->id, $menu);
        $this->assertArrayNotHasKey((int) $borrower->id, $menu);

        $this->assertArrayNotHasKey((int) $lender->id, meeting_manager::master_menu((int) $lender->id));
    }
}
