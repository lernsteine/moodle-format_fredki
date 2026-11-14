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
 * Topic fields for trainers: create, rename, order, merge and remove; sort conversations in.
 *
 * @package    format_fredki
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use format_fredki\local\community;
use format_fredki\local\fredki;
use format_fredki\local\images;
use format_fredki\local\quality;
use format_fredki\local\topics;

$courseid = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($course->id);
$community = community::require_forum($course);
if (!topics::can_manage($community['context'])) {
    require_capability('moodle/course:update', $context);
}

$url = new moodle_url('/course/format/fredki/topics.php', ['id' => $course->id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('topics_report', 'format_fredki'));
$PAGE->set_heading(format_string($course->fullname, true, ['context' => $context]));

if ($action !== '' && confirm_sesskey()) {
    $topicid = optional_param('topicid', 0, PARAM_INT);
    $message = get_string('changessaved');
    switch ($action) {
        case 'add':
            topics::find_or_create((int)$course->id, required_param('name', PARAM_TEXT));
            break;
        case 'rename':
            topics::rename((int)$course->id, $topicid, required_param('name', PARAM_TEXT));
            break;
        case 'merge':
            topics::merge((int)$course->id, $topicid, required_param('into', PARAM_INT));
            break;
        case 'delete':
            topics::delete((int)$course->id, $topicid);
            break;
        case 'image':
            topics::set_image((int)$course->id, $topicid, optional_param('image', '', PARAM_ALPHA));
            break;
        case 'up':
        case 'down':
            topics::move((int)$course->id, $topicid, $action === 'up' ? -1 : 1);
            break;
        case 'assign':
            $discussionid = required_param('discussionid', PARAM_INT);
            $DB->get_record('forum_discussions', ['id' => $discussionid, 'forum' => $community['forum']->id], 'id', MUST_EXIST);
            topics::assign((int)$course->id, $discussionid, $topicid, 'trainer');
            break;
        case 'sortin':
            // FredKI sorts every conversation without a topic field in, in the background.
            $sql = "SELECT d.id FROM {forum_discussions} d
                 LEFT JOIN {" . topics::MAP . "} m ON m.discussionid = d.id
                     WHERE d.forum = :forum AND m.id IS NULL";
            $ids = $DB->get_fieldset_sql($sql, ['forum' => $community['forum']->id]);
            foreach ($ids as $i => $id) {
                topics::queue((int)$id, 5 + $i * 2);
            }
            $message = get_string('topics_queued', 'format_fredki', count($ids));
            break;
    }
    redirect($url, $message, null, \core\output\notification::NOTIFY_SUCCESS);
}

/**
 * A small form with sesskey that posts back to this page.
 *
 * @param moodle_url $url Page.
 * @param array $fields Hidden fields.
 * @param string $inner Visible controls.
 * @return string
 */
function format_fredki_topics_form(moodle_url $url, array $fields, string $inner): string {
    $hidden = '';
    foreach ($fields + ['sesskey' => sesskey()] as $name => $value) {
        $hidden .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
    }
    return html_writer::tag('form', $hidden . $inner, ['method' => 'post', 'action' => $url->out(false),
        'class' => 'd-flex flex-wrap gap-2 align-items-center']);
}

$all = topics::get_all((int)$course->id);
$counts = topics::counts((int)$course->id, (int)$community['forum']->id);
$maturity = quality::enabled($course) ? topics::maturity((int)$course->id) : [];

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('topics_report', 'format_fredki'));
echo html_writer::tag('p', get_string('topics_report_intro', 'format_fredki'), ['class' => 'text-muted']);

// New topic field.
echo format_fredki_topics_form(
    $url,
    ['action' => 'add'],
    html_writer::label(get_string('topic_create', 'format_fredki'), 'ffk-topic-new', false, ['class' => 'sr-only'])
    . html_writer::empty_tag('input', [
        'type' => 'text', 'name' => 'name', 'id' => 'ffk-topic-new', 'class' => 'form-control w-auto',
        'maxlength' => topics::MAXNAME, 'required' => 'required', 'placeholder' => get_string('topic_create', 'format_fredki')])
    . html_writer::tag('button', get_string('add'), ['type' => 'submit', 'class' => 'btn btn-primary'])
);

