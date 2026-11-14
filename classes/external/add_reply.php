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
use core_external\external_single_structure;
use core_external\external_value;
use format_fredki\local\community;

/**
 * Reply to a contribution of the group, in own words or by sharing a FredKI perspective.
 *
 * @package    format_fredki
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_reply extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'discussionid' => new external_value(PARAM_INT, 'Discussion id'),
            'text' => new external_value(PARAM_RAW, 'Reply text'),
            'isfredki' => new external_value(
                PARAM_BOOL,
                'Whether the reply shares a FredKI contribution',
                VALUE_DEFAULT,
                false
            ),
            'parentid' => new external_value(PARAM_INT, 'Post answered, 0 for the conversation', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Add the reply.
     *
     * @param int $courseid Course id.
     * @param int $discussionid Discussion id.
     * @param string $text Reply text.
     * @param bool $isfredki Whether the reply shares a FredKI contribution.
     * @param int $parentid Post answered, 0 for the conversation.
     * @return array
     */
    public static function execute(
        int $courseid,
        int $discussionid,
        string $text,
        bool $isfredki = false,
        int $parentid = 0
    ): array {
        global $DB;
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'discussionid' => $discussionid,
            'text' => $text,
            'isfredki' => $isfredki,
            'parentid' => $parentid,
        ]);
        $course = get_course($params['courseid']);
        $context = \context_course::instance($course->id);
        self::validate_context($context);
        community::use_forum_of_discussion($course, $params['discussionid']);
        if ($params['isfredki']) {
            require_capability('format/fredki:usefredki', $context);
        }
        if (\core_text::strlen($params['text']) > 12000) {
            throw new \moodle_exception('toolong', 'format_fredki');
        }
        $postid = community::add_reply(
            $course,
            $params['discussionid'],
            $params['text'],
            $params['isfredki'],
            $params['parentid']
        );
        $discussion = $DB->get_record('forum_discussions', ['id' => $params['discussionid']], 'id, firstpost');
        $parent = (int)$DB->get_field('forum_posts', 'parent', ['id' => $postid]);
        $replyto = '';
        if ($parent && $parent !== (int)$discussion->firstpost) {
            $parentpost = $DB->get_record('forum_posts', ['id' => $parent], 'id, userid');
            $agent = \format_fredki\local\council::agent_of((int)$course->id, (int)$parentpost->userid);
            $replyto = $agent ? $agent['name'] : (\format_fredki\local\botuser::is_bot((int)$parentpost->userid) ? 'FredKI'
                : (string)$DB->get_field('user', 'firstname', ['id' => $parentpost->userid]));
        }
        return [
            'postid' => $postid,
            'reply' => [
                'postid' => $postid,
                'likes' => 0,
                'liked' => false,
                'isfredki' => $params['isfredki'],
                'text' => shorten_text(trim(preg_replace('/\s+/u', ' ', $params['text'])), 180, true),
                'author' => community::export_user($USER),
                'isreplyto' => $replyto !== '',
                'replyto' => $replyto,
                'canedit' => !$params['isfredki'],
                'edituntil' => $params['isfredki'] ? 0 : time() + community::edit_window(),
            ],
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'postid' => new external_value(PARAM_INT, 'New post id'),
            'reply' => structures::reply(),
        ]);
    }
}
