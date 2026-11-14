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

namespace format_fredki\output;

use core_courseformat\output\section_renderer;
use moodle_page;

/**
 * Renderer of the FredKI course format.
 *
 * @package    format_fredki
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends section_renderer {
    /**
     * Constructor.
     *
     * @param moodle_page $page Page.
     * @param string $target Rendering target.
     */
    public function __construct(moodle_page $page, $target) {
        parent::__construct($page, $target);
        // Teachers who may only highlight the current step still get the editing switch.
        $page->set_other_editing_capability('moodle/course:setcurrentsection');
    }

    /**
     * Section title, editable in place.
     *
     * @param \section_info|\stdClass $section Section.
     * @param \stdClass $course Course.
     * @return string
     */
    public function section_title($section, $course) {
        return $this->render(course_get_format($course)->inplace_editable_render_section_name($section));
    }

    /**
     * Section title without link.
     *
     * @param \section_info|\stdClass $section Section.
     * @param int|\stdClass $course Course.
     * @return string
     */
    public function section_title_without_link($section, $course) {
        return $this->render(course_get_format($course)->inplace_editable_render_section_name($section, false));
    }
}
