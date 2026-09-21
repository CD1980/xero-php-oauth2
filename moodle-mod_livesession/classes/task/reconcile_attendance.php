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

namespace mod_livesession\task;

use core\task\scheduled_task;
use mod_livesession\local\attendance;
use mod_livesession\local\meeting_manager;
use mod_livesession\local\zoom\client;
use mod_livesession\local\zoom\zoom_exception;

/**
 * Reconciles browser-captured attendance against Zoom's own participant report.
 *
 * The in-page heartbeat is what makes attendance automatic, but it can only see the
 * browser tab. Zoom knows how long each participant was actually connected, so once a
 * session has finished we take Zoom's durations as authoritative where they are longer,
 * which covers the student whose tab was throttled in the background.
 *
 * @package    mod_livesession
 * @copyright  2026 Aspire Education and Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reconcile_attendance extends scheduled_task {
    /** @var int Only look at sessions that finished within this window. */
    const LOOKBACK = 2 * DAYSECS;

    /** @var int Wait this long after the scheduled end before asking Zoom. */
    const SETTLE = 10 * MINSECS;

    /**
     * Task name for the admin UI.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task:reconcileattendance', 'mod_livesession');
    }

    /**
     * Run the reconciliation.
     *
     * @return void
     */
    public function execute() {
        global $DB;

        if (!client::is_configured()) {
            mtrace('mod_livesession: Zoom is not configured, skipping reconciliation.');
            return;
        }

        $now = time();
        $sql = "SELECT *
                  FROM {livesession}
                 WHERE meetingid IS NOT NULL
                       AND meetingid <> ''
                       AND (starttime + duration) < :cutoff
                       AND (starttime + duration) > :lookback
                       AND lastreconciled < (starttime + duration)";
        $sessions = $DB->get_records_sql($sql, [
            'cutoff'   => $now - self::SETTLE,
            'lookback' => $now - self::LOOKBACK,
        ]);

        foreach ($sessions as $livesession) {
            try {
                $this->reconcile_one($livesession);
            } catch (zoom_exception $e) {
                mtrace('mod_livesession: could not reconcile session ' . $livesession->id
                    . ': ' . $e->getMessage());
            }
        }
    }

    /**
     * Pull Zoom's participant report for one session and merge it in.
     *
     * @param \stdClass $livesession
     * @return void
     */
    protected function reconcile_one(\stdClass $livesession): void {
        global $DB;

        $zoom = client::instance();

        $uuid = (string) $livesession->meetinguuid;
        if ($uuid === '') {
            $instances = $zoom->get_past_instances((string) $livesession->meetingid);
            if (!$instances) {
                $livesession->lastreconciled = time();
                $DB->update_record('livesession', $livesession);
                return;
            }
            $uuid = (string) end($instances)['uuid'];
        }

        $participants = $zoom->get_past_participants($uuid);

        // Zoom reports one row per connection, so a reconnect appears twice. Total them.
        $byemail = [];
        foreach ($participants as $participant) {
            $email = strtolower(trim((string) ($participant['user_email'] ?? '')));
            if ($email === '') {
                continue;
            }
            if (!isset($byemail[$email])) {
                $byemail[$email] = ['duration' => 0, 'uuid' => (string) ($participant['id'] ?? '')];
            }
            $byemail[$email]['duration'] += (int) ($participant['duration'] ?? 0);
        }

        $records = attendance::get_records($livesession->id);
        $userids = array_map(static fn($r) => (int) $r->userid, $records);
        $users = $userids ? $DB->get_records_list('user', 'id', $userids, '', 'id, email') : [];

        foreach ($records as $record) {
            if (!empty($record->overridden)) {
                continue;
            }
            $email = strtolower((string) ($users[$record->userid]->email ?? ''));
            if ($email === '' || !isset($byemail[$email])) {
                continue;
            }

            $zoomduration = (int) $byemail[$email]['duration'];
            // Trust Zoom only when it saw more than we did; a shorter Zoom figure usually
            // means the participant was matched by a different email on their Zoom account.
            if ($zoomduration > (int) $record->duration) {
                $record->duration = $zoomduration;
                $record->zoomparticipantuuid = $byemail[$email]['uuid'] ?: $record->zoomparticipantuuid;
                $record->timemodified = time();
                $DB->update_record('livesession_attendance', $record);
                attendance::log($livesession, $record, 'reconcile', null, ['zoomduration' => $zoomduration]);
                attendance::recalculate($livesession, $record);
            }
        }

        $livesession->meetinguuid = $uuid;
        $livesession->lastreconciled = time();
        $DB->update_record('livesession', $livesession);
    }
}
