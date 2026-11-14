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

namespace format_fredki\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use format_fredki\local\community;
use format_fredki\local\topics;

/**
 * Put a conversation into a topic field: chosen, new, or suggested by FredKI.
 *
 * @package    format_fredki
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_topic extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'discussionid' => new external_value(PARAM_INT, 'Conversation'),
            'topicid' => new external_value(PARAM_INT, 'Topic field, 0 for none', VALUE_DEFAULT, 0),
            'name' => new external_value(PARAM_TEXT, 'Name of a new topic field', VALUE_DEFAULT, ''),
            'suggest' => new external_value(PARAM_BOOL, 'Let FredKI choose', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Set the topic field.
     *
     * @param int $courseid Course.
     * @param int $discussionid Conversation.
     * @param int $topicid Topic field.
     * @param string $name New topic field.
     * @param bool $suggest Let FredKI choose.
     * @return array
     */
    public static function execute(
        int $courseid,
        int $discussionid,
        int $topicid = 0,
        string $name = '',
        bool $suggest = false
    ): array {
        global $DB;
        $params = self::validate_parameters(self::execute_parameters(), compact(
            'courseid',
            'discussionid',
            'topicid',
            'name',
            'suggest'
        ));
        $course = get_course($params['courseid']);
        self::validate_context(\context_course::instance($course->id));
        $community = community::require_forum($course);
        $discussion = $DB->get_record('forum_discussions', ['id' => $params['discussionid'],
            'forum' => $community['forum']->id], 'id, userid', MUST_EXIST);
        if (!topics::can_change($community['context'], (int)$discussion->userid)) {
            throw new \required_capability_exception($community['context'], 'mod/forum:editanypost', 'nopermissions', '');
        }
        $source = topics::can_manage($community['context']) ? 'trainer' : 'person';
        if ($params['suggest']) {
            \core\session\manager::write_close();
            $topic = topics::suggest($course, (int)$discussion->id, false);
        } else {
            $topic = trim($params['name']) !== '' ? topics::find_or_create((int)$course->id, $params['name'])
                : max(0, $params['topicid']);
            topics::assign((int)$course->id, (int)$discussion->id, $topic, $source);
        }
        $all = topics::get_all((int)$course->id);
        community::reset_topics();
        $counts = topics::counts((int)$course->id, (int)$community['forum']->id);
        return [
            'topicid' => isset($all[$topic]) ? $topic : 0,
            'topicname' => isset($all[$topic]) ? topics::name($all[$topic]) : '',
            'image' => ($picture = community::topic_picture((int)$course->id, (int)$discussion->id))[0],
            'imagestyle' => $picture[1],
            'topics' => topics::export_list($course, $counts),
            'without' => $counts[0] ?? 0,
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'topicid' => new external_value(PARAM_INT, 'Topic field, 0 for none'),
            'topicname' => new external_value(PARAM_TEXT, 'Name of the topic field'),
            'image' => new external_value(PARAM_URL, 'Picture of the card when the post has none of its own'),
            'imagestyle' => new external_value(PARAM_RAW, 'Section of that picture'),
            'topics' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Topic field'),
                'name' => new external_value(PARAM_TEXT, 'Name'),
                'count' => new external_value(PARAM_INT, 'Conversations'),
            ])),
            'without' => new external_value(PARAM_INT, 'Conversations without a topic field'),
        ]);
    }
}