$table = new html_table();
$table->attributes['class'] = 'generaltable mt-3';
$table->head = [
    get_string('topic', 'format_fredki'),
    get_string('topics_col_conversations', 'format_fredki'),
    get_string('topics_col_image', 'format_fredki'),
    get_string('maturity', 'format_fredki'),
    get_string('topics_col_order', 'format_fredki'),
    get_string('topics_col_merge', 'format_fredki'),
    '',
];
$ids = array_keys($all);
foreach ($all as $topic) {
    $id = (int)$topic->id;
    $rename = format_fredki_topics_form(
        $url,
        ['action' => 'rename', 'topicid' => $id],
        html_writer::label(get_string('topic_rename', 'format_fredki'), 'ffk-topic-' . $id, false, ['class' => 'sr-only'])
        . html_writer::empty_tag('input', ['type' => 'text', 'name' => 'name', 'id' => 'ffk-topic-' . $id,
            'value' => $topic->name, 'maxlength' => topics::MAXNAME, 'class' => 'form-control form-control-sm w-auto'])
        . html_writer::tag(
            'button',
            get_string('topic_rename', 'format_fredki'),
            ['type' => 'submit', 'class' => 'btn btn-sm btn-secondary']
        )
    );
    $order = '';
    if ($id !== reset($ids)) {
        $order .= format_fredki_topics_form(
            $url,
            ['action' => 'up', 'topicid' => $id],
            html_writer::tag('button', '↑', ['type' => 'submit', 'class' => 'btn btn-sm btn-link',
            'title' => get_string('moveup'),
            'aria-label' => get_string('moveup')])
        );
    }
    if ($id !== end($ids)) {
        $order .= format_fredki_topics_form(
            $url,
            ['action' => 'down', 'topicid' => $id],
            html_writer::tag('button', '↓', ['type' => 'submit', 'class' => 'btn btn-sm btn-link',
            'title' => get_string('movedown'),
            'aria-label' => get_string('movedown')])
        );
    }
    $others = [];
    foreach ($all as $other) {
        if ((int)$other->id !== $id) {
            $others[(int)$other->id] = topics::name($other);
        }
    }
    $merge = $others ? format_fredki_topics_form(
        $url,
        ['action' => 'merge', 'topicid' => $id],
        html_writer::label(get_string('topics_col_merge', 'format_fredki'), 'ffk-merge-' . $id, false, ['class' => 'sr-only'])
        . html_writer::select($others, 'into', '', false, [
            'id' => 'ffk-merge-' . $id, 'class' => 'form-select form-select-sm w-auto',
        ])
        . html_writer::tag(
            'button',
            get_string('topic_merge', 'format_fredki'),
            ['type' => 'submit', 'class' => 'btn btn-sm btn-secondary']
        )
    ) : '';
    $delete = format_fredki_topics_form(
        $url,
        ['action' => 'delete', 'topicid' => $id],
        html_writer::tag('button', get_string('delete'), ['type' => 'submit', 'class' => 'btn btn-sm btn-outline-danger',
        'title' => get_string(
            'topic_delete_hint',
            'format_fredki'
        )])
    );
    $level = isset($maturity[$id]) ? get_string('maturity_' . quality::level($maturity[$id], (int)$course->id), 'format_fredki')
        . ' (' . $maturity[$id] . ')' : '–';
    $photos = images::choices();
    $photo = format_fredki_topics_form(
        $url,
        ['action' => 'image', 'topicid' => $id],
        html_writer::empty_tag('img', ['src' => images::for_topic($topic), 'alt' => '', 'width' => 48, 'height' => 36,
            'style' => 'object-fit: cover; border-radius: 6px;'])
        . html_writer::label(get_string('topics_col_image', 'format_fredki'), 'ffk-image-' . $id, false, ['class' => 'sr-only'])
        . html_writer::select(
            $photos,
            'image',
            (string)($topic->image ?? ''),
            false,
            ['id' => 'ffk-image-' . $id, 'class' => 'form-select form-select-sm w-auto']
        )
        . html_writer::tag('button', get_string('savechanges'), ['type' => 'submit', 'class' => 'btn btn-sm btn-secondary'])
    );
    $own = count(images::uploads((int)$course->id, $id));
    $photo .= html_writer::link(
        new moodle_url('/course/format/fredki/topicimages.php', ['id' => $course->id, 'topicid' => $id]),
        get_string($own ? 'topicimages_link_count' : 'topicimages_link', 'format_fredki', $own),
        ['class' => 'd-block mt-1 small']
    );
    $table->data[] = [$rename, $counts[$id] ?? 0, $photo, s($level), $order, $merge, $delete];
}
if ($all) {
    echo html_writer::table($table);
}

// Conversations without a topic field.
$without = $counts[0] ?? 0;
echo $OUTPUT->heading(get_string('topic_without', 'format_fredki') . ' (' . $without . ')', 3, 'mt-4');
$ownwithout = count(images::uploads((int)$course->id, 0));
echo html_writer::tag('p', html_writer::link(new moodle_url(
    '/course/format/fredki/topicimages.php',
    ['id' => $course->id, 'topicid' => 0]
), get_string(
    $ownwithout ? 'topicimages_link_count' : 'topicimages_link',
    'format_fredki',
    $ownwithout
)) . ' – ' . get_string('topicimages_without_hint', 'format_fredki'), ['class' => 'small']);
if ($without && fredki::is_available($course)) {
    echo format_fredki_topics_form($url, ['action' => 'sortin'], html_writer::tag(
        'button',
        get_string('topics_sortin', 'format_fredki'),
        ['type' => 'submit', 'class' => 'btn btn-secondary']
    ));
}
if ($without && $all) {
    $sql = "SELECT d.id, d.name FROM {forum_discussions} d
         LEFT JOIN {" . topics::MAP . "} m ON m.discussionid = d.id
             WHERE d.forum = :forum AND m.id IS NULL
          ORDER BY d.timemodified DESC";
    $menu = array_map(fn($t) => topics::name($t), $all);
    $list = new html_table();
    $list->attributes['class'] = 'generaltable mt-3';
    $list->head = [get_string('maturity_col_conversation', 'format_fredki'), get_string('topic', 'format_fredki')];
    foreach ($DB->get_records_sql($sql, ['forum' => $community['forum']->id], 0, 100) as $d) {
        $list->data[] = [format_string($d->name), format_fredki_topics_form(
            $url,
            ['action' => 'assign', 'discussionid' => (int)$d->id],
            html_writer::label(get_string('topic', 'format_fredki'), 'ffk-assign-' . $d->id, false, ['class' => 'sr-only'])
            . html_writer::select($menu, 'topicid', '', false, ['id' => 'ffk-assign-' . $d->id,
                'class' => 'form-select form-select-sm w-auto'])
            . html_writer::tag(
                'button',
                get_string('topic_assign', 'format_fredki'),
                ['type' => 'submit', 'class' => 'btn btn-sm btn-secondary']
            )
        )];
    }
    echo html_writer::table($list);
}

echo html_writer::link(course_get_url($course), get_string('back'), ['class' => 'btn btn-link mt-3']);
echo $OUTPUT->footer();
