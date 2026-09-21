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

use mod_livesession\local\attendance;

/**
 * Tests for the attendance capture and grading logic.
 *
 * @package    mod_livesession
 * @copyright  2026 Aspire Education and Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_livesession\local\attendance
 */
final class attendance_test extends \advanced_testcase {

    /** @var \stdClass */
    protected $course;

    /** @var \stdClass */
    protected $student;

    /**
     * Set up a course with one enrolled student.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $this->course = $this->getDataGenerator()->create_course();
        $this->student = $this->getDataGenerator()->create_user(['username' => 'jstudent']);
        $this->getDataGenerator()->enrol_user($this->student->id, $this->course->id, 'student');

        // getremoteaddr() reads this; without it the IP capture cannot be asserted.
        $_SERVER['REMOTE_ADDR'] = '203.0.113.45';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (TestBrowser)';
    }

    /**
     * Build a session instance.
     *
     * @param array $overrides
     * @return \stdClass
     */
    protected function create_session(array $overrides = []): \stdClass {
        global $DB;

        $instance = $this->getDataGenerator()->create_module('livesession',
            ['course' => $this->course->id] + $overrides);

        return $DB->get_record('livesession', ['id' => $instance->id], '*', MUST_EXIST);
    }

    /**
     * Pretend the given number of seconds have passed since the last sighting.
     *
     * @param \stdClass $record
     * @param int $seconds
     * @return void
     */
    protected function rewind_lastseen(\stdClass $record, int $seconds): void {
        global $DB;
        $DB->set_field('livesession_attendance', 'lastseen', time() - $seconds, ['id' => $record->id]);
    }

    /**
     * The requirement is a share of the scheduled duration unless minutes are set.
     *
     * @return void
     */
    public function test_required_seconds(): void {
        $session = $this->create_session(['duration' => HOURSECS, 'requiredpercent' => 75]);
        $this->assertEquals(45 * MINSECS, attendance::required_seconds($session));

        $session->requiredminutes = 10;
        $this->assertEquals(10 * MINSECS, attendance::required_seconds($session));

        $session->requiredminutes = 0;
        $session->requiredpercent = 0;
        $this->assertEquals(0, attendance::required_seconds($session));
    }

    /**
     * Joining opens a record carrying the IP, username and sign-in time.
     *
     * @return void
     */
    public function test_open_segment_captures_identity(): void {
        $session = $this->create_session();
        $this->setUser($this->student);

        $record = attendance::open_segment($session, (int) $this->student->id);

        $this->assertEquals('203.0.113.45', $record->firstip);
        $this->assertEquals('203.0.113.45', $record->lastip);
        $this->assertEquals('jstudent', $record->usernamesnapshot);
        $this->assertEquals('Mozilla/5.0 (TestBrowser)', $record->useragent);
        $this->assertEquals(1, $record->joincount);
        $this->assertNotEmpty($record->sessionhash);
        $this->assertEquals(64, strlen($record->sessionhash));
    }

    /**
     * With IP recording switched off the address is not stored.
     *
     * @return void
     */
    public function test_open_segment_respects_recordip(): void {
        $session = $this->create_session(['recordip' => 0]);
        $this->setUser($this->student);

        $record = attendance::open_segment($session, (int) $this->student->id);

        $this->assertNull($record->firstip);
        $this->assertNull($record->lastip);
    }

    /**
     * Each heartbeat credits the time since the previous one.
     *
     * @return void
     */
    public function test_heartbeat_accrues_time(): void {
        $session = $this->create_session();
        $this->setUser($this->student);

        $record = attendance::open_segment($session, (int) $this->student->id);
        $this->assertEquals(0, $record->duration);

        $this->rewind_lastseen($record, 60);
        $record = attendance::heartbeat($session, (int) $this->student->id);
        $this->assertEqualsWithDelta(60, $record->duration, 2);

        $this->rewind_lastseen($record, 120);
        $record = attendance::heartbeat($session, (int) $this->student->id);
        $this->assertEqualsWithDelta(180, $record->duration, 2);
    }

