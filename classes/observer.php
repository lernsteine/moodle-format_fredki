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

namespace format_fredki;

use format_fredki\local\botuser;
use format_fredki\local\community;

/**
 * Event observers: prepare the forum when the format is chosen, sort conversations into topic fields
 * and let FredKI answer when someone writes @FredKI.
 *
 * @package    format_fredki
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Course of an event, if it uses this format.
     *
     * @param \core\event\base $event Event.
     * @return \stdClass|null
     */
    protected static function fredki_course(\core\event\base $event): ?\stdClass {
        global $DB;
        if (empty($event->courseid) || $event->courseid == SITEID) {
            return null;
        }
        $course = $DB->get_record('course', ['id' => $event->courseid]);
        return $course && $course->format === 'fredki' ? $course : null;
    }

    /**
     * Course created or updated: a course in this format gets its forum for the conversations.
     *
     * @param \core\event\base $event Event.
     */
    public static function course_changed(\core\event\base $event): void {
        $course = self::fredki_course($event);
        if (!$course) {
            return;
        }
        try {
            // A course in this format starts with a forum for its conversations.
            local\community::ensure_forum($course);
        } catch (\Throwable $e) {
            debugging('FredKI: forum for the conversations not created: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * A forum post mentions @FredKI: FredKI answers in the background if it was not answered already.
     *
     * @param \core\event\base $event Event.
     */
    public static function post_created(\core\event\base $event): void {
        global $DB;
        $course = self::fredki_course($event);
        if (!$course) {
            return;
        }
        $postid = $event instanceof \mod_forum\event\discussion_created
            ? (int)$DB->get_field('forum_discussions', 'firstpost', ['id' => $event->objectid])
            : (int)$event->objectid;
        $post = $postid ? $DB->get_record('forum_posts', ['id' => $postid], 'id, userid, message, discussion') : null;
        if ($post && \format_fredki\local\quality::by_person($post)) {
            // A new contribution of a person: the maturity of the conversation is looked at again a little later.
            \format_fredki\local\quality::queue((int)$post->discussion);
        }
        if ($post && $event instanceof \mod_forum\event\discussion_created) {
            // A new conversation: FredKI puts it into a topic field unless the learning space has done so already.
            \format_fredki\local\topics::queue((int)$post->discussion);
        }
        if (!$post || botuser::is_bot((int)$post->userid) || !community::mentions_fredki((string)$post->message)) {
            return;
        }
        $task = new \format_fredki\task\reply_to_mention();
        $task->set_custom_data(['postid' => $postid]);
        $task->set_component('format_fredki');
        // The browser usually asks FredKI right away; this is the fallback for posts in the forum itself.
        $task->set_next_run_time(time() + MINSECS);
        \core\task\manager::queue_adhoc_task($task, true);
    }

    /**
     * A post was changed or deleted: rate its conversation again.
     *
     * @param \core\event\base $event Event.
     */
    public static function post_changed(\core\event\base $event): void {
        if (!self::fredki_course($event)) {
            return;
        }
        $discussionid = (int)($event->other['discussionid'] ?? 0);
        if ($discussionid) {
            \format_fredki\local\quality::queue($discussionid);
        }
    }

    /**
     * A conversation was deleted: it leaves its topic field.
     *
     * @param \core\event\base $event Event.
     */
    public static function discussion_deleted(\core\event\base $event): void {
        global $DB;
        $DB->delete_records(\format_fredki\local\topics::MAP, ['discussionid' => (int)$event->objectid]);
    }
}
