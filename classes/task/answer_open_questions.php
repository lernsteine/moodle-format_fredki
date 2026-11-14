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

use format_fredki\local\community;
use format_fredki\local\fredki;
use format_fredki\local\fredkiposter;

/**
 * FredKI answers questions of the group that nobody answered for some hours.
 *
 * @package    format_fredki
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class answer_open_questions extends \core\task\scheduled_task {
    /**
     * Name of the task.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_openquestions', 'format_fredki');
    }

    /**
     * Run the task.
     */
    public function execute() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/format/lib.php');
        $courses = $DB->get_records('course', ['format' => 'fredki', 'visible' => 1], '', 'id');
        foreach ($courses as $c) {
            $course = get_course($c->id);
            $hours = (int)(course_get_format($course)->get_format_options()['fredkianswerafter'] ?? 0);
            if ($hours <= 0 || fredki::get_source($course) === '') {
                continue;
            }
            try {
                $community = community::require_forum_record($course);
            } catch (\moodle_exception $e) {
                continue;
            }
            foreach (community::find_open_questions($community, $hours) as $post) {
                try {
                    fredkiposter::answer_post((int)$post->id, 'answer');
                    mtrace('FredKI answered open question ' . $post->id . ' in course ' . $course->id);
                } catch (\Throwable $e) {
                    mtrace('FredKI could not answer question ' . $post->id . ': ' . $e->getMessage());
                    break;
                }
            }
        }
    }
}
