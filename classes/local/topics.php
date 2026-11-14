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
 * Topic fields of the learning space: conversations of the group bundled by theme.
 *
 * Only the learning space uses them; the forum itself stays as it is. FredKI suggests a topic field when a
 * conversation is shared, the author can change it, trainers rename, merge or create topic fields.
 *
 * @package    format_fredki
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class topics {
    /** @var string Topic fields of a course. */
    const TABLE = 'format_fredki_topic';

    /** @var string Which conversation belongs to which topic field. */
    const MAP = 'format_fredki_topicmap';

    /** @var int Topic fields FredKI may create on its own; beyond that it picks an existing one. */
    const MAXAI = 12;

    /** @var int Characters of a topic name. */
    const MAXNAME = 40;

    /** @var int Conversations looked at to build the shelves. */
    const SCAN = 300;

    /** @var int Cards on a shelf. */
    const SHELF = 3;

    /** @var array Topic of each discussion read during this request. */
    protected static $map = [];

    /**
     * Topic fields of a course, in their order.
     *
     * @param int $courseid Course.
     * @return \stdClass[] Keyed by id.
     */
    public static function get_all(int $courseid): array {
        global $DB;
        return $DB->get_records(self::TABLE, ['courseid' => $courseid], 'sortorder ASC, name ASC');
    }

    /**
     * Topic field of each conversation.
     *
     * @param int[] $discussionids Conversations.
     * @return int[] discussionid => topicid (missing: no topic field)
     */
    public static function of_discussions(array $discussionids): array {
        global $DB;
        $missing = array_values(array_diff(array_unique(array_map('intval', $discussionids)), array_keys(self::$map)));
        if ($missing) {
            [$insql, $params] = $DB->get_in_or_equal($missing, SQL_PARAMS_NAMED);
            foreach ($missing as $id) {
                self::$map[$id] = 0;
            }
            $mapped = $DB->get_records_select_menu(self::MAP, "discussionid $insql", $params, '', 'discussionid, topicid');
            foreach ($mapped as $discussionid => $topicid) {
                self::$map[(int)$discussionid] = (int)$topicid;
            }
        }
        $result = [];
        foreach ($discussionids as $id) {
            if (!empty(self::$map[(int)$id])) {
                $result[(int)$id] = self::$map[(int)$id];
            }
        }
        return $result;
    }

    /**
     * Put a conversation into a topic field (0: take it out).
     *
     * @param int $courseid Course.
     * @param int $discussionid Conversation.
     * @param int $topicid Topic field, 0 for none.
     * @param string $source ai, person or trainer.
     */
    public static function assign(int $courseid, int $discussionid, int $topicid, string $source): void {
        global $DB;
        $record = $DB->get_record(self::MAP, ['discussionid' => $discussionid]);
        if ($topicid <= 0) {
            if ($record) {
                $DB->delete_records(self::MAP, ['id' => $record->id]);
            }
            self::$map[$discussionid] = 0;
            return;
        }
        $DB->get_record(self::TABLE, ['id' => $topicid, 'courseid' => $courseid], 'id', MUST_EXIST);
        if ($record) {
            $record->topicid = $topicid;
            $record->source = $source;
            $record->timemodified = time();
            $DB->update_record(self::MAP, $record);
        } else {
            $DB->insert_record(self::MAP, (object)['courseid' => $courseid, 'discussionid' => $discussionid,
                'topicid' => $topicid, 'source' => $source, 'timemodified' => time()]);
        }
        self::$map[$discussionid] = $topicid;
    }

    /**
     * Clean a topic name.
     *
     * @param string $name Name.
     * @return string
     */
    public static function clean_name(string $name): string {
        $name = trim(clean_param($name, PARAM_TEXT), " \t\n\r\0\x0B\"'„“”«»#*.:;-");
        $name = preg_replace('/\s+/u', ' ', $name);
        return \core_text::substr($name, 0, self::MAXNAME);
    }

    /**
     * The topic field of this name, created when there is none yet.
     *
     * @param int $courseid Course.
     * @param string $name Name.
     * @return int Topic id, 0 for an empty name.
     */
    public static function find_or_create(int $courseid, string $name): int {
        global $DB;
        $name = self::clean_name($name);
        if ($name === '') {
            return 0;
        }
        foreach (self::get_all($courseid) as $topic) {
            if (\core_text::strtolower($topic->name) === \core_text::strtolower($name)) {
                return (int)$topic->id;
            }
        }
        $max = (int)$DB->get_field_sql('SELECT MAX(sortorder) FROM {' . self::TABLE . '} WHERE courseid = ?', [$courseid]);
        return (int)$DB->insert_record(self::TABLE, (object)['courseid' => $courseid, 'name' => $name,
            'sortorder' => $max + 1, 'timecreated' => time(), 'timemodified' => time()]);
    }

    /**
     * Rename a topic field.
     *
     * @param int $courseid Course.
     * @param int $topicid Topic field.
     * @param string $name New name.
     */
    public static function rename(int $courseid, int $topicid, string $name): void {
        global $DB;
        $name = self::clean_name($name);
        if ($name !== '') {
            $DB->set_field(self::TABLE, 'name', $name, ['id' => $topicid, 'courseid' => $courseid]);
            $DB->set_field(self::TABLE, 'timemodified', time(), ['id' => $topicid, 'courseid' => $courseid]);
        }
    }

    /**
     * Move all conversations of one topic field into another and remove the first.
     *
     * @param int $courseid Course.
     * @param int $from Topic field that disappears.
     * @param int $into Topic field that stays.
     */
    public static function merge(int $courseid, int $from, int $into): void {
        global $DB;
        if ($from === $into) {
            return;
        }
        $DB->get_record(self::TABLE, ['id' => $into, 'courseid' => $courseid], 'id', MUST_EXIST);
        $DB->set_field(self::MAP, 'topicid', $into, ['topicid' => $from, 'courseid' => $courseid]);
        $DB->delete_records(self::TABLE, ['id' => $from, 'courseid' => $courseid]);
        self::$map = [];
    }

    /**
     * Remove a topic field; its conversations stay, without a topic field.
     *
     * @param int $courseid Course.
     * @param int $topicid Topic field.
     */
    public static function delete(int $courseid, int $topicid): void {
        global $DB;
        $DB->delete_records(self::MAP, ['topicid' => $topicid, 'courseid' => $courseid]);
        $DB->delete_records(self::TABLE, ['id' => $topicid, 'courseid' => $courseid]);
        $contextid = \context_course::instance($courseid)->id;
        get_file_storage()->delete_area_files($contextid, 'format_fredki', images::FILEAREA, $topicid);
        self::$map = [];
    }

    /**
     * Choose the photo of a topic field ('' = automatic).
     *
     * @param int $courseid Course.
     * @param int $topicid Topic field.
     * @param string $image Photo key or ''.
     */
    public static function set_image(int $courseid, int $topicid, string $image): void {
        global $DB;
        $image = in_array($image, images::PHOTOS, true) ? $image : '';
        $DB->set_field(self::TABLE, 'image', $image, ['id' => $topicid, 'courseid' => $courseid]);
    }

    /**
     * Move a topic field up or down.
     *
     * @param int $courseid Course.
     * @param int $topicid Topic field.
     * @param int $direction -1 up, 1 down.
     */
    public static function move(int $courseid, int $topicid, int $direction): void {
        global $DB;
        $ids = array_keys(self::get_all($courseid));
        $pos = array_search($topicid, $ids);
        $other = $pos === false ? false : $pos + ($direction < 0 ? -1 : 1);
        if ($other === false || !isset($ids[$other])) {
            return;
        }
        [$ids[$pos], $ids[$other]] = [$ids[$other], $ids[$pos]];
        foreach ($ids as $i => $id) {
            $DB->set_field(self::TABLE, 'sortorder', $i + 1, ['id' => $id]);
        }
    }

    /**
     * Number of conversations per topic field (and those without).
     *
     * @param int $courseid Course.
     * @param int $forumid Forum of the group.
     * @return array [topicid => count, 0 => without]
     */
    public static function counts(int $courseid, int $forumid): array {
        global $DB;
        $sql = "SELECT COALESCE(m.topicid, 0) AS topicid, COUNT(1) AS c
                  FROM {forum_discussions} d
             LEFT JOIN {" . self::MAP . "} m ON m.discussionid = d.id
                 WHERE d.forum = :forumid
              GROUP BY COALESCE(m.topicid, 0)";
        $counts = [];
        foreach ($DB->get_records_sql($sql, ['forumid' => $forumid]) as $row) {
            $counts[(int)$row->topicid] = (int)$row->c;
        }
        return $counts;
    }

    /**
     * Maturity per topic field: mean of its rated conversations, weighted by their contributions.
     *
     * @param int $courseid Course.
     * @return array topicid => score
     */
    public static function maturity(int $courseid): array {
        global $DB;
        $sql = "SELECT m.topicid, SUM(q.score * CASE WHEN q.posts > 0 THEN q.posts ELSE 1 END) AS s,
                       SUM(CASE WHEN q.posts > 0 THEN q.posts ELSE 1 END) AS w
                  FROM {" . self::MAP . "} m
                  JOIN {" . quality::TABLE . "} q ON q.discussionid = m.discussionid
                 WHERE m.courseid = :courseid AND q.score IS NOT NULL
              GROUP BY m.topicid";
        $result = [];
        foreach ($DB->get_records_sql($sql, ['courseid' => $courseid]) as $row) {
            if ((int)$row->w > 0) {
                $result[(int)$row->topicid] = (int)round((int)$row->s / (int)$row->w);
            }
        }
        return $result;
    }

    /**
     * Whether someone may put a conversation into another topic field: its author or a trainer.
     *
     * @param \context $context Forum context.
     * @param int $authorid Author of the opening post.
     * @return bool
     */
    public static function can_change(\context $context, int $authorid): bool {
        global $USER;
        return ($authorid === (int)$USER->id && !isguestuser()) || self::can_manage($context);
    }

    /**
     * Whether someone may rename, merge and create topic fields.
     *
     * @param \context $context Forum or course context.
     * @return bool
     */
    public static function can_manage(\context $context): bool {
        return has_capability('mod/forum:editanypost', $context)
            || has_capability('moodle/course:update', $context->get_course_context());
    }

    /**
     * Ask FredKI to put a conversation into a fitting topic field (existing or new).
     *
     * @param \stdClass $course Course.
     * @param int $discussionid Conversation.
     * @param bool $keep Keep a topic field that is already set.
     * @return int Topic id, 0 when FredKI could not decide.
     */
    public static function suggest(\stdClass $course, int $discussionid, bool $keep = true): int {
        global $DB;
        if ($keep && ($current = self::of_discussions([$discussionid]))) {
            return reset($current);
        }
        if (!fredki::is_available($course)) {
            return 0;
        }
        // Conversations of a learning step stay with their step; topic fields bundle the course conversations.
        $forumid = (int)$DB->get_field('forum_discussions', 'forum', ['id' => $discussionid]);
        if ($forumid !== community::course_forum_id($course)) {
            return 0;
        }
        $discussion = $DB->get_record('forum_discussions', ['id' => $discussionid], 'id, name, firstpost', MUST_EXIST);
        $message = (string)$DB->get_field('forum_posts', 'message', ['id' => $discussion->firstpost]);
        $plain = html_entity_decode(strip_tags($message), ENT_QUOTES);
        $text = \core_text::substr(trim(preg_replace('/\s+/u', ' ', $plain)), 0, 1500);
        $topics = self::get_all((int)$course->id);
        $names = array_map(fn($t) => '- ' . $t->name, $topics);
        $rule = count($topics) >= self::MAXAI
            ? get_string('prompt_topic_existing', 'format_fredki')
            : get_string('prompt_topic_new', 'format_fredki');
        $prompt = str_replace(['{language}', '{topics}', '{rule}', '{title}', '{text}'], [
            fredki::answer_language($course, $discussion->name . ' ' . $text),
            $names ? implode("\n", $names) : '–',
            $rule,
            (string)$discussion->name,
            $text,
        ], str_replace('\\n', "\n", get_string('prompt_topic', 'format_fredki')));
        $name = self::parse(fredki::complete($course, $prompt));
        if ($name === '') {
            return 0;
        }
        if (count($topics) >= self::MAXAI) {
            // Only an existing topic field is allowed now.
            $match = array_filter($topics, fn($t) => \core_text::strtolower($t->name) === \core_text::strtolower($name));
            if (!$match) {
                return 0;
            }
        }
        $topicid = self::find_or_create((int)$course->id, $name);
        if ($topicid) {
            self::assign((int)$course->id, $discussionid, $topicid, 'ai');
        }
        return $topicid;
    }

    /**
     * Read FredKI's answer: JSON {"topic": "..."} or just the name.
     *
     * @param string $answer Answer.
     * @return string
     */
    public static function parse(string $answer): string {
        $answer = trim(preg_replace('/<think>.*?<\/think>/s', '', $answer));
        if (preg_match('/\{.*\}/s', $answer, $m)) {
            $data = json_decode($m[0], true);
            if (is_array($data) && isset($data['topic']) && is_string($data['topic'])) {
                return self::clean_name($data['topic']);
            }
        }
        $line = strtok($answer, "\n");
        return $line === false ? '' : self::clean_name(preg_replace('/^(topic|thema|themenfeld)\s*:\s*/iu', '', $line));
    }

    /**
     * Let FredKI sort a conversation in a little later (for conversations started in the forum itself).
     *
     * @param int $discussionid Conversation.
     * @param int $delay Seconds.
     */
    public static function queue(int $discussionid, int $delay = 120): void {
        $task = new \format_fredki\task\assign_topic();
        $task->set_custom_data(['discussionid' => $discussionid]);
        $task->set_component('format_fredki');
        $task->set_next_run_time(time() + $delay);
        \core\task\manager::queue_adhoc_task($task, true);
    }

    /**
     * Name of a topic field as plain text (templates escape it).
     *
     * @param \stdClass $topic Topic field.
     * @return string
     */
    public static function name(\stdClass $topic): string {
        return format_string($topic->name, true, ['escape' => false]);
    }

    /**
     * Export of the topic fields for chips and menus.
     *
     * @param \stdClass $course Course.
     * @param array $counts Result of counts().
     * @return array
     */
    public static function export_list(\stdClass $course, array $counts): array {
        $list = [];
        foreach (self::get_all((int)$course->id) as $topic) {
            $list[] = ['id' => (int)$topic->id, 'name' => self::name($topic), 'count' => $counts[(int)$topic->id] ?? 0];
        }
        return $list;
    }
}
