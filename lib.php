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
 * FredKI course format: an image-led learning space where learners, their group and FredKI talk together.
 *
 * @package    format_fredki
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/format/lib.php');

use core\output\inplace_editable;

/**
 * Main class of the FredKI course format.
 *
 * @package    format_fredki
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_fredki extends core_courseformat\base {
    /**
     * This format uses sections.
     *
     * @return bool
     */
    public function uses_sections() {
        return true;
    }

    /**
     * The course index is only shown while editing, so learners see a calm learning space.
     *
     * @return bool
     */
    public function uses_course_index() {
        global $PAGE;
        return !empty($PAGE) && $PAGE->user_is_editing();
    }

    /**
     * Indentation of activities is not used.
     *
     * @return bool
     */
    public function uses_indentation(): bool {
        return false;
    }

    /**
     * Display name of a section.
     *
     * @param int|stdClass|section_info $section Section.
     * @return string
     */
    public function get_section_name($section) {
        $section = $this->get_section($section);
        if ((string)$section->name !== '') {
            return format_string($section->name, true, ['context' => context_course::instance($this->courseid)]);
        }
        return $this->get_default_section_name($section);
    }

    /**
     * Default name of a section.
     *
     * @param int|stdClass|section_info $section Section.
     * @return string
     */
    public function get_default_section_name($section) {
        $section = $this->get_section($section);
        if ($section->sectionnum == 0) {
            return get_string('section0name', 'format_fredki');
        }
        return get_string('newsection', 'format_fredki');
    }

    /**
     * Page title of a section page.
     *
     * @return string
     */
    public function page_title(): string {
        return get_string('sectionoutline');
    }

    /**
     * URL of the course or of one learning step.
     *
     * Learners open a learning step on the course page itself (course/view.php?section=n), where the
     * format shows its own step view. While editing, the standard section page is used.
     *
     * @param int|stdClass|null $section Section.
     * @param array $options Options, see core_courseformat\base::get_view_url().
     * @return null|moodle_url
     */
    public function get_view_url($section, $options = []) {
        global $PAGE;
        $course = $this->get_course();
        if (array_key_exists('sr', $options) && !is_null($options['sr'])) {
            $sectionno = $options['sr'];
        } else if (is_object($section)) {
            $sectionno = $section->section;
        } else {
            $sectionno = $section;
        }
        if ((!empty($options['navigation']) || array_key_exists('sr', $options)) && $sectionno !== null) {
            if (!empty($PAGE) && $PAGE->user_is_editing()) {
                $sectioninfo = $this->get_section($sectionno);
                return new moodle_url('/course/section.php', ['id' => $sectioninfo->id]);
            }
            if ($sectionno > 0) {
                return new moodle_url('/course/view.php', ['id' => $course->id, 'section' => $sectionno]);
            }
        }
        return new moodle_url('/course/view.php', ['id' => $course->id]);
    }

    /**
     * AJAX support for editing.
     *
     * @return stdClass
     */
    public function supports_ajax() {
        $ajaxsupport = new stdClass();
        $ajaxsupport->capable = true;
        return $ajaxsupport;
    }

    /**
     * The reactive course editor is used while editing.
     *
     * @return bool
     */
    public function supports_components() {
        return true;
    }

    /**
     * No blocks are added to new courses.
     *
     * @return array
     */
    public function get_default_blocks() {
        return [
            BLOCK_POS_LEFT => [],
            BLOCK_POS_RIGHT => [],
        ];
    }

    /**
     * Mark course pages of this format so that the theme chrome can be calmed down.
     *
     * @param moodle_page $page Page.
     */
    public function page_set_course(moodle_page $page) {
        $options = $this->get_format_options();
        if (!empty($options['immersive'])) {
            $page->add_body_class('fredki-immersive');
        }
    }

    /**
     * Course format options.
     *
     * @param bool $foreditform Whether the options are requested for the edit form.
     * @return array
     */
    public function course_format_options($foreditform = false) {
        $options = [
            'heroquestion' => ['default' => '', 'type' => PARAM_TEXT],
            'heroimage' => ['default' => '', 'type' => PARAM_ALPHA],
            'communityforum' => ['default' => 0, 'type' => PARAM_INT],
            'aimode' => ['default' => 'auto', 'type' => PARAM_ALPHA],
            'fredkibot' => ['default' => 0, 'type' => PARAM_INT],
            'fredkiquestions' => ['default' => 1, 'type' => PARAM_BOOL],
            'fredkianswerafter' => ['default' => 12, 'type' => PARAM_INT],
            'definitions' => ['default' => 1, 'type' => PARAM_BOOL],
            'maturity' => ['default' => 1, 'type' => PARAM_BOOL],
            'maturitydemand' => ['default' => 'normal', 'type' => PARAM_ALPHA],
            'definitionsglossary' => ['default' => 0, 'type' => PARAM_INT],
            'forumprepared' => ['default' => 0, 'type' => PARAM_INT],
            'impulse' => ['default' => '', 'type' => PARAM_TEXT],
            'showpresence' => ['default' => 1, 'type' => PARAM_BOOL],
            'immersive' => ['default' => 1, 'type' => PARAM_BOOL],
            'cardstyle' => ['default' => 'light', 'type' => PARAM_ALPHA],
            'privacynote' => ['default' => '', 'type' => PARAM_RAW],
        ];
        if (!$foreditform) {
            return $options;
        }
        $edit = [
            'heroquestion' => [
                'label' => new lang_string('heroquestion', 'format_fredki'),
                'help' => 'heroquestion',
                'help_component' => 'format_fredki',
                'element_type' => 'text',
                'element_attributes' => [['size' => 60, 'maxlength' => 160]],
            ],
            'heroimage' => [
                'label' => new lang_string('heroimage', 'format_fredki'),
                'help' => 'heroimage',
                'help_component' => 'format_fredki',
                'element_type' => 'select',
                'element_attributes' => [\format_fredki\local\images::choices()],
            ],
            'communityforum' => [
                'label' => new lang_string('communityforum', 'format_fredki'),
                'help' => 'communityforum',
                'help_component' => 'format_fredki',
                'element_type' => 'select',
                'element_attributes' => [\format_fredki\local\community::get_forum_choices((int)$this->courseid)],
            ],
            'aimode' => [
                'label' => new lang_string('aimode', 'format_fredki'),
                'help' => 'aimode',
                'help_component' => 'format_fredki',
                'element_type' => 'select',
                'element_attributes' => [[
                    'auto' => new lang_string('aimode_auto', 'format_fredki'),
                    'fredki' => new lang_string('aimode_fredki', 'format_fredki'),
                    'team' => new lang_string('aimode_team', 'format_fredki'),
                ]],
            ],
            // Only with the plugin FredKI Chat (local_fredkichat); otherwise there is nothing to choose.
            'fredkibot' => \format_fredki\local\fredki::design_installed() ? [
                'label' => new lang_string('fredkibot', 'format_fredki'),
                'help' => 'fredkibot',
                'help_component' => 'format_fredki',
                'element_type' => 'select',
                'element_attributes' => [\format_fredki\local\fredki::get_bot_choices((int)$this->courseid)],
            ] : ['label' => '', 'element_type' => 'hidden'],
            'fredkiquestions' => [
                'label' => new lang_string('fredkiquestions', 'format_fredki'),
                'help' => 'fredkiquestions',
                'help_component' => 'format_fredki',
                'element_type' => 'advcheckbox',
            ],
            'fredkianswerafter' => [
                'label' => new lang_string('fredkianswerafter', 'format_fredki'),
                'help' => 'fredkianswerafter',
                'help_component' => 'format_fredki',
                'element_type' => 'select',
                'element_attributes' => [[
                    0 => new lang_string('fredkianswer_never', 'format_fredki'),
                    2 => new lang_string('fredkianswer_hours', 'format_fredki', 2),
                    6 => new lang_string('fredkianswer_hours', 'format_fredki', 6),
                    12 => new lang_string('fredkianswer_hours', 'format_fredki', 12),
                    24 => new lang_string('fredkianswer_hours', 'format_fredki', 24),
                    48 => new lang_string('fredkianswer_hours', 'format_fredki', 48),
                ]],
            ],
            'definitions' => [
                'label' => new lang_string('definitions', 'format_fredki'),
                'help' => 'definitions',
                'help_component' => 'format_fredki',
                'element_type' => 'advcheckbox',
            ],
            'maturity' => [
                'label' => new lang_string('maturity', 'format_fredki'),
                'help' => 'maturity',
                'help_component' => 'format_fredki',
                'element_type' => 'advcheckbox',
            ],
            'maturitydemand' => [
                'label' => new lang_string('maturitydemand', 'format_fredki'),
                'help' => 'maturitydemand',
                'help_component' => 'format_fredki',
                'element_type' => 'select',
                'element_attributes' => [[
                    'low' => new lang_string('maturitydemand_low', 'format_fredki'),
                    'normal' => new lang_string('maturitydemand_normal', 'format_fredki'),
                    'high' => new lang_string('maturitydemand_high', 'format_fredki'),
                ]],
            ],
            'definitionsglossary' => [
                // Set by the learning space when it creates the glossary for the definitions; not for editing.
                'label' => '',
                'element_type' => 'hidden',
            ],
            'forumprepared' => [
                // Set once the course got its forum for the conversations (or had one already); not for editing.
                'label' => '',
                'element_type' => 'hidden',
            ],
            'impulse' => [
                'label' => new lang_string('impulse', 'format_fredki'),
                'help' => 'impulse',
                'help_component' => 'format_fredki',
                'element_type' => 'text',
                'element_attributes' => [['size' => 60, 'maxlength' => 200]],
            ],
            'showpresence' => [
                'label' => new lang_string('showpresence', 'format_fredki'),
                'help' => 'showpresence',
                'help_component' => 'format_fredki',
                'element_type' => 'advcheckbox',
            ],
            'immersive' => [
                'label' => new lang_string('immersive', 'format_fredki'),
                'help' => 'immersive',
                'help_component' => 'format_fredki',
                'element_type' => 'advcheckbox',
            ],
            'privacynote' => [
                'label' => new lang_string('privacynote', 'format_fredki'),
                'help' => 'privacynote',
                'help_component' => 'format_fredki',
                'element_type' => 'textarea',
                'element_attributes' => [['rows' => 8, 'cols' => 70]],
            ],
            'cardstyle' => [
                'label' => new lang_string('cardstyle', 'format_fredki'),
                'help' => 'cardstyle',
                'help_component' => 'format_fredki',
                'element_type' => 'select',
                'element_attributes' => [[
                    'light' => new lang_string('cardstyle_light', 'format_fredki'),
                    'image' => new lang_string('cardstyle_image', 'format_fredki'),
                    'calm' => new lang_string('cardstyle_calm', 'format_fredki'),
                    'clear' => new lang_string('cardstyle_clear', 'format_fredki'),
                ]],
            ],
        ];
        return array_merge_recursive($options, $edit);
    }

    /**
     * Section form with an own picture for the learning step.
     *
     * @param mixed $action Form action.
     * @param array $customdata Custom data.
     * @return moodleform
     */
    public function editsection_form($action, $customdata = []) {
        if (!array_key_exists('course', $customdata)) {
            $customdata['course'] = $this->get_course();
        }
        return new \format_fredki\form\editsection($action, $customdata);
    }

    /**
     * Save the section options and the own picture of the learning step.
     *
     * @param stdClass|array $data Form data.
     * @return bool
     */
    public function update_section_format_options($data) {
        $data = (array)$data;
        if (isset($data['fredkisectionimage']) && !empty($data['id'])) {
            $context = context_course::instance($this->get_courseid());
            file_save_draft_area_files(
                (int)$data['fredkisectionimage'],
                $context->id,
                'format_fredki',
                \format_fredki\local\images::SECTIONAREA,
                (int)$data['id'],
                \format_fredki\local\images::section_upload_options()
            );
        }
        unset($data['fredkisectionimage']);
        return parent::update_section_format_options($data);
    }

    /**
     * Section format options: starter questions for FredKI on a learning step.
     *
     * @param bool $foreditform Whether the options are requested for the edit form.
     * @return array
     */
    public function section_format_options($foreditform = false) {
        $options = [
            'fredkiimage' => ['default' => '', 'type' => PARAM_ALPHA],
            'fredkistarters' => ['default' => '', 'type' => PARAM_TEXT],
            'stepforum' => ['default' => 'auto', 'type' => PARAM_ALPHA],
        ];
        if ($foreditform) {
            $options['stepforum'] += [
                'label' => new lang_string('stepforum', 'format_fredki'),
                'help' => 'stepforum',
                'help_component' => 'format_fredki',
                'element_type' => 'select',
                'element_attributes' => [[
                    'auto' => new lang_string('stepforum_auto', 'format_fredki'),
                    'off' => new lang_string('stepforum_off', 'format_fredki'),
                ]],
            ];
            $options['fredkiimage'] += [
                'label' => new lang_string('fredkiimage', 'format_fredki'),
                'help' => 'fredkiimage',
                'help_component' => 'format_fredki',
                'element_type' => 'select',
                'element_attributes' => [\format_fredki\local\images::choices()],
            ];
            $options['fredkistarters'] += [
                'label' => new lang_string('fredkistarters', 'format_fredki'),
                'help' => 'fredkistarters',
                'help_component' => 'format_fredki',
                'element_type' => 'textarea',
                'element_attributes' => [['rows' => 3, 'cols' => 60]],
            ];
        }
        return $options;
    }

    /**
     * Add the "number of sections" element to the course creation form.
     *
     * @param MoodleQuickForm $mform Form.
     * @param bool $forsection Whether this is the section edit form.
     * @return array
     */
    public function create_edit_form_elements(&$mform, $forsection = false) {
        global $COURSE;
        $elements = parent::create_edit_form_elements($mform, $forsection);
        if (!$forsection && (empty($COURSE->id) || $COURSE->id == SITEID)) {
            $courseconfig = get_config('moodlecourse');
            $max = (int)$courseconfig->maxsections;
            $element = $mform->addElement('select', 'numsections', get_string('numberweeks'), range(0, $max ?: 52));
            $mform->setType('numsections', PARAM_INT);
            if (is_null($mform->getElementValue('numsections'))) {
                $mform->setDefault('numsections', 4);
            }
            array_unshift($elements, $element);
        }
        return $elements;
    }

    /**
     * Update course format options, keeping values that are not part of the submitted data.
     *
     * @param stdClass|array $data Submitted data.
     * @param stdClass|null $oldcourse Course before the update.
     * @return bool
     */
    public function update_course_format_options($data, $oldcourse = null) {
        $data = (array)$data;
        if ($oldcourse !== null) {
            $oldcourse = (array)$oldcourse;
            foreach ($this->course_format_options() as $key => $unused) {
                if (!array_key_exists($key, $data) && array_key_exists($key, $oldcourse)) {
                    $data[$key] = $oldcourse[$key];
                }
            }
        }
        return $this->update_format_options($data);
    }

    /**
     * Sections may be deleted.
     *
     * @param int|stdClass|section_info $section Section.
     * @return bool
     */
    public function can_delete_section($section) {
        return true;
    }

    /**
     * A news forum may be created.
     *
     * @return bool
     */
    public function supports_news() {
        return true;
    }

    /**
     * Allow the "available but not shown" visibility state.
     *
     * @param stdClass|cm_info $cm Course module.
     * @param stdClass|section_info $section Section.
     * @return bool
     */
    public function allow_stealth_module_visibility($cm, $section) {
        return !$section->section || $section->visible;
    }

    /**
     * Section actions from the course editor, including setting the current learning step.
     *
     * @param section_info|stdClass $section Section.
     * @param string $action Action.
     * @param int $sr Section to return to.
     * @return null|array
     */
    public function section_action($section, $action, $sr) {
        global $PAGE;
        if ($section->section && ($action === 'setmarker' || $action === 'removemarker')) {
            require_capability('moodle/course:setcurrentsection', context_course::instance($this->courseid));
            course_set_marker($this->courseid, ($action === 'setmarker') ? $section->section : 0);
            return null;
        }
        $rv = parent::section_action($section, $action, $sr);
        $renderer = $PAGE->get_renderer('format_fredki');
        if (!($section instanceof section_info)) {
            $modinfo = course_modinfo::instance($this->courseid);
            $section = $modinfo->get_section_info($section->section);
        }
        $elementclass = $this->get_output_classname('content\\section\\availability');
        $availability = new $elementclass($this, $section);
        $rv['section_availability'] = $renderer->render($availability);
        return $rv;
    }

    /**
     * Format options for web services.
     *
     * @return array
     */
    public function get_config_for_external() {
        return $this->get_format_options();
    }
}

