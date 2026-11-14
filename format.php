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

/**
 * Course page of the FredKI format.
 *
 * Learners see the learning space (or one learning step). While editing, Moodle's standard course
 * editor is shown so that teachers keep all familiar tools.
 *
 * @package    format_fredki
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/completionlib.php');

$format = course_get_format($course);
$course = $format->get_course();
$context = context_course::instance($course->id);

if (($marker >= 0) && has_capability('moodle/course:setcurrentsection', $context) && confirm_sesskey()) {
    $course->marker = $marker;
    course_set_marker($course->id, $marker);
}

course_create_sections_if_missing($course, 0);

$renderer = $PAGE->get_renderer('format_fredki');

if ($PAGE->user_is_editing()) {
    // While editing, the standard Moodle view stays as it is.
    if (!is_null($displaysection)) {
        $format->set_sectionnum($displaysection);
    }
    $outputclass = $format->get_output_classname('content');
    $widget = new $outputclass($format);
    echo $renderer->render($widget);
} else {
    if (has_capability('moodle/course:manageactivities', $context ?? context_course::instance($course->id))) {
        // Courses that use this format from before: the first visit of a teacher adds the forum once.
        try {
            if (\format_fredki\local\community::ensure_forum($course)) {
                $course = get_course($course->id);
            }
        } catch (\Throwable $e) {
            debugging('FredKI: forum for the conversations not created: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
    $space = new \format_fredki\local\lernraum($course);
    if (!is_null($displaysection) && $displaysection > 0) {
        $data = $space->export_step((int)$displaysection, $renderer);
        echo $renderer->render_from_template('format_fredki/step', $data);
    } else {
        $data = $space->export_space($renderer);
        echo $renderer->render_from_template('format_fredki/lernraum', $data);
    }
    $PAGE->requires->strings_for_js(\format_fredki\local\lernraum::JS_STRINGS, 'format_fredki');
    $PAGE->requires->js_call_amd('format_fredki/lernraum', 'init', [$data['jsconfig']]);
}
