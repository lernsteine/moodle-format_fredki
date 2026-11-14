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
 * Maturity of a conversation: how far the group has developed a thought.
 *
 * An AI auditor reads only what people wrote (never posts of agents or FredKI), with pseudonyms instead of names,
 * and judges the conversation as a whole on five aspects. There is never a value for a single person.
 *
 * @package    format_fredki
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quality {
    /** @var string Table. */
    const TABLE = 'format_fredki_quality';

    /** @var string[] The five aspects, each rated 0–4. */
    const ASPECTS = ['perspectives', 'reasons', 'connection', 'depth', 'outcome'];

    /** @var int Contributions of people needed before a conversation is rated. */
    const MINPOSTS = 2;

    /** @var int Seconds to wait after a post, so a lively conversation is rated once, not after every post. */
    const DELAY = 300;

    /** @var int Characters of all contributions sent to the auditor. */
    const MAXCHARS = 12000;

    /**
     * Whether the maturity display is switched on in a course.
     *
     * @param \stdClass $course Course.
     * @return bool
     */
    public static function enabled(\stdClass $course): bool {
        global $CFG;
        require_once($CFG->dirroot . '/course/format/lib.php');
        $options = course_get_format($course)->get_format_options();
        return !empty($options['maturity']) && fredki::get_source($course) !== '';
    }

    /** @var array Where the levels exchange, deepening and developed begin, per demand set by the trainers. */
    const THRESHOLDS = [
        'low' => [20, 40, 60],
        'normal' => [25, 50, 75],
        'high' => [35, 60, 85],
    ];

    /** @var array Demand per course, read once per request. */
    protected static $demand = [];

    /**
     * Thresholds of a course.
     *
     * @param int $courseid Course, 0 for the standard.
     * @return int[] Start of exchange, deepening, developed.
     */
    public static function thresholds(int $courseid = 0): array {
        global $CFG;
        if ($courseid && !isset(self::$demand[$courseid])) {
            require_once($CFG->dirroot . '/course/lib.php');
            $options = course_get_format($courseid)->get_format_options();
            self::$demand[$courseid] = (string)($options['maturitydemand'] ?? 'normal');
        }
        $demand = $courseid ? self::$demand[$courseid] : 'normal';
        return self::THRESHOLDS[$demand] ?? self::THRESHOLDS['normal'];
    }

    /**
     * Level of a score.
     *
     * @param int $score 0–100.
     * @param int $courseid Course whose demand applies (0: standard).
     * @return string start, exchange, deepening or developed
     */
    public static function level(int $score, int $courseid = 0): string {
        [$exchange, $deepening, $developed] = self::thresholds($courseid);
        if ($score >= $developed) {
            return 'developed';
        }
        if ($score >= $deepening) {
            return 'deepening';
        }
        if ($score >= $exchange) {
            return 'exchange';
        }
        return 'start';
    }

    /**
     * Step 1–4 of a level, for the four marks on a card.
     *
     * @param string $level Level.
     * @return int
     */
    public static function step(string $level): int {
        return array_search($level, ['start', 'exchange', 'deepening', 'developed'], true) + 1;
    }

    /**
     * Angle of the needle of the gauge: -90° (empty) to 90° (full).
     *
     * @param int $score 0–100.
     * @return int
     */
    public static function angle(int $score): int {
        return (int)round(max(0, min(100, $score)) * 1.8 - 90);
    }

    /**
     * Ask for a new rating of a conversation a little later.
     *
     * @param int $discussionid Conversation.
     */
    public static function queue(int $discussionid): void {
        $task = new \format_fredki\task\rate_discussion();
        $task->set_custom_data(['discussionid' => $discussionid]);
        $task->set_component('format_fredki');
        $task->set_next_run_time(time() + self::DELAY);
        \core\task\manager::queue_adhoc_task($task, true);
    }

    /**
     * Whether a post was written by a person (not by FredKI, not by an agent, not a shared AI contribution).
     *
     * @param \stdClass $post Post with userid and id.
     * @param array $aiposts Post ids that share an AI contribution.
     * @return bool
     */
    public static function by_person(\stdClass $post, array $aiposts = []): bool {
        $userid = (int)$post->userid;
        if (botuser::is_bot($userid) || isset($aiposts[(int)$post->id])) {
            return false;
        }
        $agents = '\\block_fredkiteamagents\\local\\agents';
        return !(class_exists($agents) && $agents::is_agent_user($userid));
    }

    /**
     * The contributions of people in a conversation, with pseudonyms.
     *
     * @param int $discussionid Conversation.
     * @return array [topic, lines[], people, posts]
     */
    public static function contributions(int $discussionid): array {
        global $DB;
        $discussion = $DB->get_record('forum_discussions', ['id' => $discussionid], 'id, name', MUST_EXIST);
        $posts = $DB->get_records(
            'forum_posts',
            ['discussion' => $discussionid, 'deleted' => 0, 'privatereplyto' => 0],
            'created ASC, id ASC',
            'id, userid, message, created'
        );
        $aiposts = community::get_ai_posts(array_keys($posts));
        $names = [];
        $lines = [];
        foreach ($posts as $post) {
            if (!self::by_person($post, $aiposts)) {
                continue;
            }
            $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string)$post->message), ENT_QUOTES)));
            if ($text === '') {
                continue;
            }
            $userid = (int)$post->userid;
            if (!isset($names[$userid])) {
                $names[$userid] = get_string('person', 'format_fredki', chr(65 + min(25, count($names))));
            }
            $lines[] = $names[$userid] . ': ' . \core_text::substr($text, 0, 1500);
        }
        return [(string)$discussion->name, $lines, count($names), count($lines)];
    }

    /**
     * Rate a conversation now and store the result.
     *
     * @param int $discussionid Conversation.
     * @return \stdClass|null Stored record, null when there is too little to rate.
     */
    public static function rate(int $discussionid): ?\stdClass {
        global $DB;
        $discussion = $DB->get_record('forum_discussions', ['id' => $discussionid], 'id, course', MUST_EXIST);
        $course = get_course((int)$discussion->course);
        if (!self::enabled($course)) {
            return null;
        }
        [$topic, $lines, $people, $count] = self::contributions($discussionid);
        $record = $DB->get_record(self::TABLE, ['discussionid' => $discussionid]) ?: (object)[
            'courseid' => (int)$course->id, 'discussionid' => $discussionid];
        $record->people = $people;
        $record->posts = $count;
        $record->timemodified = time();
        if ($count < self::MINPOSTS) {
            $record->score = null;
            $record->aspects = '';
            $record->hint = '';
        } else {
            // Newest contributions count most when the conversation is long.
            $text = implode("\n", $lines);
            if (\core_text::strlen($text) > self::MAXCHARS) {
                $text = '…' . \core_text::substr($text, -self::MAXCHARS);
            }
            $prompt = str_replace(
                ['{language}', '{topic}', '{posts}'],
                [fredki::answer_language($course, $topic . ' ' . $text), $topic, $text],
                str_replace('\\n', "\n", get_string('prompt_audit', 'format_fredki'))
            );
            $result = self::parse(fredki::complete($course, $prompt));
            if (!$result) {
                throw new \moodle_exception('fredkierror', 'format_fredki');
            }
            $record->score = $result['score'];
            $record->aspects = json_encode($result['aspects']);
            $record->hint = $result['hint'];
        }
        if (!empty($record->id)) {
            $DB->update_record(self::TABLE, $record);
        } else {
            $record->id = $DB->insert_record(self::TABLE, $record);
        }
        return $record;
    }

    /**
     * Read the auditor's answer.
     *
     * @param string $answer Answer, JSON somewhere inside.
     * @return array|null ['score' => int, 'aspects' => array, 'hint' => string]
     */
    public static function parse(string $answer): ?array {
        if (!preg_match('/\{.*\}/s', $answer, $m)) {
            return null;
        }
        $data = json_decode($m[0], true);
        if (!is_array($data)) {
            return null;
        }
        $aspects = [];
        foreach (self::ASPECTS as $aspect) {
            if (!isset($data[$aspect]) || !is_numeric($data[$aspect])) {
                return null;
            }
            $aspects[$aspect] = max(0, min(4, (int)round((float)$data[$aspect])));
        }
        $hint = trim(clean_param((string)($data['hint'] ?? ''), PARAM_TEXT));
        return [
            'score' => (int)round(array_sum($aspects) / (4 * count(self::ASPECTS)) * 100),
            'aspects' => $aspects,
            'hint' => \core_text::substr($hint, 0, 300),
        ];
    }

    /**
     * Ratings of many conversations.
     *
     * @param int[] $discussionids Conversations.
     * @return \stdClass[] Keyed by discussion id.
     */
    public static function for_discussions(array $discussionids): array {
        global $DB;
        $discussionids = array_values(array_filter(array_unique(array_map('intval', $discussionids))));
        if (!$discussionids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($discussionids, SQL_PARAMS_NAMED);
        $result = [];
        foreach ($DB->get_records_select(self::TABLE, "discussionid $insql", $params) as $record) {
            $result[(int)$record->discussionid] = $record;
        }
        return $result;
    }

    /**
     * What a card or a conversation shows.
     *
     * @param \stdClass|null $record Rating.
     * @param bool $full With aspects and hint (conversation view).
     * @return array
     */
    public static function export(?\stdClass $record, bool $full = false): array {
        if (!$record || $record->score === null) {
            return ['hasmaturity' => false];
        }
        $score = (int)$record->score;
        $level = self::level($score, (int)($record->courseid ?? 0));
        $data = [
            'hasmaturity' => true,
            'maturityscore' => $score,
            'maturitylevel' => $level,
            'maturitylabel' => get_string('maturity_' . $level, 'format_fredki'),
            'maturitysteps' => array_map(fn($i) => ['on' => $i <= self::step($level)], [1, 2, 3, 4]),
            'maturityangle' => self::angle($score),
        ];
        if ($full) {
            $aspects = json_decode((string)$record->aspects, true) ?: [];
            $data['maturityaspects'] = array_map(fn($aspect) => [
                'name' => get_string('aspect_' . $aspect, 'format_fredki'),
                'value' => (int)($aspects[$aspect] ?? 0),
                'percent' => (int)($aspects[$aspect] ?? 0) * 25,
            ], self::ASPECTS);
            $data['maturityhint'] = (string)$record->hint;
            $data['hasmaturityhint'] = trim((string)$record->hint) !== '';
        }
        return $data;
    }

    /**
     * Maturity of the whole course: mean of all rated conversations, weighted by their contributions.
     *
     * @param int $courseid Course.
     * @return array|null ['score' => int, 'level' => string, 'conversations' => int]
     */
    public static function course(int $courseid): ?array {
        global $DB;
        $records = $DB->get_records_select(
            self::TABLE,
            'courseid = :courseid AND score IS NOT NULL',
            ['courseid' => $courseid],
            '',
            'id, score, posts'
        );
        if (!$records) {
            return null;
        }
        $sum = 0;
        $weight = 0;
        foreach ($records as $record) {
            $sum += (int)$record->score * max(1, (int)$record->posts);
            $weight += max(1, (int)$record->posts);
        }
        $score = (int)round($sum / $weight);
        $level = self::level($score, $courseid);
        return ['score' => $score, 'level' => $level, 'label' => get_string('maturity_' . $level, 'format_fredki'),
            'angle' => self::angle($score), 'conversations' => count($records)];
    }
}
