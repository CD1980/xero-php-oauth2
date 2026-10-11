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

namespace local_qualtracker;

use context_course;
use moodle_url;
use stdClass;

/**
 * Works out which courses are qualification / funded programs, which courses are their units,
 * and how far each enrolled student has got through those units.
 *
 * A program is any course flagged by one of the configured course custom fields. Its students are
 * the users enrolled in that course; its units are found by category or by a linking custom field.
 *
 * @package    local_qualtracker
 * @copyright  2026 Aspire Education and Training
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tracker {

    /** Unit cell status: course completion recorded. */
    const UNIT_COMPLETE = 'complete';
    /** Unit cell status: some activity completed or the course has been accessed. */
    const UNIT_INPROGRESS = 'inprogress';
    /** Unit cell status: enrolled in the unit but nothing done yet. */
    const UNIT_NOTSTARTED = 'notstarted';
    /** Unit cell status: not enrolled in the unit course. */
    const UNIT_NOTENROLLED = 'notenrolled';

    /** Activity completion states that count as done (COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS). */
    const DONE_STATES = [1, 2];

    /** @var array[] Parsed flag rules, each ['shortname' => string, 'value' => ?string]. */
    protected $flags;

    /** @var string How units are linked: category, categorytree or field. */
    protected $linkmode;

    /** @var string Shortname of the custom field on unit courses naming their qualification. */
    protected $linkfield;

    /**
     * Constructor, reads plugin config unless overridden.
     *
     * @param string|null $flags Flag rules, one per line (shortname or shortname=value).
     * @param string|null $linkmode Unit link mode.
     * @param string|null $linkfield Linking custom field shortname.
     */
    public function __construct(?string $flags = null, ?string $linkmode = null, ?string $linkfield = null) {
        $config = get_config('local_qualtracker');
        $this->flags = self::parse_flags($flags ?? ($config->flags ?? 'qualification'));
        $this->linkmode = $linkmode ?? ($config->linkmode ?? 'category');
        $this->linkfield = $linkfield ?? ($config->linkfield ?? '');
    }

    /**
     * Parse the flags setting.
     *
     * @param string $text One rule per line: "shortname" (any truthy value) or "shortname=value".
     * @return array[]
     */
    public static function parse_flags(string $text): array {
        $rules = [];
        foreach (preg_split('/[\r\n,]+/', $text) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $value = null;
            if (strpos($line, '=') !== false) {
                [$line, $value] = array_map('trim', explode('=', $line, 2));
            }
            $rules[] = ['shortname' => $line, 'value' => $value];
        }
        return $rules;
    }

    /**
     * Fetch every stored value of a course custom field.
     *
     * @param string $shortname Field shortname.
     * @return array [field record|null, data records keyed by course id]
     */
    protected function get_field_data(string $shortname): array {
        global $DB;
        $field = $DB->get_record_sql(
            "SELECT f.*
               FROM {customfield_field} f
               JOIN {customfield_category} c ON c.id = f.categoryid
              WHERE c.component = 'core_course' AND c.area = 'course' AND f.shortname = ?",
            [$shortname], IGNORE_MULTIPLE);
        if (!$field) {
            return [null, []];
        }
        $data = [];
        $rs = $DB->get_recordset('customfield_data', ['fieldid' => $field->id], '',
            'id, instanceid, intvalue, charvalue, value');
        foreach ($rs as $record) {
            $data[$record->instanceid] = $record;
        }
        $rs->close();
        return [$field, $data];
    }

    /**
     * Human-readable value of a stored custom field record (select fields give the option label).
     *
     * @param stdClass $field customfield_field record.
     * @param stdClass $record customfield_data record.
     * @return string
     */
    protected static function field_text(stdClass $field, stdClass $record): string {
        if ($field->type === 'select') {
            $config = json_decode($field->configdata ?? '', true) ?: [];
            $options = array_merge([''], preg_split('/\s*\n\s*/', trim($config['options'] ?? '')));
            return trim($options[(int)$record->intvalue] ?? '');
        }
        if ($field->type === 'checkbox') {
            return (string)(int)$record->intvalue;
        }
        $text = (string)($record->charvalue ?? '');
        return trim($text !== '' ? $text : strip_tags((string)$record->value));
    }

    /**
     * Does a stored field value satisfy a flag rule?
     *
     * @param stdClass $field customfield_field record.
     * @param stdClass $record customfield_data record.
     * @param string|null $expected Required value, or null for "set / ticked".
     * @return bool
     */
    protected static function flag_matches(stdClass $field, stdClass $record, ?string $expected): bool {
        $text = self::field_text($field, $record);
        if ($expected === null) {
            return $text !== '' && $text !== '0';
        }
        if ($field->type === 'checkbox') {
            $wanted = in_array(\core_text::strtolower($expected), ['1', 'yes', 'true', 'y'], true);
            return ((int)$record->intvalue === 1) === $wanted;
        }
        if ($field->type === 'select' && is_numeric($expected) && (int)$expected === (int)$record->intvalue) {
            return true;
        }
        return \core_text::strtolower($text) === \core_text::strtolower($expected);
    }

    /**
     * Course ids flagged as qualification / funded programs.
     *
     * @return int[]
     */
    public function get_program_courseids(): array {
        $ids = [];
        foreach ($this->flags as $rule) {
            [$field, $data] = $this->get_field_data($rule['shortname']);
            if (!$field) {
                continue;
            }
            foreach ($data as $courseid => $record) {
                if ($courseid != SITEID && self::flag_matches($field, $record, $rule['value'])) {
                    $ids[$courseid] = (int)$courseid;
                }
            }
        }
        return array_values($ids);
    }

    /**
     * The program courses, in course sort order.
     *
     * @return stdClass[] Course records keyed by id.
     */
    public function get_programs(): array {
        global $DB;
        $ids = $this->get_program_courseids();
        if (!$ids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($ids);
        return $DB->get_records_select('course', "id $insql", $params, 'sortorder',
            'id, category, fullname, shortname, idnumber, visible, enablecompletion, sortorder');
    }

    /**
     * Unit courses belonging to a program.
     *
     * @param stdClass $program Program course record.
     * @param int[] $programids All program course ids, which are never treated as units.
     * @return stdClass[] Course records keyed by id, in natural shortname order.
     */
    public function get_units(stdClass $program, array $programids): array {
        global $DB;
        $params = [];
        if ($this->linkmode === 'field') {
            [$field, $data] = $this->get_field_data($this->linkfield);
            if (!$field) {
                return [];
            }
            $keys = array_filter([(string)$program->id, $program->shortname, $program->idnumber], 'strlen');
            $keys = array_map('core_text::strtolower', $keys);
            $ids = [];
            foreach ($data as $courseid => $record) {
                // A unit can serve several qualifications: allow comma / semicolon separated codes.
                $codes = preg_split('/\s*[,;]\s*/', \core_text::strtolower(self::field_text($field, $record)));
                if (array_intersect($codes, $keys)) {
                    $ids[] = (int)$courseid;
                }
            }
            if (!$ids) {
                return [];
            }
            [$where, $params] = $DB->get_in_or_equal($ids);
            $where = "c.id $where";
        } else if ($this->linkmode === 'categorytree') {
            $path = $DB->get_field('course_categories', 'path', ['id' => $program->category]);
            $where = '(cc.id = ? OR ' . $DB->sql_like('cc.path', '?') . ')';
            $params = [$program->category, $path . '/%'];
        } else {
            $where = 'c.category = ?';
            $params = [$program->category];
        }
        [$notin, $notparams] = $DB->get_in_or_equal(array_merge($programids, [SITEID]), SQL_PARAMS_QM, 'param', false);
        $units = $DB->get_records_sql(
            "SELECT c.id, c.fullname, c.shortname, c.visible, c.enablecompletion
               FROM {course} c
               JOIN {course_categories} cc ON cc.id = c.category
              WHERE $where AND c.id $notin",
            array_merge($params, $notparams));
        // Unit codes read best in natural order (UNIT2 before UNIT10); Moodle's sortorder puts newest courses first.
        \core_collator::asort_objects_by_property($units, 'shortname', \core_collator::SORT_NATURAL);
        return $units;
    }

    /**
     * Build the full report for every program.
     *
     * @return stdClass[] One object per program with units, students and summary counts.
     */
    public function build_report(): array {
        $programs = $this->get_programs();
        $programids = array_keys($programs);
        $report = [];
        foreach ($programs as $program) {
            $report[] = $this->build_program($program, $this->get_units($program, $programids));
        }
        return $report;
    }

    /**
     * Work out progress for every student enrolled in one program.
     *
     * @param stdClass $program Program course record.
     * @param stdClass[] $units Unit course records.
     * @return stdClass
     */
    public function build_program(stdClass $program, array $units): stdClass {
        global $DB;

        $context = context_course::instance($program->id);
        $namefields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        // Include suspended enrolments so finished students whose access has ended still count as completed.
        $students = get_enrolled_users($context, 'moodle/course:isincompletionreports', 0,
            'u.id, u.email, ' . $namefields, 'u.lastname, u.firstname', 0, 0, false);

        $result = (object)[
            'course' => $program,
            'units' => array_values($units),
            'students' => [],
            'enrolled' => count($students),
            'completed' => 0,
            'inprogress' => 0,
            'notstarted' => 0,
        ];
        if (!$students) {
            return $result;
        }

        $unitids = array_keys($units);
        $courseids = array_merge([(int)$program->id], $unitids);
        $userids = array_keys($students);
        $completions = $this->get_course_completions($courseids, $userids);
        $access = $this->get_last_access($courseids, $userids);
        $enrolments = $unitids ? $this->get_unit_enrolments($unitids, $userids) : [];
        [$trackable, $done] = $unitids ? $this->get_activity_progress($unitids, $userids) : [[], []];

        foreach ($students as $user) {
            $cells = [];
            $unitscomplete = 0;
            $percentsum = 0;
            $started = !empty($access[$user->id][$program->id]);
            foreach ($units as $unit) {
                $total = $trackable[$unit->id] ?? 0;
                $count = min($done[$user->id][$unit->id] ?? 0, $total);
                $percent = $total ? (int)round($count / $total * 100) : null;
                if (!empty($completions[$user->id][$unit->id])) {
                    $status = self::UNIT_COMPLETE;
                    $percent = 100;
                    $unitscomplete++;
                } else if ($count > 0 || !empty($access[$user->id][$unit->id])) {
                    $status = self::UNIT_INPROGRESS;
                } else if (!empty($enrolments[$user->id][$unit->id])) {
                    $status = self::UNIT_NOTSTARTED;
                } else {
                    $status = self::UNIT_NOTENROLLED;
                }
                if ($status !== self::UNIT_NOTSTARTED && $status !== self::UNIT_NOTENROLLED) {
                    $started = true;
                }
                $percentsum += $percent ?? 0;
                $cells[] = (object)[
                    'unitid' => $unit->id,
                    'status' => $status,
                    'percent' => $percent,
                    'timecompleted' => $completions[$user->id][$unit->id] ?? null,
                ];
            }

            $programcompleted = $completions[$user->id][$program->id] ?? null;
            $complete = $programcompleted || ($units && $unitscomplete === count($units));
            if ($complete) {
                $status = 'completed';
                $result->completed++;
            } else if ($started) {
                $status = 'inprogress';
                $result->inprogress++;
            } else {
                $status = 'notstarted';
                $result->notstarted++;
            }

            $result->students[] = (object)[
                'user' => $user,
                'status' => $status,
                'cells' => $cells,
                'unitscomplete' => $unitscomplete,
                'unitstotal' => count($units),
                'percent' => $complete ? 100 : ($units ? (int)round($percentsum / count($units)) : 0),
                'timecompleted' => $programcompleted,
            ];
        }
        return $result;
    }

    /**
     * Course completion times.
     *
     * @param int[] $courseids
     * @param int[] $userids
     * @return array [userid][courseid] => timecompleted
     */
    protected function get_course_completions(array $courseids, array $userids): array {
        global $DB;
        [$insql, $params] = $DB->get_in_or_equal($courseids);
        $wanted = array_flip($userids);
        $result = [];
        $rs = $DB->get_recordset_select('course_completions', "course $insql AND timecompleted IS NOT NULL", $params,
            '', 'id, userid, course, timecompleted');
        foreach ($rs as $row) {
            if (isset($wanted[$row->userid])) {
                $result[$row->userid][$row->course] = (int)$row->timecompleted;
            }
        }
        $rs->close();
        return $result;
    }

    /**
     * Last course access times.
     *
     * @param int[] $courseids
     * @param int[] $userids
     * @return array [userid][courseid] => timeaccess
     */
    protected function get_last_access(array $courseids, array $userids): array {
        global $DB;
        [$insql, $params] = $DB->get_in_or_equal($courseids);
        $wanted = array_flip($userids);
        $result = [];
        $rs = $DB->get_recordset_select('user_lastaccess', "courseid $insql", $params, '', 'id, userid, courseid, timeaccess');
        foreach ($rs as $row) {
            if (isset($wanted[$row->userid])) {
                $result[$row->userid][$row->courseid] = (int)$row->timeaccess;
            }
        }
        $rs->close();
        return $result;
    }

    /**
     * Which students hold an enrolment in which unit courses.
     *
     * @param int[] $unitids
     * @param int[] $userids
     * @return array [userid][courseid] => true
     */
    protected function get_unit_enrolments(array $unitids, array $userids): array {
        global $DB;
        [$insql, $params] = $DB->get_in_or_equal($unitids);
        $wanted = array_flip($userids);
        $result = [];
        $rs = $DB->get_recordset_sql(
            "SELECT ue.id, ue.userid, e.courseid
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid $insql", $params);
        foreach ($rs as $row) {
            if (isset($wanted[$row->userid])) {
                $result[$row->userid][$row->courseid] = true;
            }
        }
        $rs->close();
        return $result;
    }

    /**
     * Count tracked activities per unit and how many each student has completed.
     *
     * @param int[] $unitids
     * @param int[] $userids
     * @return array [[courseid => total], [userid][courseid] => done]
     */
    protected function get_activity_progress(array $unitids, array $userids): array {
        global $DB;
        [$insql, $params] = $DB->get_in_or_equal($unitids);
        $cmwhere = "cm.course $insql AND cm.completion > 0 AND cm.visible = 1 AND cm.deletioninprogress = 0";

        $trackable = $DB->get_records_sql_menu(
            "SELECT cm.course, COUNT(cm.id)
               FROM {course_modules} cm
              WHERE $cmwhere
           GROUP BY cm.course", $params);

        [$statesql, $stateparams] = $DB->get_in_or_equal(self::DONE_STATES);
        $wanted = array_flip($userids);
        $done = [];
        $rs = $DB->get_recordset_sql(
            "SELECT cmc.userid, cm.course, COUNT(cmc.id) AS done
               FROM {course_modules_completion} cmc
               JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
              WHERE $cmwhere AND cmc.completionstate $statesql
           GROUP BY cmc.userid, cm.course",
            array_merge($params, $stateparams));
        foreach ($rs as $row) {
            if (isset($wanted[$row->userid])) {
                $done[$row->userid][$row->course] = (int)$row->done;
            }
        }
        $rs->close();
        return [array_map('intval', $trackable), $done];
    }

    /**
     * Turn a built program into template context.
     *
     * @param stdClass $program Result of build_program().
     * @param bool $open Whether the program starts expanded.
     * @return array
     */
    public static function export_program(stdClass $program, bool $open = false): array {
        $statuslabels = [
            'completed' => ['label' => get_string('status_completed', 'local_qualtracker'), 'class' => 'badge-success'],
            'inprogress' => ['label' => get_string('status_inprogress', 'local_qualtracker'), 'class' => 'badge-warning'],
            'notstarted' => ['label' => get_string('status_notstarted', 'local_qualtracker'), 'class' => 'badge-secondary'],
        ];

        $units = [];
        foreach ($program->units as $unit) {
            $units[] = [
                'shortname' => format_string($unit->shortname),
                'fullname' => format_string($unit->fullname),
                'url' => (new moodle_url('/course/view.php', ['id' => $unit->id]))->out(false),
                'reporturl' => (new moodle_url('/report/completion/index.php', ['course' => $unit->id]))->out(false),
            ];
        }

        $students = [];
        foreach ($program->students as $student) {
            $cells = [];
            foreach ($student->cells as $i => $cell) {
                $unitname = $units[$i]['fullname'];
                $statustext = get_string('unit_' . $cell->status, 'local_qualtracker');
                if ($cell->status === self::UNIT_COMPLETE) {
                    $text = '✓';
                    $statustext .= ' ' . userdate($cell->timecompleted, get_string('strftimedate', 'langconfig'));
                } else if ($cell->status === self::UNIT_INPROGRESS) {
                    $text = $cell->percent !== null ? $cell->percent . '%' : '…';
                } else if ($cell->status === self::UNIT_NOTSTARTED) {
                    $text = $cell->percent !== null ? '0%' : '–';
                } else {
                    $text = '';
                }
                if ($cell->percent !== null && $cell->status === self::UNIT_INPROGRESS) {
                    $statustext .= " ({$cell->percent}%)";
                }
                $cells[] = [
                    'status' => $cell->status,
                    'text' => $text,
                    'title' => $unitname . ': ' . $statustext,
                ];
            }
            $students[] = [
                'id' => $student->user->id,
                'fullname' => fullname($student->user),
                'email' => $student->user->email,
                'searchkey' => \core_text::strtolower(fullname($student->user) . ' ' . $student->user->email),
                'profileurl' => (new moodle_url('/user/view.php',
                    ['id' => $student->user->id, 'course' => $program->course->id]))->out(false),
                'status' => $student->status,
                'statuslabel' => $statuslabels[$student->status]['label'],
                'statusclass' => $statuslabels[$student->status]['class'],
                'unitscomplete' => $student->unitscomplete,
                'unitstotal' => $student->unitstotal,
                'percent' => $student->percent,
                'cells' => $cells,
            ];
        }

        $enrolled = $program->enrolled;
        return [
            'id' => $program->course->id,
            'fullname' => format_string($program->course->fullname),
            'shortname' => format_string($program->course->shortname),
            'url' => (new moodle_url('/course/view.php', ['id' => $program->course->id]))->out(false),
            'participantsurl' => (new moodle_url('/user/index.php', ['id' => $program->course->id]))->out(false),
            'unitcount' => count($units),
            'enrolled' => $enrolled,
            'completed' => $program->completed,
            'inprogress' => $program->inprogress,
            'notstarted' => $program->notstarted,
            'working' => $program->inprogress + $program->notstarted,
            'completedpercent' => $enrolled ? (int)round($program->completed / $enrolled * 100) : 0,
            'units' => $units,
            'hasunits' => !empty($units),
            'students' => $students,
            'hasstudents' => !empty($students),
            'open' => $open,
        ];
    }
}
