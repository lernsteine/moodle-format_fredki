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

/**
 * Conversations of the group that relate to a learner's question.
 *
 * @package    format_fredki
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class related extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'text' => new external_value(PARAM_RAW, 'Question'),
        ]);
    }

    /**
     * Find related conversations.
     *
     * @param int $courseid Course id.
     * @param string $text Question.
     * @return array
     */
    public static function execute(int $courseid, string $text): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'text' => $text,
        ]);
        $course = get_course($params['courseid']);
        self::validate_context(\context_course::instance($course->id));
        return community::related($course, \core_text::substr($params['text'], 0, 2000));
    }

    /**
     * Return structure.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(new external_single_structure([
            'discussionid' => new external_value(PARAM_INT, 'Discussion id'),
            'subject' => new external_value(PARAM_TEXT, 'Subject'),
            'firstname' => new external_value(PARAM_TEXT, 'First name of the author'),
            'url' => new external_value(PARAM_URL, 'Discussion URL'),
        ]));
    }
}
