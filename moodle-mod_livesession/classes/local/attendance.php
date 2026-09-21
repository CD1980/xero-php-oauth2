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

namespace mod_livesession\local;

use context_module;
use core_user;
use stdClass;

/**
 * Records who attended a live session, for how long, and from where.
 *
 * Attendance is captured from the embedded meeting client: the browser calls in once
 * when it joins, then every heartbeat interval while the meeting is open, and once
 * more on the way out. Each call carries the request's IP address and user agent, and
 * the accumulated time drives the grade.
 *
 * Time is accumulated from heartbeat deltas rather than from a simple
 * (leave - join) subtraction, so a student who closes the tab without a clean leave
 * is credited up to their last heartbeat instead of the whole session.
 *
 * @package    mod_livesession
 * @copyright  2026 Aspire Education and Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attendance {
    /** @var string Never joined. */
    const STATUS_ABSENT = 'absent';

    /** @var string Joined and met the attendance requirement. */
    const STATUS_PRESENT = 'present';

    /** @var string Met the requirement but arrived after the late threshold. */
    const STATUS_LATE = 'late';

    /** @var string Joined but fell short of the requirement. */
    const STATUS_PARTIAL = 'partial';

    /** @var string Excused by a teacher; carries no penalty. */
    const STATUS_EXCUSED = 'excused';

    /** @var int No grade is pushed to the gradebook. */
    const GRADING_NONE = 0;

    /** @var int All or nothing against the attendance requirement. */
    const GRADING_THRESHOLD = 1;

    /** @var int Pro rata against the scheduled duration. */
    const GRADING_PROPORTIONAL = 2;

    /**
     * Open or resume an attendance segment for a user who has just entered the meeting.
     *
     * @param stdClass $livesession
     * @param int $userid
     * @return stdClass the attendance record
     */
    public static function open_segment(stdClass $livesession, int $userid): stdClass {
        global $DB, $USER;

        $now = time();
        $record = self::get_record($livesession->id, $userid);
        $identity = self::capture_identity($livesession, $userid);

        if ($record === false) {
            $record = (object) [
                'livesessionid'       => $livesession->id,
                'userid'              => $userid,
                'status'              => self::STATUS_PARTIAL,
                'firstjoin'           => $now,
                'lastseen'            => $now,
                'lastleave'           => 0,
                'duration'            => 0,
                'joincount'           => 1,
                'firstip'             => $identity['ip'],
                'lastip'              => $identity['ip'],
                'useragent'           => $identity['useragent'],
                'sessionhash'         => $identity['sessionhash'],
                'usernamesnapshot'    => $identity['username'],
                'lastlogin'           => $identity['lastlogin'],
                'zoomparticipantuuid' => null,
                'grade'               => null,
                'gradedtime'          => 0,
                'overridden'          => 0,
                'overriddenby'        => 0,
                'remarks'             => null,
                'timecreated'         => $now,
                'timemodified'        => $now,
            ];
            $record->id = $DB->insert_record('livesession_attendance', $record);
        } else {
            // A rejoin. Credit any time since the last heartbeat before restarting the clock.
            self::accrue($record, $now);
            $record->joincount++;
            $record->lastseen = $now;
            if (empty($record->firstjoin)) {
                $record->firstjoin = $now;
            }
            if (empty($record->firstip)) {
                $record->firstip = $identity['ip'];
            }
            $record->lastip = $identity['ip'];
            $record->useragent = $identity['useragent'];
            $record->sessionhash = $identity['sessionhash'];
            $record->usernamesnapshot = $identity['username'];
            $record->lastlogin = $identity['lastlogin'];
            $record->timemodified = $now;
            $DB->update_record('livesession_attendance', $record);
        }

        self::log($livesession, $record, 'join', $identity);
        self::recalculate($livesession, $record);

        return $record;
    }

    /**
     * Credit a user for still being in the meeting.
     *
     * @param stdClass $livesession
     * @param int $userid
     * @return stdClass the updated attendance record
     */
    public static function heartbeat(stdClass $livesession, int $userid): stdClass {
        global $DB;

        $now = time();
        $record = self::get_record($livesession->id, $userid);
        if ($record === false) {
            // A heartbeat with no open segment means the join call was lost; treat it as a join.
            return self::open_segment($livesession, $userid);
        }

        $identity = self::capture_identity($livesession, $userid);
        self::accrue($record, $now);
        $record->lastseen = $now;
        $record->lastip = $identity['ip'];
        $record->timemodified = $now;
        $DB->update_record('livesession_attendance', $record);

        self::recalculate($livesession, $record);

        return $record;
    }

    /**
     * Close a user's segment when they leave the meeting.
     *
     * @param stdClass $livesession
     * @param int $userid
     * @return stdClass|null the updated record, or null if there was nothing open
     */
    public static function close_segment(stdClass $livesession, int $userid): ?stdClass {
        global $DB;

        $now = time();
        $record = self::get_record($livesession->id, $userid);
        if ($record === false) {
            return null;
        }

        $identity = self::capture_identity($livesession, $userid);
        self::accrue($record, $now);
        $record->lastseen = $now;
        $record->lastleave = $now;
        $record->lastip = $identity['ip'];
        $record->timemodified = $now;
        $DB->update_record('livesession_attendance', $record);

        self::log($livesession, $record, 'leave', $identity);
        self::recalculate($livesession, $record);

        return $record;
    }

    /**
     * Add the time elapsed since the last sighting to the accumulated duration.
     *
     * Anything longer than the stale timeout is assumed to be a browser that went away
     * without telling us, so only the timeout's worth of time is credited.
     *
     * @param stdClass $record modified in place
     * @param int $now
     * @return void
     */
    protected static function accrue(stdClass $record, int $now): void {
        if (empty($record->lastseen) || $record->lastleave >= $record->lastseen) {
            return;
        }
        $delta = $now - (int) $record->lastseen;
        if ($delta <= 0) {
            return;
        }
        $record->duration = (int) $record->duration + min($delta, self::stale_timeout());
    }

    /**
     * Seconds of silence after which a browser is presumed gone.
     *
     * @return int
     */
    public static function stale_timeout(): int {
        $configured = (int) get_config('mod_livesession', 'staletimeout');
        return $configured > 0 ? $configured : 5 * MINSECS;
    }

    /**
     * How often the embedded client should call in, in seconds.
     *
     * @return int
     */
    public static function heartbeat_interval(): int {
        $configured = (int) get_config('mod_livesession', 'heartbeatinterval');
        return $configured > 0 ? $configured : 60;
    }

    /**
     * Gather the identifying details recorded against this attendance.
     *
     * The Moodle session id is hashed rather than stored: it is enough to show that two
     * records came from the same browser session, without handing anyone a usable
     * session token out of the database or a gradebook export.
     *
     * @param stdClass $livesession
     * @param int $userid
     * @return array
     */
    protected static function capture_identity(stdClass $livesession, int $userid): array {
        global $CFG, $USER;

        $recordip = !empty($livesession->recordip) && get_config('mod_livesession', 'recordip');

        $user = ($userid == $USER->id) ? $USER : core_user::get_user($userid, '*', IGNORE_MISSING);

        return [
            'ip'          => $recordip ? substr((string) getremoteaddr(), 0, 45) : null,
            'useragent'   => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            'sessionhash' => hash('sha256', session_id() . $CFG->siteidentifier),
            'username'    => $user ? substr((string) $user->username, 0, 100) : '',
            'lastlogin'   => $user ? (int) ($user->currentlogin ?? 0) : 0,
        ];
    }

    /**
     * Append a row to the audit trail.
     *
     * @param stdClass $livesession
     * @param stdClass $record
     * @param string $action
     * @param array|null $identity re-used when the caller already gathered it
     * @param array $extra additional detail to store as JSON
     * @return void
     */
    public static function log(
        stdClass $livesession,
        stdClass $record,
        string $action,
        ?array $identity = null,
        array $extra = []
    ): void {
        global $DB;

        $identity = $identity ?? self::capture_identity($livesession, (int) $record->userid);

        $DB->insert_record('livesession_log', (object) [
            'livesessionid' => $livesession->id,
            'attendanceid'  => $record->id ?? 0,
            'userid'        => $record->userid,
            'action'        => $action,
            'ipaddress'     => $identity['ip'],
            'useragent'     => $identity['useragent'],
            'sessionhash'   => $identity['sessionhash'],
            'extra'         => $extra ? json_encode($extra) : null,
            'timecreated'   => time(),
        ]);
    }

    /**
     * Render an attended duration for display.
     *
     * format_time(0) renders as "now", which is meaningless as a duration, so zero
     * gets its own wording.
     *
     * @param int $seconds
     * @return string
     */
    public static function format_attended(int $seconds): string {
        if ($seconds <= 0) {
            return get_string('attendednone', 'mod_livesession');
        }
        return format_time($seconds);
    }

    /**
     * Seconds of attendance a student must accumulate to count as present.
     *
     * @param stdClass $livesession
     * @return int
     */
    public static function required_seconds(stdClass $livesession): int {
        if ((int) $livesession->requiredminutes > 0) {
            return (int) $livesession->requiredminutes * MINSECS;
        }
        $percent = min(100, max(0, (int) $livesession->requiredpercent));
        return (int) round($livesession->duration * $percent / 100);
    }

    /**
     * Derive status and grade from the accumulated duration, then push to the gradebook.
     *
     * Records a teacher has overridden are left exactly as the teacher set them.
     *
     * @param stdClass $livesession
     * @param stdClass $record modified in place
     * @return stdClass
     */
    public static function recalculate(stdClass $livesession, stdClass $record): stdClass {
        global $DB;

        if (!empty($record->overridden)) {
            return $record;
        }

        $oldgrade = $record->grade;
        $oldstatus = $record->status;

        $required = self::required_seconds($livesession);
        $duration = (int) $record->duration;

        if ($duration <= 0) {
            $status = self::STATUS_ABSENT;
        } else if ($duration >= $required) {
            $late = !empty($livesession->latethreshold)
                && $record->firstjoin > ($livesession->starttime + $livesession->latethreshold);
            $status = $late ? self::STATUS_LATE : self::STATUS_PRESENT;
        } else {
            $status = self::STATUS_PARTIAL;
        }

        $record->status = $status;
        $record->grade = self::calculate_grade($livesession, $record);
        $record->gradedtime = time();
        $record->timemodified = time();

        $DB->update_record('livesession_attendance', $record);

        // Heartbeats land every minute per attendee, and a gradebook write is far more
        // expensive than an attendance write. Only go to the gradebook when the mark or
        // the status has actually moved.
        $gradechanged = (string) $oldgrade !== (string) $record->grade;
        if ($gradechanged || $oldstatus !== $record->status) {
            self::push_grade($livesession, $record);
        }

        return $record;
    }

    /**
     * Work out the gradebook value for a record.
     *
     * @param stdClass $livesession
     * @param stdClass $record
     * @return float|null null when this activity is not graded
     */
    public static function calculate_grade(stdClass $livesession, stdClass $record): ?float {
        $max = (int) $livesession->grade;
        if ($max <= 0 || (int) $livesession->gradingmethod === self::GRADING_NONE) {
            return null;
        }

        if ($record->status === self::STATUS_EXCUSED) {
            return null;
        }

        $duration = (int) $record->duration;

        if ((int) $livesession->gradingmethod === self::GRADING_PROPORTIONAL) {
            $scheduled = max(1, (int) $livesession->duration);
            return round($max * min(1.0, $duration / $scheduled), 5);
        }

        return $duration >= self::required_seconds($livesession) ? (float) $max : 0.0;
    }

    /**
     * Send one user's grade, with its attendance audit trail as feedback, to the gradebook.
     *
     * @param stdClass $livesession
     * @param stdClass $record
     * @return void
     */
    public static function push_grade(stdClass $livesession, stdClass $record): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/livesession/lib.php');

        if ((int) $livesession->grade <= 0 || (int) $livesession->gradingmethod === self::GRADING_NONE) {
            return;
        }

        $grade = (object) [
            'userid'         => $record->userid,
            'rawgrade'       => $record->grade,
            'feedback'       => self::build_feedback($livesession, $record),
            'feedbackformat' => FORMAT_HTML,
            'dategraded'     => $record->gradedtime,
        ];

        livesession_grade_item_update($livesession, $grade);
    }

    /**
     * Compose the gradebook feedback: the evidence behind the mark.
     *
     * This is what makes the sign-in detail visible in the gradebook itself rather than
     * only in the plugin's own report, so a marks export carries the evidence with it.
     *
     * @param stdClass $livesession
     * @param stdClass $record
     * @return string HTML
     */
    public static function build_feedback(stdClass $livesession, stdClass $record): string {
        if (empty($livesession->ipinfeedback) || !get_config('mod_livesession', 'ipinfeedback')) {
            return '';
        }

        $rows = [];
        $rows[] = [get_string('status', 'mod_livesession'), get_string('status:' . $record->status, 'mod_livesession')];
        $rows[] = [get_string('attendedfor', 'mod_livesession'), self::format_attended((int) $record->duration)];

        if (!empty($record->firstjoin)) {
            $rows[] = [get_string('firstjoin', 'mod_livesession'), userdate($record->firstjoin)];
        }
        if (!empty($record->lastleave)) {
            $rows[] = [get_string('lastleave', 'mod_livesession'), userdate($record->lastleave)];
        }
        if (!empty($record->joincount)) {
            $rows[] = [get_string('joincount', 'mod_livesession'), (string) $record->joincount];
        }
        if (!empty($record->usernamesnapshot)) {
            $rows[] = [get_string('username'), $record->usernamesnapshot];
        }
        if (!empty($record->lastlogin)) {
            $rows[] = [get_string('lastlogin', 'mod_livesession'), userdate($record->lastlogin)];
        }
        if (!empty($record->firstip)) {
            $ip = $record->firstip;
            if (!empty($record->lastip) && $record->lastip !== $record->firstip) {
                $ip .= ' -> ' . $record->lastip;
            }
            $rows[] = [get_string('ipaddress', 'mod_livesession'), $ip];
        }

        $html = \html_writer::start_tag('ul', ['class' => 'mod-livesession-feedback']);
        foreach ($rows as [$label, $value]) {
            $html .= \html_writer::tag('li', \html_writer::tag('strong', s($label) . ': ') . s($value));
        }
        $html .= \html_writer::end_tag('ul');

        return $html;
    }

    /**
     * Fetch one attendance record.
     *
     * @param int $livesessionid
     * @param int $userid
     * @return stdClass|false
     */
    public static function get_record(int $livesessionid, int $userid) {
        global $DB;
        return $DB->get_record('livesession_attendance', [
            'livesessionid' => $livesessionid,
            'userid'        => $userid,
        ]);
    }

    /**
     * All attendance rows for a session, keyed by user id.
     *
     * @param int $livesessionid
     * @return array
     */
    public static function get_records(int $livesessionid): array {
        global $DB;
        return $DB->get_records('livesession_attendance', ['livesessionid' => $livesessionid], '', '*');
    }

    /**
     * Apply a teacher's manual correction to a record.
     *
     * @param stdClass $livesession
     * @param int $userid
     * @param string $status one of the STATUS_* constants
     * @param int|null $durationminutes replacement duration in minutes, or null to keep
     * @param string $remarks
     * @return stdClass
     */
    public static function override(
        stdClass $livesession,
        int $userid,
        string $status,
        ?int $durationminutes,
        string $remarks
    ): stdClass {
        global $DB, $USER;

        $now = time();
        $record = self::get_record($livesession->id, $userid);
        if ($record === false) {
            $record = (object) [
                'livesessionid'    => $livesession->id,
                'userid'           => $userid,
                'status'           => $status,
                'firstjoin'        => 0,
                'lastseen'         => 0,
                'lastleave'        => 0,
                'duration'         => 0,
                'joincount'        => 0,
                'lastlogin'        => 0,
                'gradedtime'       => 0,
                'overridden'       => 0,
                'overriddenby'     => 0,
                'timecreated'      => $now,
                'timemodified'     => $now,
            ];
            $record->id = $DB->insert_record('livesession_attendance', $record);
        }

        $record->status = $status;
        if ($durationminutes !== null) {
            $record->duration = max(0, $durationminutes) * MINSECS;
        }
        $record->remarks = $remarks;
        $record->overridden = 1;
        $record->overriddenby = $USER->id;
        $record->grade = self::calculate_grade($livesession, $record);
        $record->gradedtime = $now;
        $record->timemodified = $now;

        $DB->update_record('livesession_attendance', $record);
        self::log($livesession, $record, 'override', null, ['status' => $status, 'remarks' => $remarks]);
        self::push_grade($livesession, $record);

        return $record;
    }

    /**
     * Clear an override so the record follows the captured data again.
     *
     * @param stdClass $livesession
     * @param int $userid
     * @return void
     */
    public static function clear_override(stdClass $livesession, int $userid): void {
        global $DB;

        $record = self::get_record($livesession->id, $userid);
        if ($record === false) {
            return;
        }
        $record->overridden = 0;
        $record->overriddenby = 0;
        $DB->update_record('livesession_attendance', $record);
        self::recalculate($livesession, $record);
    }
}
