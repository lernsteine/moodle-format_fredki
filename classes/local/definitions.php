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

namespace format_fredki\local;

/**
 * "Definitions of the group": when someone asks FredKI for a definition, the concise answer is kept in a glossary of
 * the course. The learning space shows the terms as chips and explains them on hover wherever they appear.
 *
 * @package    format_fredki
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class definitions {
    /** Terms passed to the browser at most. */
    const MAXTERMS = 300;

    /**
     * Whether the course collects definitions.
     *
     * @param \stdClass $course Course.
     * @return bool
     */
    public static function enabled(\stdClass $course): bool {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        $options = course_get_format($course)->get_format_options();
        return !empty($options['definitions']);
    }

    /**
     * Whether a question asks for a definition, e.g. "Was ist Suffizienz?", "Define rebound effect".
     *
     * @param string $question Question.
     * @return bool
     */
    public static function is_definition_question(string $question): bool {
        $q = trim(\core_text::strtolower($question));
        if ($q === '' || \core_text::strlen($q) > 160) {
            return false;
        }
        $patterns = [
            '/^(was (ist|sind|bedeutet|bedeuten|hei(ß|ss)t|versteht man unter|meint man mit|genau ist)|definiere|' .
                'definition (von|f(ü|u)r|des|der)|wof(ü|u)r steht|erkl(ä|a)r(e)? (mir )?(den|die|das)? ?begriff)\b/u',
            '/^(what(\'s| is| are| means| does .* mean| do .* mean| does .* stand for)|define|definition of|' .
                'explain the term|meaning of|whats)\b/u',
            '/^(qu\'est-ce que|d(é|e)finis|definici(ó|o)n de|qu(é|e) es|che cos\'(è|e)|wat is|wat betekent)\b/u',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $q)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Term and definition from FredKI's answer in the form "TERM: …" / "DEFINITION: …".
     *
     * @param string $answer Answer.
     * @return array|null [term, definition] or null.
     */
    public static function parse(string $answer): ?array {
        $text = str_replace(['**', '__', chr(96)], '', $answer);
        if (!preg_match('/(?:TERM|BEGRIFF)\s*:\s*(.+?)\s*\R+\s*(?:DEFINITION)\s*:\s*(.+)/isu', $text, $m)) {
            return null;
        }
        $term = trim(preg_replace('/\s+/u', ' ', $m[1]), " \t\"„“”'.:");
        $definition = trim(preg_replace('/\s+/u', ' ', $m[2]));
        if ($term === '' || $definition === '' || \core_text::strlen($term) > 80) {
            return null;
        }
        return [$term, \core_text::substr($definition, 0, 700)];
    }

    /**
     * The glossary of the definitions, created in the first section when $create is set.
     *
     * @param \stdClass $course Course.
     * @param bool $create Create it if missing.
     * @return array|null ['glossary' => record, 'cm' => cm_info] or null.
     */
    public static function glossary(\stdClass $course, bool $create = false): ?array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');
        $format = course_get_format($course);
        $cmid = (int)($format->get_format_options()['definitionsglossary'] ?? 0);
        if ($cmid) {
            $cm = get_fast_modinfo($course)->get_cms()[$cmid] ?? null;
            if ($cm && $cm->modname === 'glossary' && !$cm->deletioninprogress) {
                $glossary = $DB->get_record('glossary', ['id' => $cm->instance]);
                if ($glossary) {
                    return ['glossary' => $glossary, 'cm' => $cm];
                }
            }
        }
        if (!$create || !$DB->record_exists('modules', ['name' => 'glossary', 'visible' => 1])) {
            return null;
        }
        require_once($CFG->dirroot . '/course/modlib.php');
        $moduleinfo = (object)[
            'modulename' => 'glossary',
            'module' => (int)$DB->get_field('modules', 'id', ['name' => 'glossary']),
            'course' => $course->id,
            'section' => 0,
            'visible' => 1,
            'visibleoncoursepage' => 1,
            'name' => get_string('definitions_name', 'format_fredki'),
            'introeditor' => ['text' => get_string('definitions_intro', 'format_fredki'), 'format' => FORMAT_HTML, 'itemid' => 0],
            'displayformat' => 'dictionary',
            'approvaldisplayformat' => 'default',
            'mainglossary' => 0,
            'globalglossary' => 0,
            'showspecial' => 1,
            'showalphabet' => 1,
            'showall' => 1,
            'allowduplicatedentries' => 0,
            'allowcomments' => 0,
            'allowprintview' => 1,
            'usedynalink' => 1,
            'defaultapproval' => 1,
            'editalways' => 0,
            'entbypage' => 20,
            'rsstype' => 0,
            'rssarticles' => 0,
            'assessed' => 0,
            'groupmode' => 0,
            'groupingid' => 0,
            'cmidnumber' => '',
            'completion' => 0,
        ];
        $moduleinfo = add_moduleinfo($moduleinfo, $course);
        $format->update_course_format_options(['definitionsglossary' => (int)$moduleinfo->coursemodule]);
        rebuild_course_cache($course->id, true);
        $course = get_course($course->id);
        $cm = get_fast_modinfo($course)->get_cm((int)$moduleinfo->coursemodule);
        return ['glossary' => $DB->get_record('glossary', ['id' => $cm->instance], '*', MUST_EXIST), 'cm' => $cm];
    }

    /**
     * Keep a definition for the group. A term that exists already stays as it is.
     *
     * @param \stdClass $course Course.
     * @param string $term Term.
     * @param string $definition Definition (plain text).
     * @param int $sectionnum Learning step the definition belongs to, 0 for none.
     * @return array ['saved' => bool, 'exists' => bool, 'url' => string]
     */
    public static function add(\stdClass $course, string $term, string $definition, int $sectionnum = 0): array {
        global $DB;
        $found = self::glossary($course, true);
        if (!$found) {
            return ['saved' => false, 'exists' => false, 'url' => ''];
        }
        $glossary = $found['glossary'];
        $cm = $found['cm'];
        $existing = $DB->get_record_select(
            'glossary_entries',
            'glossaryid = ? AND ' . $DB->sql_like('concept', '?', false),
            [$glossary->id, $DB->sql_like_escape($term)],
            'id',
            IGNORE_MULTIPLE
        );
        if ($existing) {
            self::file_under_step($course, $glossary, (int)$existing->id, $sectionnum);
            return ['saved' => false, 'exists' => true, 'url' => self::entry_url((int)$existing->id)];
        }
        $now = time();
        $html = '<p>' . s($definition) . '</p><p class="ffk-def-ai"><small>' .
            get_string('definitions_ailabel', 'format_fredki') . '</small></p>';
        $entry = (object)[
            'glossaryid' => $glossary->id,
            'userid' => botuser::get_id(),
            'concept' => $term,
            'definition' => $html,
            'definitionformat' => FORMAT_HTML,
            'definitiontrust' => 0,
            'attachment' => '',
            'timecreated' => $now,
            'timemodified' => $now,
            'teacherentry' => 0,
            'sourceglossaryid' => 0,
            'usedynalink' => 1,
            'casesensitive' => 0,
            'fullmatch' => 1,
            'approved' => 1,
        ];
        $entry->id = $DB->insert_record('glossary_entries', $entry);
        self::file_under_step($course, $glossary, (int)$entry->id, $sectionnum);
        \mod_glossary\local\concept_cache::reset_glossary($glossary);
        $context = \context_module::instance($cm->id);
        $event = \mod_glossary\event\entry_created::create([
            'context' => $context,
            'objectid' => $entry->id,
            'other' => ['concept' => $entry->concept],
        ]);
        $event->add_record_snapshot('glossary_entries', $entry);
        $event->trigger();
        return ['saved' => true, 'exists' => false, 'url' => self::entry_url((int)$entry->id)];
    }

    /**
     * One glossary for the whole course, with a category per learning step: a term asked on a step is filed under
     * that step (also when it was asked on another step before). The glossary shows the steps under "Browse by
     * category"; the links to terms work everywhere in the course.
     *
     * @param \stdClass $course Course.
     * @param \stdClass $glossary Glossary.
     * @param int $entryid Entry.
     * @param int $sectionnum Section the question was asked in, 0 for the learning space.
     */
    protected static function file_under_step(\stdClass $course, \stdClass $glossary, int $entryid, int $sectionnum): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');
        if ($sectionnum <= 0) {
            return;
        }
        $section = get_fast_modinfo($course)->get_section_info($sectionnum);
        if (!$section) {
            return;
        }
        $name = \core_text::substr(trim(strip_tags(get_section_name($course, $section))), 0, 255);
        if ($name === '') {
            return;
        }
        $category = $DB->get_record(
            'glossary_categories',
            ['glossaryid' => $glossary->id, 'name' => $name],
            'id',
            IGNORE_MULTIPLE
        );
        $categoryid = $category ? (int)$category->id : (int)$DB->insert_record(
            'glossary_categories',
            (object)['glossaryid' => $glossary->id, 'name' => $name, 'usedynalink' => 0]
        );
        if (!$DB->record_exists('glossary_entries_categories', ['categoryid' => $categoryid, 'entryid' => $entryid])) {
            $DB->insert_record('glossary_entries_categories', (object)['categoryid' => $categoryid, 'entryid' => $entryid]);
        }
    }

    /**
     * Address of one entry.
     *
     * @param int $entryid Entry id.
     * @return string
     */
    protected static function entry_url(int $entryid): string {
        return (new \moodle_url('/mod/glossary/showentry.php', ['eid' => $entryid, 'displayformat' => 'dictionary']))
            ->out(false);
    }

    /**
     * Terms of the glossary for the learning space, newest first. Entries added by hand count too.
     *
     * @param \stdClass $course Course.
     * @return array ['terms' => list, 'url' => glossary address]
     */
    public static function export(\stdClass $course): array {
        global $DB;
        $found = self::glossary($course);
        if (!$found || !$found['cm']->uservisible) {
            return ['terms' => [], 'url' => ''];
        }
        $context = \context_module::instance($found['cm']->id);
        $terms = [];
        $records = $DB->get_records(
            'glossary_entries',
            ['glossaryid' => $found['glossary']->id, 'approved' => 1],
            'timecreated DESC',
            'id, concept, definition, definitionformat, userid',
            0,
            self::MAXTERMS
        );
        foreach ($records as $r) {
            $plain = trim(preg_replace('/\s+/u', ' ', html_to_text(format_text(
                preg_replace('~<p class="ffk-def-ai">.*?</p>~su', '', (string)$r->definition),
                (int)$r->definitionformat,
                ['context' => $context, 'filter' => false]
            ), 0, false)));
            $terms[] = [
                'concept' => format_string($r->concept, true, ['context' => $context]),
                'definition' => shorten_text($plain, 400, true),
                'url' => self::entry_url((int)$r->id),
                'ai' => botuser::is_bot((int)$r->userid),
            ];
        }
        return ['terms' => $terms, 'url' => $found['cm']->url ? $found['cm']->url->out(false) : ''];
    }
}
