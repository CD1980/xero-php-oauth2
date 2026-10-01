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

use context_course;
use mod_livesession\local\zoom\client;
use mod_livesession\local\zoom\zoom_exception;
use stdClass;

/**
 * Keeps the Zoom meeting behind an activity instance in step with the Moodle record.
 *
 * Most sessions own a meeting of their own. A session may instead borrow another
 * session's meeting, so that one instructor hosts a single room and several cohorts
 * running at the same time all land in it. The session that owns the room is the
 * master; the ones borrowing it are its children. A child never creates, updates or
 * deletes anything at Zoom - it copies the master's meeting details and otherwise keeps
 * its own schedule, join window, attendance and grades.
 *
 * @package    mod_livesession
 * @copyright  2026 Aspire Education and Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class meeting_manager {
    /** @var string Zoom meeting type: scheduled. */
    const TYPE_SCHEDULED = 2;

    /**
     * Create the Zoom meeting for a session, or update it if one already exists.
     *
     * Failure here is recorded on the instance rather than thrown, so that saving the
     * activity form never fails outright because Zoom was briefly unavailable - the
     * teacher gets a warning on the view page and can retry.
     *
     * @param stdClass $livesession row from {livesession}, updated in place
     * @return stdClass the same record, with sync fields refreshed
     */
    public static function sync(stdClass $livesession): stdClass {
        global $DB;

        if (!empty($livesession->mastersessionid)) {
            return self::link_to_master($livesession);
        }

        try {
            $zoom = client::instance();
            $payload = self::build_payload($livesession);

            if (!empty($livesession->meetingid)) {
                $zoom->update_meeting($livesession->meetingid, $payload);
                $meeting = $zoom->get_meeting($livesession->meetingid);
            } else {
                $host = trim((string) $livesession->zoomhostid);
                if ($host === '') {
                    $host = (string) get_config('mod_livesession', 'defaulthost');
                }
                if ($host === '') {
                    $host = 'me';
                }
                $meeting = $zoom->create_meeting($host, $payload);
            }

            $livesession->meetingid = (string) ($meeting['id'] ?? $livesession->meetingid);
            $livesession->meetinguuid = (string) ($meeting['uuid'] ?? $livesession->meetinguuid);
            $livesession->passcode = (string) ($meeting['password'] ?? '');
            $livesession->joinurl = (string) ($meeting['join_url'] ?? '');
            $livesession->syncstatus = 'ok';
            $livesession->syncerror = null;
        } catch (zoom_exception $e) {
            $livesession->syncstatus = 'error';
            $livesession->syncerror = $e->getMessage();
        }

        $livesession->timemodified = time();
        $DB->update_record('livesession', $livesession);

        // A child holds a copy of these details, so a change here has to reach it or it
        // would go on sending students to the previous meeting number.
        if (!empty($livesession->ismaster)) {
            self::propagate_to_children($livesession);
        }

        return $livesession;
    }

    /**
     * Point a session at the meeting owned by its master.
     *
     * Nothing is sent to Zoom: the master's meeting already exists, and a child that
     * tried to update it would be fighting the master over the same room.
     *
     * @param stdClass $livesession row from {livesession}, updated in place
     * @return stdClass
     */
    protected static function link_to_master(stdClass $livesession): stdClass {
        global $DB;

        $master = self::get_master($livesession);

        if (!$master) {
            $livesession->syncstatus = 'error';
            $livesession->syncerror = get_string('error:mastermissing', 'mod_livesession');
            $livesession->meetingid = null;
            $livesession->meetinguuid = null;
            $livesession->passcode = null;
            $livesession->joinurl = null;
        } else if (empty($master->meetingid)) {
            // The master exists but has no room yet, usually because its own sync to Zoom
            // failed. Say so plainly rather than leaving the child silently empty.
            $livesession->syncstatus = 'error';
            $livesession->syncerror = get_string('error:masternotready', 'mod_livesession');
            $livesession->meetingid = null;
        } else {
            $livesession->meetingid = $master->meetingid;
            $livesession->meetinguuid = $master->meetinguuid;
            $livesession->passcode = $master->passcode;
            $livesession->joinurl = $master->joinurl;
            $livesession->zoomhostid = $master->zoomhostid;
            $livesession->syncstatus = 'linked';
            $livesession->syncerror = null;
        }

        $livesession->timemodified = time();
        $DB->update_record('livesession', $livesession);

        return $livesession;
    }

    /**
     * Copy a master's meeting details onto every session sharing its room.
     *
     * @param stdClass $master
     * @return void
     */
    public static function propagate_to_children(stdClass $master): void {
        global $DB;

        $children = self::get_children((int) $master->id);
        foreach ($children as $child) {
            $child->meetingid = $master->meetingid;
            $child->meetinguuid = $master->meetinguuid;
            $child->passcode = $master->passcode;
            $child->joinurl = $master->joinurl;
            $child->zoomhostid = $master->zoomhostid;
            $child->syncstatus = empty($master->meetingid) ? 'error' : 'linked';
            $child->syncerror = empty($master->meetingid)
                ? get_string('error:masternotready', 'mod_livesession')
                : null;
            $child->timemodified = time();
            $DB->update_record('livesession', $child);
        }
    }

    /**
     * Release every session that was sharing this one's room.
     *
     * Called when the master is about to be deleted. The children are not quietly given
     * meetings of their own: that would conjure up a Zoom meeting per course behind the
     * teacher's back. They are unlinked and flagged instead, so the next person to open
     * one is told what happened and can decide.
     *
     * @param stdClass $master
     * @return void
     */
    public static function unlink_children(stdClass $master): void {
        global $DB;

        foreach (self::get_children((int) $master->id) as $child) {
            $child->mastersessionid = 0;
            $child->meetingid = null;
            $child->meetinguuid = null;
            $child->passcode = null;
            $child->joinurl = null;
            $child->syncstatus = 'error';
            $child->syncerror = get_string('error:masterdeleted', 'mod_livesession');
            $child->timemodified = time();
            $DB->update_record('livesession', $child);
        }
    }

    /**
     * The session whose room this one borrows, if any.
     *
     * @param stdClass $livesession
     * @return stdClass|null
     */
    public static function get_master(stdClass $livesession): ?stdClass {
        global $DB;

        if (empty($livesession->mastersessionid)) {
            return null;
        }
        $master = $DB->get_record('livesession', ['id' => (int) $livesession->mastersessionid]);

        // A master that is itself borrowing a room would make a chain, which nothing in
        // the form allows and which get_children() would not follow. Refuse it.
        if (!$master || !empty($master->mastersessionid)) {
            return null;
        }
        return $master;
    }

    /**
     * Every session sharing this one's room.
     *
     * @param int $masterid
     * @return array
     */
    public static function get_children(int $masterid): array {
        global $DB;
        return $masterid ? $DB->get_records('livesession', ['mastersessionid' => $masterid]) : [];
    }

    /**
     * Sessions the current user may borrow a room from, as a select menu.
     *
     * The bar is being able to add a live session to the master's course: someone who
     * could schedule a session there anyway is not gaining reach by pointing a session at
     * that course's room.
     *
     * @param int $excludeid a session to leave out, normally the one being edited
     * @return array session id => human readable label, with 0 => none first
     */
    public static function master_menu(int $excludeid = 0): array {
        global $DB;

        $menu = [0 => get_string('nomastersession', 'mod_livesession')];

        $select = 'ismaster = 1 AND mastersessionid = 0';
        $params = [];
        if ($excludeid) {
            $select .= ' AND id <> :excludeid';
            $params['excludeid'] = $excludeid;
        }
        $masters = $DB->get_records_select('livesession', $select, $params, 'starttime ASC');
        if (!$masters) {
            return $menu;
        }

        $courses = $DB->get_records_list(
            'course',
            'id',
            array_unique(array_map(static fn($m) => (int) $m->course, $masters)),
            '',
            'id, shortname'
        );

        foreach ($masters as $master) {
            $course = $courses[$master->course] ?? null;
            if (!$course) {
                continue;
            }
            if (!has_capability('mod/livesession:addinstance', context_course::instance($course->id))) {
                continue;
            }
            $menu[(int) $master->id] = get_string('mastersessionlabel', 'mod_livesession', (object) [
                'course'  => format_string($course->shortname),
                'name'    => format_string($master->name),
                'starts'  => userdate($master->starttime, get_string('strftimedatetimeshort', 'langconfig')),
            ]);
        }

        return $menu;
    }

    /**
     * Translate a session record into the Zoom meeting object.
     *
     * @param stdClass $livesession
     * @return array
     */
    protected static function build_payload(stdClass $livesession): array {
        $topic = shorten_text(format_string($livesession->name, true), 190, true, '');

        $agenda = '';
        if (!empty($livesession->intro)) {
            $agenda = shorten_text(html_to_text($livesession->intro, 0, false), 1900, true, '');
        }

        $waitingroom = !empty($livesession->waitingroom);
        // Zoom refuses a meeting that has both; the waiting room is the stricter choice,
        // so when a teacher asks for both we honour the waiting room and drop join-before-host.
        $joinbeforehost = !empty($livesession->joinbeforehost) && !$waitingroom;

        $autorecord = in_array($livesession->autorecord, ['none', 'local', 'cloud'], true)
            ? $livesession->autorecord
            : 'none';

        // Off unless a site administrator turns it on. Leaving it on makes Zoom greet
        // every participant with a "Meeting Summary has been enabled" notice, and it
        // means an AI transcript and summary of a class is generated and distributed,
        // which is a decision for the institution rather than a default.
        $aicompanion = (bool) get_config('mod_livesession', 'aicompanion');

        return [
            'topic'      => $topic,
            'type'       => self::TYPE_SCHEDULED,
            'start_time' => gmdate('Y-m-d\TH:i:s\Z', (int) $livesession->starttime),
            'duration'   => max(1, (int) ceil($livesession->duration / MINSECS)),
            'timezone'   => 'UTC',
            'agenda'     => $agenda,
            'settings'   => [
                'host_video'             => true,
                'participant_video'      => false,
                'join_before_host'       => $joinbeforehost,
                'jbh_time'               => 0,
                'mute_upon_entry'        => !empty($livesession->muteonentry),
                'waiting_room'           => $waitingroom,
                'approval_type'          => 2,
                'auto_recording'         => $autorecord,
                'meeting_authentication' => false,
                'show_share_button'      => true,
                'auto_start_meeting_summary'      => $aicompanion,
                'auto_start_ai_companion_questions' => $aicompanion,
            ],
        ];
    }

    /**
     * Remove the Zoom meeting backing a session.
     *
     * @param stdClass $livesession
     * @return void
     */
    public static function delete(stdClass $livesession): void {
        // The meeting id on a child is a copy of the master's. Deleting it here would
        // pull the room out from under the master and every other cohort in it.
        if (!empty($livesession->mastersessionid)) {
            return;
        }
        if (empty($livesession->meetingid) || !client::is_configured()) {
            return;
        }
        try {
            client::instance()->delete_meeting($livesession->meetingid);
        } catch (zoom_exception $e) {
            // The activity is going away regardless; leaving an orphaned Zoom meeting
            // behind is preferable to blocking the delete, so note it and move on.
            debugging('mod_livesession: could not delete Zoom meeting ' . $livesession->meetingid
                . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * The window during which students may enter the embedded meeting.
     *
     * @param stdClass $livesession
     * @return array [int opentime, int closetime]
     */
    public static function join_window(stdClass $livesession): array {
        $open = (int) $livesession->starttime - (int) $livesession->joinwindowbefore;
        $close = (int) $livesession->starttime + (int) $livesession->duration + (int) $livesession->joinwindowafter;
        return [$open, $close];
    }

    /**
     * Whether the session is open for joining right now.
     *
     * @param stdClass $livesession
     * @param int|null $now defaults to the current time
     * @return bool
     */
    public static function is_joinable(stdClass $livesession, ?int $now = null): bool {
        $now = $now ?? time();
        [$open, $close] = self::join_window($livesession);
        return $now >= $open && $now <= $close;
    }

    /**
     * When the meeting is considered finished for reconciliation purposes.
     *
     * @param stdClass $livesession
     * @return int
     */
    public static function endtime(stdClass $livesession): int {
        return (int) $livesession->starttime + (int) $livesession->duration;
    }
}
