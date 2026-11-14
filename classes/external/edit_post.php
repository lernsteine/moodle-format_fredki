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
 * Correct an own post in the learning space, e.g. a typo right after writing.
 *
 * @package    format_fredki
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_post extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'postid' => new external_value(PARAM_INT, 'Post id'),
            'text' => new external_value(PARAM_RAW, 'New text, empty to only load the current text', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Load or save the text of an own post.
     *
     * @param int $courseid Course id.
     * @param int $postid Post id.
     * @param string $text New text, empty to load.
     * @return array
     */
    public static function execute(int $courseid, int $postid, string $text = ''): array {
        global $DB;
        $params = self::validate_parameters(
            self::execute_parameters(),
            ['courseid' => $courseid, 'postid' => $postid, 'text' => $text]
        );
        $course = get_course($params['courseid']);
        self::validate_context(\context_course::instance($course->id));
        community::use_forum_of_post($course, $params['postid']);
        $community = community::require_forum($course);
        $post = $DB->get_record('forum_posts', ['id' => $params['postid'], 'deleted' => 0], '*', MUST_EXIST);
        $discussionid = (int)$DB->get_field(
            'forum_discussions',
            'id',
            ['id' => $post->discussion, 'forum' => $community['forum']->id],
            MUST_EXIST
        );
        if (!community::can_edit($post, community::get_ai_posts([(int)$post->id]))) {
            throw new \moodle_exception('cannotupdatepost', 'forum');
        }
        if (trim($params['text']) !== '') {
            if (\core_text::strlen($params['text']) > 12000) {
                throw new \moodle_exception('toolong', 'format_fredki');
            }
            community::edit_post($course, (int)$post->id, $params['text']);
            $post = $DB->get_record('forum_posts', ['id' => $post->id], '*', MUST_EXIST);
        }
        return ['discussionid' => $discussionid, 'text' => community::own_words($post), 'isopening' => empty($post->parent)];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'discussionid' => new external_value(PARAM_INT, 'Discussion id'),
            'text' => new external_value(PARAM_RAW, 'Own words of the post'),
            'isopening' => new external_value(PARAM_BOOL, 'Whether the post opens the conversation'),
        ]);
    }
}
