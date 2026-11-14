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
 * Take back an own post of the learning group shortly after writing it.
 *
 * @package    format_fredki
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_post extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'postid' => new external_value(PARAM_INT, 'Post id'),
        ]);
    }

    /**
     * Delete the post.
     *
     * @param int $courseid Course id.
     * @param int $postid Post id.
     * @return array
     */
    public static function execute(int $courseid, int $postid): array {
        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid, 'postid' => $postid]);
        $course = get_course($params['courseid']);
        self::validate_context(\context_course::instance($course->id));
        community::use_forum_of_post($course, $params['postid']);
        return community::delete_post($course, $params['postid']);
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'discussionid' => new external_value(PARAM_INT, 'Discussion id'),
            'discussiondeleted' => new external_value(PARAM_BOOL, 'Whether the whole conversation was removed'),
        ]);
    }
}