    /**
     * A browser that vanishes cannot earn more than the stale timeout.
     *
     * @return void
     */
    public function test_heartbeat_caps_long_gaps(): void {
        set_config('staletimeout', 300, 'mod_livesession');

        $session = $this->create_session();
        $this->setUser($this->student);

        $record = attendance::open_segment($session, (int) $this->student->id);
        $this->rewind_lastseen($record, 4 * HOURSECS);

        $record = attendance::heartbeat($session, (int) $this->student->id);

        $this->assertEqualsWithDelta(300, $record->duration, 2);
    }

    /**
     * Rejoining increments the count and keeps the original first join.
     *
     * @return void
     */
    public function test_rejoin_increments_count(): void {
        $session = $this->create_session();
        $this->setUser($this->student);

        $first = attendance::open_segment($session, (int) $this->student->id);
        $firstjoin = $first->firstjoin;

        attendance::close_segment($session, (int) $this->student->id);
        $second = attendance::open_segment($session, (int) $this->student->id);

        $this->assertEquals(2, $second->joincount);
        $this->assertEquals($firstjoin, $second->firstjoin);
    }

    /**
     * Status follows the accumulated time against the requirement.
     *
     * @return void
     */
    public function test_status_derivation(): void {
        global $DB;

        $session = $this->create_session([
            'duration'        => HOURSECS,
            'requiredpercent' => 50,
            'latethreshold'   => 5 * MINSECS,
            'starttime'       => time() - HOURSECS,
        ]);
        $this->setUser($this->student);

        $record = attendance::open_segment($session, (int) $this->student->id);

        // Nothing attended yet.
        $record->duration = 0;
        $record->firstjoin = $session->starttime;
        attendance::recalculate($session, $record);
        $this->assertEquals(attendance::STATUS_ABSENT, $record->status);

        // Short of the 30 minute requirement.
        $record->duration = 10 * MINSECS;
        attendance::recalculate($session, $record);
        $this->assertEquals(attendance::STATUS_PARTIAL, $record->status);

        // Met the requirement, arrived on time.
        $record->duration = 40 * MINSECS;
        attendance::recalculate($session, $record);
        $this->assertEquals(attendance::STATUS_PRESENT, $record->status);

        // Met the requirement, but arrived after the late threshold.
        $record->firstjoin = $session->starttime + 20 * MINSECS;
        attendance::recalculate($session, $record);
        $this->assertEquals(attendance::STATUS_LATE, $record->status);
    }

    /**
     * All-or-nothing grading awards the full mark only at the requirement.
     *
     * @return void
     */
    public function test_threshold_grading(): void {
        $session = $this->create_session([
            'gradingmethod'   => attendance::GRADING_THRESHOLD,
            'grade'           => 20,
            'duration'        => HOURSECS,
            'requiredpercent' => 50,
        ]);

        $record = (object) ['duration' => 29 * MINSECS, 'status' => attendance::STATUS_PARTIAL];
        $this->assertEquals(0.0, attendance::calculate_grade($session, $record));

        $record->duration = 30 * MINSECS;
        $record->status = attendance::STATUS_PRESENT;
        $this->assertEquals(20.0, attendance::calculate_grade($session, $record));
    }

    /**
     * Proportional grading scales with time and never exceeds the maximum.
     *
     * @return void
     */
    public function test_proportional_grading(): void {
        $session = $this->create_session([
            'gradingmethod' => attendance::GRADING_PROPORTIONAL,
            'grade'         => 100,
            'duration'      => HOURSECS,
        ]);

        $record = (object) ['duration' => 30 * MINSECS, 'status' => attendance::STATUS_PARTIAL];
        $this->assertEquals(50.0, attendance::calculate_grade($session, $record));

        $record->duration = 2 * HOURSECS;
        $this->assertEquals(100.0, attendance::calculate_grade($session, $record));
    }

