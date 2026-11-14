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
 * Learners who open the forum of the learning group (e.g. from a notification) land on the course page instead,
 * with the conversation open. Whoever may moderate the forum keeps the forum.
 *
 * @package    format_fredki
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class forumredirect {
    /**
     * Before the page is sent: redirect learners from the forum of the group to the course page.
     *
     * @param \core\hook\output\before_http_headers $hook Hook.
     */
    public static function before_http_headers(\core\hook\output\before_http_headers $hook): void {
        global $PAGE;
        if (
            during_initial_install() || CLI_SCRIPT || AJAX_SCRIPT || !in_array(
                $PAGE->pagetype,
                ['mod-forum-discuss', 'mod-forum-view'],
                true
            )
        ) {
            return;
        }
        $url = self::target($PAGE);
        if ($url) {
            redirect($url);
        }
    }

    /**
     * Where a learner should go instead of this forum page, if anywhere.
     *
     * @param \moodle_page $page Page.
     * @return \moodle_url|null
     */
    public static function target(\moodle_page $page): ?\moodle_url {
        try {
            $cm = $page->cm;
            $course = $page->course;
        } catch (\Throwable $e) {
            return null;
        }
        if (!$cm || $cm->modname !== 'forum' || empty($course->id) || $course->format !== 'fredki') {
            return null;
        }
        if (has_capability('mod/forum:editanypost', $page->context)) {
            return null;
        }
        $community = community::get_forum($course);
        if (!$community || (int)$community['cm']->id !== (int)$cm->id) {
            return null;
        }
        $url = new \moodle_url('/course/view.php', ['id' => $course->id]);
        if ($page->pagetype === 'mod-forum-discuss') {
            $discussionid = optional_param('d', 0, PARAM_INT);
            if ($discussionid) {
                $url->set_anchor('gespraech-' . $discussionid);
            }
        }
        return $url;
    }
}
