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

use format_fredki\local\fredkiposter;

/**
 * FredKI answers a post that mentions @FredKI, also when it was written in the forum itself.
 *
 * @package    format_fredki
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reply_to_mention extends \core\task\adhoc_task {
    /**
     * Name of the task.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_mention', 'format_fredki');
    }

    /**
     * Run the task.
     */
    public function execute() {
        $data = $this->get_custom_data();
        try {
            fredkiposter::answer_post((int)($data->postid ?? 0), 'mention');
        } catch (\Throwable $e) {
            mtrace('FredKI could not answer post ' . (int)($data->postid ?? 0) . ': ' . $e->getMessage());
        }
    }
}