    /**
     * Ungraded sessions and excused students produce no mark.
     *
     * @return void
     */
    public function test_no_grade_cases(): void {
        $session = $this->create_session(['gradingmethod' => attendance::GRADING_NONE]);
        $record = (object) ['duration' => HOURSECS, 'status' => attendance::STATUS_PRESENT];
        $this->assertNull(attendance::calculate_grade($session, $record));

        $session = $this->create_session(['gradingmethod' => attendance::GRADING_THRESHOLD]);
        $record = (object) ['duration' => 0, 'status' => attendance::STATUS_EXCUSED];
        $this->assertNull(attendance::calculate_grade($session, $record));
    }

    /**
     * A teacher's correction sticks, and automation stops touching the record.
     *
     * @return void
     */
    public function test_override_is_respected(): void {
        $session = $this->create_session(['duration' => HOURSECS, 'requiredpercent' => 50]);
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $this->course->id, 'editingteacher');

        $this->setUser($this->student);
        attendance::open_segment($session, (int) $this->student->id);

        $this->setUser($teacher);
        $record = attendance::override($session, (int) $this->student->id,
            attendance::STATUS_EXCUSED, 0, 'Medical certificate provided.');

        $this->assertEquals(attendance::STATUS_EXCUSED, $record->status);
        $this->assertEquals(1, $record->overridden);
        $this->assertEquals($teacher->id, $record->overriddenby);

        // Recalculation must leave an overridden record alone.
        $record->duration = 0;
        attendance::recalculate($session, $record);
        $this->assertEquals(attendance::STATUS_EXCUSED, $record->status);

        attendance::clear_override($session, (int) $this->student->id);
        $refreshed = attendance::get_record((int) $session->id, (int) $this->student->id);
        $this->assertEquals(0, $refreshed->overridden);
        $this->assertEquals(attendance::STATUS_ABSENT, $refreshed->status);
    }

    /**
     * Every join writes an audit row carrying the address it came from.
     *
     * @return void
     */
    public function test_audit_log_written(): void {
        global $DB;

        $session = $this->create_session();
        $this->setUser($this->student);

        attendance::open_segment($session, (int) $this->student->id);
        attendance::close_segment($session, (int) $this->student->id);

        $logs = $DB->get_records('livesession_log', ['livesessionid' => $session->id], 'id ASC');
        $actions = array_values(array_map(fn($l) => $l->action, $logs));

        $this->assertEquals(['join', 'leave'], $actions);
        foreach ($logs as $log) {
            $this->assertEquals('203.0.113.45', $log->ipaddress);
        }
    }

    /**
     * The gradebook feedback carries the evidence, and honours the privacy switch.
     *
     * @return void
     */
    public function test_feedback_contents(): void {
        $session = $this->create_session();
        $this->setUser($this->student);
        $record = attendance::open_segment($session, (int) $this->student->id);

        $feedback = attendance::build_feedback($session, $record);
        $this->assertStringContainsString('203.0.113.45', $feedback);
        $this->assertStringContainsString('jstudent', $feedback);

        $session->ipinfeedback = 0;
        $this->assertSame('', attendance::build_feedback($session, $record));
    }

    /**
     * Grades reach the gradebook with the evidence attached as feedback.
     *
     * @return void
     */
    public function test_grade_reaches_gradebook(): void {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');

        $session = $this->create_session([
            'gradingmethod'   => attendance::GRADING_THRESHOLD,
            'grade'           => 10,
            'duration'        => HOURSECS,
            'requiredpercent' => 50,
        ]);

        $this->setUser($this->student);
        $record = attendance::open_segment($session, (int) $this->student->id);
        $this->rewind_lastseen($record, 40 * MINSECS);
        attendance::heartbeat($session, (int) $this->student->id);

        $grades = grade_get_grades($this->course->id, 'mod', 'livesession',
            $session->id, $this->student->id);
        $item = reset($grades->items);

        $this->assertEquals(10.0, (float) $item->grades[$this->student->id]->grade);
        $this->assertStringContainsString('203.0.113.45', $item->grades[$this->student->id]->str_feedback);
    }
}
