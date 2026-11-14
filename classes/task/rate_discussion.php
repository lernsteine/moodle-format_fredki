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

use format_fredki\local\quality;

/**
 * Look at the maturity of a conversation again (contributions of people only).
 *
 * @package    format_fredki
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rate_discussion extends \core\task\adhoc_task {
    /**
     * Name of the task.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_rate', 'format_fredki');
    }

    /**
     * Run the task.
     */
    public function execute() {
        $discussionid = (int)($this->get_custom_data()->discussionid ?? 0);
        try {
            quality::rate($discussionid);
        } catch (\dml_missing_record_exception $e) {
            // The conversation was deleted meanwhile.
            return;
        } catch (\Throwable $e) {
            mtrace('Maturity of conversation ' . $discussionid . ' could not be rated: ' . $e->getMessage());
        }
    }
}
