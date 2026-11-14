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
 * Overview for trainers: maturity of the conversations of the group (never per person).
 *
 * @package    format_fredki
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use format_fredki\local\community;
use format_fredki\local\quality;

$courseid = required_param('id', PARAM_INT);
$rate = optional_param('rate', 0, PARAM_INT);
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($course->id);
require_capability('moodle/course:update', $context);

$url = new moodle_url('/course/format/fredki/maturity.php', ['id' => $course->id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('maturity_report', 'format_fredki'));
$PAGE->set_heading(format_string($course->fullname, true, ['context' => $context]));

$community = community::get_forum($course);
if ($rate && confirm_sesskey() && $community) {
    // Ask for a fresh look at every conversation (runs in the background).
    foreach ($DB->get_fieldset_select('forum_discussions', 'id', 'forum = :forum', ['forum' => $community['forum']->id]) as $id) {
        quality::queue((int)$id);
    }
    redirect($url, get_string('maturity_queued', 'format_fredki'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('maturity_report', 'format_fredki'));
echo html_writer::tag('p', get_string('maturity_report_intro', 'format_fredki'), ['class' => 'text-muted']);

if (!quality::enabled($course)) {
    echo $OUTPUT->notification(get_string('maturity_off', 'format_fredki'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$overall = quality::course((int)$course->id);
if ($overall) {
    echo html_writer::tag('p', html_writer::tag('strong', get_string('maturity_course', 'format_fredki') . ': ')
        . s($overall['label']) . ' (' . $overall['score'] . ' / 100) · '
        . get_string('maturity_rated', 'format_fredki', $overall['conversations']));
}

$discussions = $community ? $DB->get_records(
    'forum_discussions',
    ['forum' => $community['forum']->id],
    'timemodified DESC',
    'id, name, timemodified'
) : [];
$ratings = quality::for_discussions(array_keys($discussions));
$table = new html_table();
$table->head = [
    get_string('maturity_col_conversation', 'format_fredki'),
    get_string('maturity_col_people', 'format_fredki'),
    get_string('maturity_col_posts', 'format_fredki'),
    get_string('maturity', 'format_fredki'),
];
foreach (quality::ASPECTS as $aspect) {
    $table->head[] = get_string('aspect_' . $aspect, 'format_fredki');
}
$table->head[] = get_string('maturity_next', 'format_fredki');
foreach ($discussions as $discussion) {
    $rating = $ratings[(int)$discussion->id] ?? null;
    $aspects = $rating ? (json_decode((string)$rating->aspects, true) ?: []) : [];
    $row = [
        format_string($discussion->name, true, ['context' => $context]),
        $rating ? (int)$rating->people : '–',
        $rating ? (int)$rating->posts : '–',
    ];
    if ($rating && $rating->score !== null) {
        $level = quality::level((int)$rating->score, (int)$course->id);
        $row[] = get_string('maturity_' . $level, 'format_fredki') . ' (' . (int)$rating->score . ')';
    } else {
        $row[] = get_string($rating ? 'maturity_tooearly' : 'maturity_notyet', 'format_fredki');
    }
    foreach (quality::ASPECTS as $aspect) {
        $row[] = isset($aspects[$aspect]) ? (int)$aspects[$aspect] . ' / 4' : '–';
    }
    $row[] = $rating ? s((string)$rating->hint) : '';
    $table->data[] = $row;
}
if ($table->data) {
    echo html_writer::div(html_writer::table($table), 'table-responsive');
} else {
    echo $OUTPUT->notification(get_string('maturity_none', 'format_fredki'), 'info');
}
echo $OUTPUT->single_button(
    new moodle_url($url, ['rate' => 1, 'sesskey' => sesskey()]),
    get_string('maturity_rateall', 'format_fredki'),
    'post'
);
echo html_writer::tag('p', get_string('maturity_note', 'format_fredki'), ['class' => 'text-muted mt-3']);
echo $OUTPUT->footer();
