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
 * Own pictures of a topic field (or of conversations without one), uploaded by trainers.
 *
 * @package    format_fredki
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/formslib.php');

use format_fredki\local\community;
use format_fredki\local\images;
use format_fredki\local\topics;

$courseid = required_param('id', PARAM_INT);
$topicid = optional_param('topicid', 0, PARAM_INT);
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($course->id);
$community = community::require_forum($course);
if (!topics::can_manage($community['context'])) {
    require_capability('moodle/course:update', $context);
}
$topic = $topicid ? $DB->get_record(topics::TABLE, ['id' => $topicid, 'courseid' => $course->id], '*', MUST_EXIST) : null;
$name = $topic ? topics::name($topic) : get_string('topic_without', 'format_fredki');

$url = new moodle_url('/course/format/fredki/topicimages.php', ['id' => $course->id, 'topicid' => $topicid]);
$back = new moodle_url('/course/format/fredki/topics.php', ['id' => $course->id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('topicimages_title', 'format_fredki', $name));
$PAGE->set_heading(format_string($course->fullname, true, ['context' => $context]));

/**
 * Upload form.
 */
class format_fredki_topicimages_form extends moodleform {
    /**
     * Form fields.
     */
    protected function definition() {
        $this->_form->addElement(
            'filemanager',
            'pictures_filemanager',
            get_string('topicimages_files', 'format_fredki'),
            null,
            images::upload_options()
        );
        $this->_form->addHelpButton('pictures_filemanager', 'topicimages_files', 'format_fredki');
        $this->_form->addElement('hidden', 'id');
        $this->_form->setType('id', PARAM_INT);
        $this->_form->addElement('hidden', 'topicid');
        $this->_form->setType('topicid', PARAM_INT);
        $this->add_action_buttons(true);
    }
}

$data = (object)['id' => $course->id, 'topicid' => $topicid];
$data = file_prepare_standard_filemanager(
    $data,
    'pictures',
    images::upload_options(),
    $context,
    'format_fredki',
    images::FILEAREA,
    $topicid
);
$form = new format_fredki_topicimages_form($url);
$form->set_data($data);
if ($form->is_cancelled()) {
    redirect($back);
}
if ($submitted = $form->get_data()) {
    file_postupdate_standard_filemanager(
        $submitted,
        'pictures',
        images::upload_options(),
        $context,
        'format_fredki',
        images::FILEAREA,
        $topicid
    );
    redirect($back, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('topicimages_title', 'format_fredki', $name));
echo html_writer::tag('p', get_string('topicimages_intro', 'format_fredki'), ['class' => 'text-muted']);
$form->display();
echo $OUTPUT->footer();
