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
use format_fredki\local\lernraum;

/**
 * More cards for the learning wall: the next page, or the results of a search.
 *
 * @package    format_fredki
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_cards extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'forumid' => new external_value(
                PARAM_INT,
                'Forum of a learning step, 0 for the course conversations',
                VALUE_DEFAULT,
                0
            ),
            'offset' => new external_value(PARAM_INT, 'Cards already shown', VALUE_DEFAULT, 0),
            'query' => new external_value(PARAM_TEXT, 'Search text', VALUE_DEFAULT, ''),
            'topicid' => new external_value(PARAM_INT, 'Topic field: 0 all, -1 without topic field', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Export the cards.
     *
     * @param int $courseid Course id.
     * @param int $forumid Forum of a learning step, 0 for the course conversations.
     * @param int $offset Cards already shown.
     * @param string $query Search text.
     * @param int $topicid Topic field.
     * @return array
     */
    public static function execute(
        int $courseid,
        int $forumid = 0,
        int $offset = 0,
        string $query = '',
        int $topicid = 0
    ): array {
        global $PAGE;
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'forumid' => $forumid,
            'offset' => $offset,
            'query' => $query,
            'topicid' => $topicid,
        ]);
        $course = get_course($params['courseid']);
        self::validate_context(\context_course::instance($course->id));
        community::use_forum($course, $params['forumid']);
        $limit = (int)get_config('format_fredki', 'maxposts') ?: 12;
        $query = \core_text::substr(trim($params['query']), 0, 100);
        return (new lernraum($course))->export_more_cards(
            $PAGE->get_renderer('format_fredki'),
            max(0, $params['offset']),
            $limit,
            $query,
            $params['topicid']
        );
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cards' => new external_multiple_structure(structures::card()),
            'hasmore' => new external_value(PARAM_BOOL, 'Whether more cards can be loaded'),
        ]);
    }
}
