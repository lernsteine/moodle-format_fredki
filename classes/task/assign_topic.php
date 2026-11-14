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

namespace format_fredki\task;

use format_fredki\local\topics;

/**
 * Let FredKI put a conversation into a topic field, unless someone has already done so.
 *
 * @package    format_fredki
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_topic extends \core\task\adhoc_task {
    /**
     * Name of the task.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_topic', 'format_fredki');
    }

    /**
     * Run the task.
     */
    public function execute() {
        global $DB;
        $discussionid = (int)($this->get_custom_data()->discussionid ?? 0);
        $courseid = (int)$DB->get_field('forum_discussions', 'course', ['id' => $discussionid]);
        if (!$courseid) {
            return;
        }
        try {
            topics::suggest(get_course($courseid), $discussionid);
        } catch (\Throwable $e) {
            mtrace('Topic of conversation ' . $discussionid . ' could not be suggested: ' . $e->getMessage());
        }
    }
}
