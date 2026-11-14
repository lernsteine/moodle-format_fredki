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

namespace format_fredki\local;

/**
 * Who is here: the people of the course and the agents of FredKI Team Agents, for the side bar "Who is here".
 *
 * The agents come from block_fredkiteamagents when it is installed; without it the side bar shows the people only.
 *
 * @package    format_fredki
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class council {
    /** @var int People listed in the side bar. */
    const MAXPEOPLE = 8;

    /** @var int Seconds a person counts as online after the last access. */
    const ONLINE = 5 * MINSECS;

    /** @var string AI in the course: FredKI as learning companion. */
    const MODE_FREDKI = 'fredki';

    /** @var string AI in the course: the team agents (expert round). */
    const MODE_TEAM = 'team';

    /**
     * Which AI speaks in a course: the learning companion FredKI or the expert round of team agents.
     * "auto" chooses the expert round as soon as the course has a team agent.
     *
     * @param \stdClass $course Course.
     * @return string
     */
    public static function mode(\stdClass $course): string {
        global $CFG, $DB;
        static $cache = [];
        if (isset($cache[$course->id])) {
            return $cache[$course->id];
        }
        require_once($CFG->dirroot . '/course/format/lib.php');
        $choice = (string)(course_get_format($course)->get_format_options()['aimode'] ?? 'auto');
        if ($choice === self::MODE_FREDKI || !self::has_teamagents()) {
            $mode = self::MODE_FREDKI;
        } else if ($choice === self::MODE_TEAM) {
            $mode = self::MODE_TEAM;
        } else {
            // Agents of the round only: the learning companion belongs to the learning companion mode.
            $mode = self::MODE_FREDKI;
            $records = $DB->get_records_select(
                'block_fredkiteamagents_agent',
                'courseid = ? AND status <> ?',
                [$course->id, 'removing'],
                '',
                'id, template'
            );
            foreach ($records as $record) {
                if (!self::is_companion_template((string)$record->template)) {
                    $mode = self::MODE_TEAM;
                    break;
                }
            }
        }
        return $cache[$course->id] = $mode;
    }

    /**
     * Whether a template of FredKI Team Agents is the learning companion.
     *
     * @param string $template Template key.
     * @return bool
     */
    public static function is_companion_template(string $template): bool {
        return method_exists('\\block_fredkiteamagents\\local\\templates', 'is_companion')
            && \block_fredkiteamagents\local\templates::is_companion($template);
    }

    /**
     * The learning companion of a course (added in the block FredKI Team Agents), if it is ready.
     *
     * @param int $courseid Course id.
     * @return \stdClass|null Agent record.
     */
    public static function companion(int $courseid): ?\stdClass {
        if (!self::has_teamagents() || !method_exists('\\block_fredkiteamagents\\local\\agents', 'companion')) {
            return null;
        }
        return \block_fredkiteamagents\local\agents::companion($courseid);
    }

    /**
     * Whether FredKI Team Agents is installed.
     *
     * @return bool
     */
    public static function has_teamagents(): bool {
        return class_exists('\block_fredkiteamagents\local\agents');
    }

    /**
     * Agents of a course keyed by their Moodle account.
     *
     * @param int $courseid Course id.
     * @return array
     */
    public static function agents(int $courseid): array {
        static $cache = [];
        if (!self::has_teamagents()) {
            return [];
        }
        if (!isset($cache[$courseid])) {
            // The learning companion is not part of the round.
            $cache[$courseid] = array_filter(
                \block_fredkiteamagents\local\agents::export_for_course($courseid),
                static fn(array $agent): bool => empty($agent['companion'])
            );
        }
        return $cache[$courseid];
    }

    /**
     * Agent of a user account, if it is one.
     *
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @return array|null
     */
    public static function agent_of(int $courseid, int $userid): ?array {
        $agent = self::agents($courseid)[$userid] ?? null;
        if (
            !$agent && self::has_teamagents()
                && method_exists('\\block_fredkiteamagents\\local\\agents', 'export_account')
        ) {
            // An agent removed from the course: its old posts keep the picture, the name and the AI label.
            $agent = \block_fredkiteamagents\local\agents::export_account($userid);
        }
        return $agent;
    }

    /**
     * Whether agents can be asked in this course right now.
     *
     * @param \stdClass $course Course.
     * @return bool
     */
    public static function can_convene(\stdClass $course): bool {
        if (self::mode($course) !== self::MODE_TEAM) {
            return false;
        }
        foreach (self::agents((int)$course->id) as $agent) {
            if ($agent['ready'] && $agent['active']) {
                return has_capability('block/fredkiteamagents:convene', \context_course::instance($course->id));
            }
        }
        return false;
    }

    /**
     * The people of the course for the row at the top: the most recently active first, with the way to write to them.
     *
     * @param \stdClass $course Course.
     * @param \context_course $context Course context.
     * @return array
     */
    public static function people(\stdClass $course, \context_course $context): array {
        global $DB, $USER, $CFG;
        if (!has_capability('moodle/course:viewparticipants', $context)) {
            return ['show' => false];
        }
        $exclude = array_keys(self::agents((int)$course->id));
        $exclude[] = (int)$USER->id;
        $botid = (int)get_config('format_fredki', 'botuserid');
        if ($botid) {
            $exclude[] = $botid;
        }
        [$esql, $params] = get_enrolled_sql($context, '', 0, true);
        $fields = \core_user\fields::for_userpic()->get_sql('u', false, '', '', false)->selects;
        $params['courseid'] = $course->id;
        [$insql, $inparams] = $DB->get_in_or_equal($exclude, SQL_PARAMS_NAMED, 'ex', false);
        $params += $inparams;
        $from = "FROM {user} u
                 JOIN ($esql) e ON e.id = u.id
            LEFT JOIN {user_lastaccess} ul ON ul.userid = u.id AND ul.courseid = :courseid
                WHERE u.deleted = 0 AND u.id $insql";
        $records = $DB->get_records_sql("SELECT $fields, COALESCE(ul.timeaccess, 0) AS lastseen $from
                                      ORDER BY lastseen DESC, u.firstname ASC", $params, 0, self::MAXPEOPLE);
        $total = $DB->count_records_sql("SELECT COUNT(1) $from", $params);
        $canmessage = !empty($CFG->messaging) && has_capability('moodle/site:sendmessage', \context_system::instance());
        $people = [];
        $online = 1;
        foreach ($records as $record) {
            $isonline = (int)$record->lastseen > time() - self::ONLINE;
            $online += $isonline ? 1 : 0;
            $person = community::export_user($record);
            $person['online'] = $isonline;
            $person['status'] = $isonline ? get_string('online', 'format_fredki')
                : ((int)$record->lastseen > 0 ? community::relative_time((int)$record->lastseen)
                    : get_string('notyet', 'format_fredki'));
            $person['messageurl'] = '';
            if ($canmessage && \core_message\api::can_send_message((int)$record->id, (int)$USER->id)) {
                $person['messageurl'] = (new \moodle_url('/message/index.php', ['id' => $record->id]))->out(false);
            }
            $person['profileurl'] = (new \moodle_url('/user/view.php', ['id' => $record->id, 'course' => $course->id]))
                ->out(false);
            $people[] = $person;
        }
        $more = max(0, $total - count($people));
        return [
            'show' => !empty($people),
            'list' => $people,
            'onlinelabel' => get_string('onlinecount', 'format_fredki', $online),
            'more' => $more,
            'hasmore' => $more > 0,
            'allurl' => (new \moodle_url('/user/index.php', ['id' => $course->id]))->out(false),
        ];
    }

    /**
     * Data for the side bar: the AI agents of the course ("The council").
     *
     * @param \stdClass $course Course.
     * @param \context_course $context Course context.
     * @param bool $hasfredki Whether FredKI answers in this course.
     * @return array|null Nothing when no AI takes part.
     */
    public static function export(\stdClass $course, \context_course $context, bool $hasfredki): ?array {
        // Either FredKI or the expert round: with FredKI the agents do not sit in the side bar.
        $agents = self::mode($course) === self::MODE_TEAM ? self::agents((int)$course->id) : [];
        $canmanage = self::has_teamagents() && has_capability('block/fredkiteamagents:manage', $context);
        $list = [];
        $active = 0;
        if ($hasfredki) {
            $list[] = ['isfredki' => true, 'name' => 'FredKI', 'role' => get_string('fredkirole', 'format_fredki'),
                'active' => true, 'ready' => true];
            $active++;
        }
        foreach ($agents as $agent) {
            if (!$agent['ready'] && !$canmanage) {
                continue;
            }
            $agent['canmanage'] = $canmanage && $agent['ready'];
            $agent['toggle'] = get_string($agent['active'] ? 'agentpause' : 'agentresume', 'format_fredki', $agent['name']);
            $agent['opacity'] = $agent['active'] && $agent['ready'] ? '1' : '.45';
            $active += $agent['active'] && $agent['ready'] ? 1 : 0;
            $list[] = $agent;
        }
        if (!$list) {
            return null;
        }
        return [
            'agents' => $list,
            'hasteamagents' => !empty($agents),
            'activelabel' => get_string('activecount', 'format_fredki', $active),
            'canconvene' => self::can_convene($course),
        ];
    }

    /**
     * Seconds the agents wait before they answer a new post, so the person can still correct it.
     * Set in the block FredKI Team Agents; two minutes when not set.
     *
     * @return int
     */
    public static function answer_delay(): int {
        $delay = get_config('block_fredkiteamagents', 'answerdelay');
        return $delay === false || $delay === '' ? 120 : max(0, (int)$delay);
    }
}
