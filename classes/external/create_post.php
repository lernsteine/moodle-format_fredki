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
use format_fredki\local\lernraum;

/**
 * Share a thought, optionally together with a FredKI answer, with the learning group.
 *
 * @package    format_fredki
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_post extends external_api {
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
            'text' => new external_value(PARAM_RAW, 'Own words', VALUE_DEFAULT, ''),
            'question' => new external_value(PARAM_RAW, 'Question asked to FredKI', VALUE_DEFAULT, ''),
            'answer' => new external_value(PARAM_RAW, 'Answer of FredKI', VALUE_DEFAULT, ''),
            'type' => new external_value(PARAM_ALPHA, 'Kind: question, idea or experience', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Create the post and return its card.
     *
     * @param int $courseid Course id.
     * @param int $forumid Forum of a learning step, 0 for the course conversations.
     * @param string $text Own words.
     * @param string $question Question asked to FredKI.
     * @param string $answer Answer of FredKI.
     * @param string $type Kind of contribution.
     * @return array
     */
    public static function execute(
        int $courseid,
        int $forumid = 0,
        string $text = '',
        string $question = '',
        string $answer = '',
        string $type = ''
    ): array {
        global $PAGE;
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'forumid' => $forumid,
            'text' => $text,
            'question' => $question,
            'answer' => $answer,
            'type' => $type,
        ]);
        $course = get_course($params['courseid']);
        $context = \context_course::instance($course->id);
        self::validate_context($context);
        community::use_forum($course, $params['forumid']);
        if (trim($params['answer']) !== '') {
            require_capability('format/fredki:usefredki', $context);
        }
        foreach (['text' => 4000, 'question' => 4000, 'answer' => 12000] as $field => $max) {
            if (\core_text::strlen($params[$field]) > $max) {
                throw new \moodle_exception('toolong', 'format_fredki');
            }
        }

        $discussionid = community::create_discussion(
            $course,
            $params['text'],
            $params['question'],
            $params['answer'],
            $params['type']
        );

        $community = community::require_forum($course);
        $discussions = community::load_discussions($course, $community, 1, $discussionid);
        $discussion = reset($discussions);
        $space = new lernraum($course);
        $output = $PAGE->get_renderer('format_fredki');
        $likes = community::get_likes([(int)$discussion->firstpost]);
        $aiposts = community::get_ai_posts([(int)$discussion->firstpost]);
        $card = community::export_card(
            $discussion,
            [],
            $community,
            $likes,
            $aiposts,
            $space->default_images($output),
            random_int(0, 5),
            community::get_meta([(int)$discussion->firstpost])
        );
        return ['card' => $card];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'card' => structures::card(),
        ]);
    }
}
