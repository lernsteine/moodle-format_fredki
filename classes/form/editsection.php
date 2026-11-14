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

namespace format_fredki\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/editsection_form.php');

use format_fredki\local\images;

/**
 * Section form with an own picture for the learning step.
 *
 * @package    format_fredki
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class editsection extends \editsection_form {
    /**
     * Fields: Moodle's, then the own picture right before the bundled photo choice.
     */
    public function definition() {
        parent::definition();
        $mform = $this->_form;
        $element = $mform->createElement(
            'filemanager',
            'fredkisectionimage',
            get_string('sectionimage', 'format_fredki'),
            null,
            images::section_upload_options()
        );
        if ($mform->elementExists('fredkiimage')) {
            $mform->insertElementBefore($element, 'fredkiimage');
        } else {
            $mform->addElement($element);
        }
        $mform->addHelpButton('fredkisectionimage', 'sectionimage', 'format_fredki');
    }

    /**
     * Fill the file manager with the picture saved so far.
     *
     * @param array|\stdClass $defaultvalues Values.
     */
    public function set_data($defaultvalues) {
        $defaultvalues = (array)$defaultvalues;
        $section = $this->_customdata['cs'];
        $draftid = file_get_submitted_draft_itemid('fredkisectionimage');
        file_prepare_draft_area(
            $draftid,
            \context_course::instance($section->course)->id,
            'format_fredki',
            images::SECTIONAREA,
            $section->id,
            images::section_upload_options()
        );
        $defaultvalues['fredkisectionimage'] = $draftid;
        parent::set_data($defaultvalues);
    }
}