/**
 * Inplace editing of section names.
 *
 * @param string $itemtype Item type.
 * @param int $itemid Section id.
 * @param mixed $newvalue New value.
 * @return inplace_editable
 */
function format_fredki_inplace_editable($itemtype, $itemid, $newvalue) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/course/lib.php');
    if ($itemtype === 'sectionname' || $itemtype === 'sectionnamenl') {
        $section = $DB->get_record_sql(
            'SELECT s.* FROM {course_sections} s JOIN {course} c ON s.course = c.id WHERE s.id = ? AND c.format = ?',
            [$itemid, 'fredki'],
            MUST_EXIST
        );
        return course_get_format($section->course)->inplace_editable_update_section_name($section, $itemtype, $newvalue);
    }
}

/**
 * Preferences a user may set from the learning space.
 *
 * @return array
 */
function format_fredki_user_preferences(): array {
    return [
        'format_fredki_view' => [
            'type' => PARAM_ALPHA,
            'null' => NULL_NOT_ALLOWED,
            'default' => '',
            'choices' => ['', 'topics', 'cards', 'list'],
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
        // Trainers can hide the hint how FredKI comes into the course.
        'format_fredki_aihint' => [
            'type' => PARAM_INT,
            'null' => NULL_NOT_ALLOWED,
            'default' => 0,
            'choices' => [0, 1],
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
    ];
}


/**
 * Serve the pictures trainers uploaded for the topic fields of the learning space.
 *
 * @param stdClass $course Course.
 * @param stdClass|null $cm Not used.
 * @param context $context Course context.
 * @param string $filearea File area.
 * @param array $args itemid, then the file name.
 * @param bool $forcedownload Whether to force a download.
 * @param array $options Options.
 * @return bool false when the file cannot be served
 */
function format_fredki_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if (
        $context->contextlevel !== CONTEXT_COURSE
            || !in_array($filearea, [\format_fredki\local\images::FILEAREA, \format_fredki\local\images::SECTIONAREA], true)
    ) {
        return false;
    }
    require_login($course, false);
    $itemid = (int)array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
    $file = get_file_storage()->get_file($context->id, 'format_fredki', $filearea, $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 86400, 0, $forcedownload, $options);
}
