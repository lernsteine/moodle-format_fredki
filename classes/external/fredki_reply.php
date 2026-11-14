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
use format_fredki\local\botuser;
use format_fredki\local\community;
use format_fredki\local\fredki;
use format_fredki\local\fredkiposter;

/**
 * FredKI replies publicly in a conversation of the group, like a participant.
 *
 * Mode "mention": FredKI answers a post that addressed @FredKI. Mode "share": FredKI publishes an
 * answer that the learner received privately; the answer is taken from the server, never from the browser.
 *
 * @package    format_fredki
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fredki_reply extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'discussionid' => new external_value(PARAM_INT, 'Discussion id'),
            'mode' => new external_value(PARAM_ALPHA, 'mention or share'),
            'postid' => new external_value(PARAM_INT, 'Post that mentions FredKI (mode mention)', VALUE_DEFAULT, 0),
            'token' => new external_value(PARAM_ALPHANUM, 'Token of a private answer (mode share)', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Let FredKI reply.
     *
     * @param int $courseid Course id.
     * @param int $discussionid Discussion id.
     * @param string $mode Mode.
     * @param int $postid Post id.
     * @param string $token Token.
     * @return array
     */
    public static function execute(int $courseid, int $discussionid, string $mode, int $postid = 0, string $token = ''): array {
        global $DB, $USER;
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'discussionid' => $discussionid,
            'mode' => $mode,
            'postid' => $postid,
            'token' => $token,
        ]);
        $course = get_course($params['courseid']);
        $context = \context_course::instance($course->id);
        self::validate_context($context);
        community::use_forum_of_discussion($course, $params['discussionid']);
        require_capability('format/fredki:usefredki', $context);
        if (!fredki::is_available($course)) {
            throw new \moodle_exception('nofredki', 'format_fredki');
        }
        $community = community::require_forum($course);
        $discussions = community::load_discussions($course, $community, 1, $params['discussionid']);
        if (!$discussions || !has_capability('mod/forum:replypost', $community['context'])) {
            throw new \moodle_exception('nopermissions', 'error', '', 'fredki');
        }
        $discussion = reset($discussions);

        $text = '';
        if ($params['mode'] === 'mention') {
            $post = $DB->get_record('forum_posts', ['id' => $params['postid'], 'discussion' => $discussion->id], '*', MUST_EXIST);
            // FredKI answers a post that mentions it, or one's own post when FredKI was chosen to answer.
            if (!community::mentions_fredki((string)$post->message) && (int)$post->userid !== (int)$USER->id) {
                throw new \moodle_exception('nomention', 'format_fredki');
            }
            // Free the session while FredKI thinks, so the person can go on using Moodle in other tabs and pages.
            \core\session\manager::write_close();
            $result = fredkiposter::answer_post((int)$post->id, 'mention');
            $text = $result['text'] ?? '';
        } else if ($params['mode'] === 'share') {
            $data = fredki::take_answer($params['token'], (int)$course->id);
            $own = (int)$discussion->userid === (int)$USER->id;
            if (!$data || !((int)$data['discussionid'] === (int)$discussion->id || ((int)$data['discussionid'] === 0 && $own))) {
                throw new \moodle_exception('answerexpired', 'format_fredki');
            }
            fredkiposter::share_answer($course, (int)$discussion->id, (string)$data['answer']);
            $text = (string)$data['answer'];
        } else {
            throw new \moodle_exception('invalidmode', 'format_fredki');
        }

        $bot = $DB->get_record('user', ['id' => botuser::get_id()]);
        return [
            'posted' => $text !== '',
            'reply' => [
                'isfredki' => true,
                'sharedby' => '',
                'issharedby' => false,
                'text' => shorten_text(community::plain(community::fredki_html($text), FORMAT_HTML), 400, true),
                'author' => community::export_user($bot),
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
            'posted' => new external_value(PARAM_BOOL, 'Whether FredKI posted a reply'),
            'reply' => structures::reply(),
        ]);
    }
}
