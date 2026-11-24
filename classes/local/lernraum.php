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
 * Builds the data of the learning space and of a single learning step.
 *
 * Teachers shape everything with familiar Moodle tools: the first image (or video) in a section
 * summary becomes the picture of that step, section 0 provides the large opening image, and the
 * course forum carries the conversation of the group.
 *
 * @package    format_fredki
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lernraum {
    /** @var string[] Strings of amd/src/lernraum.js (STRINGS there). Sent with the page, so the script needs no extra request. */
    const JS_STRINGS = [
        'askround', 'shared', 'thinkfurther', 'thinkfurtherprompt', 'relatedintro', 'fredkipublic', 'discard',
        'fredkierror', 'thinking', 'impulseanswering', 'bothsides', 'viewshared', 'fredkiposted', 'convenewait',
        'conveneempty', 'agentnoanswer', 'more', 'less', 'askcouncilall', 'askcouncilnone', 'edit', 'editsave',
        'editcancel', 'edittitle', 'pendingone', 'pendingmany', 'pendingnow', 'pendingedit', 'delete',
        'deleteconfirm', 'deletewhole', 'definitions_saved', 'definitions_exists', 'definitions_open', 'termai',
        'askprivate', 'askprivateplaceholder', 'agentbusy', 'agentlimit', 'agentpaused', 'agenterror',
        'definitions_count', 'definitions_countone',
        'replyfredkiplaceholder', 'whoanswers', 'topic', 'topic_none', 'topic_new', 'topic_suggest', 'topic_sorting',
        'topic_change', 'topic_add', 'topic_all', 'topic_without', 'topic_create', 'topic_menu',
    ];

    /** @var \stdClass Course. */
    protected \stdClass $course;

    /** @var \context_course Course context. */
    protected \context_course $context;

    /** @var \course_modinfo Course module information. */
    protected \course_modinfo $modinfo;

    /** @var array Course format options. */
    protected array $options;

    /** @var array|null|false Forum of the group, false if not loaded yet. */
    protected $community = false;

    /**
     * Constructor.
     *
     * @param \stdClass $course Course.
     */
    public function __construct(\stdClass $course) {
        $this->course = $course;
        $this->context = \context_course::instance($course->id);
        $this->modinfo = get_fast_modinfo($course);
        $this->options = course_get_format($course)->get_format_options();
    }

    /**
     * Hint for trainers when FredKI does not take part in the course yet, with the next step:
     * learn about FredKI Team Agents, add its block, or choose the learning companion or the expert round.
     *
     * @return array|null Null when FredKI works, the user is no trainer or hid the hint.
     */
    protected function export_aihint(): ?array {
        global $DB;
        if (
            fredki::get_source($this->course) !== ''
            || !has_capability('moodle/course:update', $this->context)
            || get_user_preferences('format_fredki_aihint', 0)
        ) {
            return null;
        }
        $installed = \core_plugin_manager::instance()->get_plugin_info('block_fredkiteamagents') !== null
            && get_config('block_fredkiteamagents', 'version');
        if (!$installed) {
            $url = trim((string)get_config('format_fredki', 'infourl'));
            return [
                'text' => get_string('aihint_install', 'format_fredki'),
                'url' => preg_match('~^https://~', $url) ? $url : '',
                'label' => get_string('aihint_more', 'format_fredki'),
                'external' => true,
            ];
        }
        $hasblock = $DB->record_exists('block_instances', [
            'blockname' => 'fredkiteamagents',
            'parentcontextid' => $this->context->id,
        ]);
        if (!$hasblock) {
            return [
                'text' => get_string('aihint_addblock', 'format_fredki'),
                'url' => (new \moodle_url('/course/view.php', [
                    'id' => $this->course->id, 'edit' => 'on', 'sesskey' => sesskey(),
                ]))->out(false),
                'label' => get_string('aihint_editing', 'format_fredki'),
            ];
        }
        return ['text' => get_string('aihint_choose', 'format_fredki'), 'url' => '', 'label' => ''];
    }

    /**
     * Data of the learning space.
     *
     * @param \renderer_base $output Renderer.
     * @return array
     */
    public function export_space(\renderer_base $output): array {
        $section0 = $this->modinfo->get_section_info(0);
        $media = $this->section_media($section0);
        $question = trim((string)($this->options['heroquestion'] ?? ''));
        if ($question === '') {
            $question = (string)$section0->name !== ''
                ? format_string($section0->name, true, ['context' => $this->context])
                : format_string($this->course->fullname, true, ['context' => $this->context]);
        }
        $image = $media['image'];
        if ($image === '' && $media['video'] === '') {
            $choice = (string)($this->options['heroimage'] ?? '');
            if ($choice !== '') {
                $image = images::url($choice);
            } else {
                $courseimage = \core_course\external\course_summary_exporter::get_course_image($this->course);
                $image = $courseimage ?: images::url(images::HERO);
            }
        }

        $stages = $this->export_stages($output);
        $hasfredki = fredki::is_available($this->course);
        $community = $this->get_community();
        $impulse = trim((string)($this->options['impulse'] ?? ''));
        $todaycard = null;
        if ($community) {
            $today = community::get_today_question($community);
            if ($today) {
                $todaycard = $this->export_card((int)$today->id, $output);
                if ($todaycard) {
                    $todaycard['isimpulse'] = true;
                    $todaycard['shape'] = 'tall';
                }
            }
        }
        $impulsepending = !$todaycard && $impulse === '' && $hasfredki && $community
            && !empty($this->options['fredkiquestions']);

        $data = [
            'courseid' => (int)$this->course->id,
            'coursename' => format_string($this->course->fullname, true, ['context' => $this->context]),
            'courseurl' => (new \moodle_url('/course/view.php', ['id' => $this->course->id]))->out(false),
            'question' => $question,
            'impulseimage' => images::url('peaks'),
            'heroimage' => $image,
            'coursematurity' => $this->course_maturity(),
            'herovideo' => $media['video'],
            'hasherovideo' => $media['video'] !== '',
            'herotext' => $media['text'],
            'stages' => $stages,
            'hasstages' => !empty($stages),
            'starters' => $this->default_starters(),
            'hasfredki' => $hasfredki,
            'aihint' => $this->export_aihint(),
            'botname' => 'FredKI',
            'companionmessageurl' => $this->companion_message_url(),
            'termchips' => array_slice($this->definitions()['terms'], 0, 12),
            'hasterms' => !empty($this->definitions()['terms']),
            'definitionsurl' => $this->definitions()['url'],
            'caneditdefinitions' => $this->definitions()['url'] !== ''
                && has_capability('moodle/course:update', $this->context),
            'startactivities' => $this->export_activities($section0, true),
            'impulse' => $impulse,
            'hasimpulse' => $impulse !== '',
            'impulsepending' => (bool)$impulsepending,
            'todaycard' => $todaycard,
            'hastodaycard' => (bool)$todaycard,
            'types' => $this->export_types(),
            'filters' => $this->export_filters($hasfredki),
            'people' => $this->export_people(),
            'links' => $this->export_links(),
            'council' => council::export($this->course, $this->context, $hasfredki),
            'privacy' => $this->export_privacy($hasfredki),
            'cardstyle' => $this->card_style(),
            'cardbright' => $this->card_style() !== 'image',
            'teammode' => council::can_convene($this->course) && $this->get_community() !== null,
        ];
        $data['hasstartactivities'] = !empty($data['startactivities']);
        $data += $this->export_community(
            $output,
            (int)get_config('format_fredki', 'maxposts') ?: 12,
            $todaycard ? (int)$todaycard['discussionid'] : 0
        );
        $data += $this->export_topics($output, $todaycard ? (int)$todaycard['discussionid'] : 0);
        $data['jsconfig'] = $this->js_config(0, $hasfredki, $data['impulsepending']);
        return $data;
    }

    /**
     * Data of one learning step.
     *
     * @param int $sectionnum Section number.
     * @param \renderer_base $output Renderer.
     * @return array
     */
    public function export_step(int $sectionnum, \renderer_base $output): array {
        $section = $this->modinfo->get_section_info($sectionnum, MUST_EXIST);
        if (!$section->uservisible) {
            throw new \moodle_exception('notavailable');
        }
        $media = $this->section_media($section);
        $stages = $this->export_stages($output);
        $position = 0;
        $prev = null;
        $next = null;
        foreach ($stages as $i => $stage) {
            if ($stage['num'] === $sectionnum) {
                $position = $i + 1;
                $prev = $stages[$i - 1] ?? null;
                $next = $stages[$i + 1] ?? null;
            }
        }
        $image = $this->section_image($section, $media);
        $hasfredki = fredki::is_available($this->course);
        $activities = $this->export_activities($section, false);

        $data = [
            'courseid' => (int)$this->course->id,
            'coursename' => format_string($this->course->fullname, true, ['context' => $this->context]),
            'courseurl' => (new \moodle_url('/course/view.php', ['id' => $this->course->id]))->out(false),
            'stagename' => get_section_name($this->course, $section),
            'position' => $position,
            'total' => count($stages),
            'heroimage' => $image,
            'herovideo' => $media['video'],
            'hasherovideo' => $media['video'] !== '',
            'herotext' => $media['text'],
            'hasherotext' => $media['text'] !== '',
            'activities' => $activities,
            'hasactivities' => !empty($activities),
            'starters' => $this->step_starters($section),
            'hasfredki' => $hasfredki,
            'botname' => 'FredKI',
            'companionmessageurl' => $this->companion_message_url(),
            'termchips' => array_slice($this->definitions()['terms'], 0, 12),
            'hasterms' => !empty($this->definitions()['terms']),
            'definitionsurl' => $this->definitions()['url'],
            'caneditdefinitions' => $this->definitions()['url'] !== ''
                && has_capability('moodle/course:update', $this->context),
            'prev' => $prev,
            'next' => $next,
            'stages' => $stages,
            'people' => $this->export_people(),
            'links' => $this->export_links(),
        ];
        $data += $this->export_community($output, 3);
        $data += $this->export_step_forum($output, $section);
        $data += $this->export_course_tiles($output);
        // Same look of the cards as in the learning space.
        $data['cardstyle'] = $this->card_style();
        $data['cardbright'] = $this->card_style() !== 'image';
        $data['jsconfig'] = $this->js_config($sectionnum, $hasfredki, false);
        return $data;
    }

    /**
     * Images used for steps and posts without an image of their own.
     *
     * @param \renderer_base $output Renderer.
     * @return string[]
     */
    public function default_images(\renderer_base $output): array {
        $images = [];
        foreach ($this->modinfo->get_section_info_all() as $section) {
            if ($section->section > 0 && $section->uservisible && empty($section->component)) {
                $media = $this->section_media($section);
                if ($media['image'] !== '') {
                    $images[] = $media['image'];
                }
            }
        }
        return array_merge($images, images::all());
    }

    /**
     * Learning steps: sections 1..n that the learner can see or that show a restriction.
     *
     * @param \renderer_base $output Renderer.
     * @return array
     */
    protected function export_stages(\renderer_base $output): array {
        $completion = new \completion_info($this->course);
        $marker = (int)$this->course->marker;
        $stages = [];
        $firstopen = null;
        foreach ($this->modinfo->get_section_info_all() as $section) {
            if ($section->section == 0 || !empty($section->component)) {
                continue;
            }
            $locked = !$section->uservisible;
            if ($locked && !($section->visible && $section->availableinfo)) {
                continue;
            }
            $media = $this->section_media($section);
            $done = 0;
            $total = 0;
            if (!$locked && $completion->is_enabled()) {
                foreach ($this->modinfo->get_sections()[$section->section] ?? [] as $cmid) {
                    $cm = $this->modinfo->cms[$cmid];
                    if (!$cm->uservisible || !$completion->is_enabled($cm)) {
                        continue;
                    }
                    $total++;
                    $state = $completion->get_data($cm, false)->completionstate;
                    if ($state == COMPLETION_COMPLETE || $state == COMPLETION_COMPLETE_PASS) {
                        $done++;
                    }
                }
            }
            if ($firstopen === null && !$locked && $done < $total) {
                $firstopen = $section->section;
            }
            $stages[] = [
                'num' => (int)$section->section,
                'name' => get_section_name($this->course, $section),
                'url' => (new \moodle_url('/course/view.php', [
                    'id' => $this->course->id,
                    'section' => $section->section,
                ]))->out(false),
                'image' => $this->section_image($section, $media),
                'locked' => $locked,
                'done' => $done,
                'total' => $total,
                'hasprogress' => $total > 0,
                'complete' => $total > 0 && $done === $total,
                'iscurrent' => false,
            ];
        }
        $current = null;
        foreach ($stages as $i => $stage) {
            if (!$stage['locked'] && ($stage['num'] === $marker || ($marker <= 0 && $stage['num'] === $firstopen))) {
                $current = $i;
                break;
            }
        }
        if ($current === null) {
            foreach ($stages as $i => $stage) {
                if (!$stage['locked']) {
                    $current = $i;
                    break;
                }
            }
        }
        if ($current !== null) {
            $stages[$current]['iscurrent'] = true;
        }
        return $stages;
    }

    /**
     * Knowledge folders of the AI agents: they belong to the block FredKI Team Agents ("Ordner öffnen"), not to the
     * course page. Also folders of agents removed earlier: hidden folders named "Wissen: …" / "Knowledge: …".
     *
     * @return array Course module ids as keys.
     */
    protected function agent_folders(): array {
        global $DB;
        $result = [];
        if (council::has_teamagents()) {
            foreach (
                $DB->get_fieldset_select(
                    'block_fredkiteamagents_agent',
                    'cmid',
                    'courseid = ? AND cmid > 0',
                    [$this->course->id]
                ) as $cmid
            ) {
                $result[(int)$cmid] = true;
            }
        }
        foreach ($this->modinfo->get_instances_of('folder') as $cm) {
            if (!$cm->visible && preg_match('/^(Wissen|Knowledge):\s/u', (string)$cm->name)) {
                $result[(int)$cm->id] = true;
            }
        }
        return $result;
    }

    /**
     * Activities of a section as calm cards.
     *
     * @param \section_info $section Section.
     * @param bool $start Whether this is the start section (forums of the group are left out there).
     * @return array
     */
    protected function export_activities(\section_info $section, bool $start): array {
        $completion = new \completion_info($this->course);
        $community = $this->get_community();
        $skip = $community ? (int)$community['cm']->id : 0;
        // The conversation of the step is shown as conversation below, not again as material.
        $stepforum = $start ? null : community::step_forum($this->course, $section);
        $skipstep = $stepforum ? (int)$stepforum['cm']->id : 0;
        $agentfolders = $this->agent_folders();
        // The glossary of the group opens as a calm window in the learning space, not as the glossary page.
        $definitionscm = $this->definitions()['url'] !== ''
            ? (int)(course_get_format($this->course)->get_format_options()['definitionsglossary'] ?? 0) : 0;
        $result = [];
        foreach ($this->modinfo->get_sections()[$section->section] ?? [] as $cmid) {
            $cm = $this->modinfo->cms[$cmid];
            if (
                !$cm->is_visible_on_course_page() || (int)$cm->id === $skip || (int)$cm->id === $skipstep
                    || isset($agentfolders[(int)$cm->id])
            ) {
                continue;
            }
            if ($start && $cm->modname === 'forum') {
                continue;
            }
            $hascompletion = $cm->uservisible && $completion->is_enabled($cm);
            $complete = false;
            if ($hascompletion) {
                $state = $completion->get_data($cm, false)->completionstate;
                $complete = $state == COMPLETION_COMPLETE || $state == COMPLETION_COMPLETE_PASS;
            }
            $url = $cm->uservisible && $cm->url ? $cm->url->out(false) : '';
            $content = '';
            if (!$start && $cm->uservisible && !$cm->url) {
                $content = $cm->get_formatted_content(['overflowdiv' => true, 'noclean' => true]);
            }
            $result[] = [
                'name' => $cm->get_formatted_name(),
                'url' => $url,
                'haslink' => $url !== '',
                'isdefinitions' => $definitionscm && (int)$cm->id === $definitionscm,
                'icon' => $cm->get_icon_url()->out(false),
                'type' => $cm->get_module_type_name(),
                'content' => $content,
                'istext' => $content !== '',
                'hascompletion' => $hascompletion,
                'complete' => $complete,
                'restricted' => !$cm->uservisible,
                'availability' => !$cm->uservisible && $cm->availableinfo
                    ? \core_availability\info::format_info($cm->availableinfo, $this->course) : '',
            ];
        }
        return $result;
    }

    /**
     * The conversation of the group.
     *
     * @param \renderer_base $output Renderer.
     * @param int $limit Number of cards.
     * @param int $excludeid Conversation shown separately (question of the day).
     * @return array
     */
    protected function export_community(\renderer_base $output, int $limit, int $excludeid = 0): array {
        $community = $this->get_community();
        if (!$community) {
            $canadd = has_capability('moodle/course:manageactivities', $this->context);
            return [
                'hascommunity' => false,
                'cards' => [],
                'canaddforum' => $canadd,
                // Agents are there, but the round needs a forum: they answer in the course conversations.
                'agentsneedforum' => $canadd && council::mode($this->course) === council::MODE_TEAM,
                'addforumurl' => $canadd ? (new \moodle_url('/course/modedit.php', [
                    'add' => 'forum',
                    'course' => $this->course->id,
                    'section' => 0,
                    'return' => 0,
                ]))->out(false) : '',
            ];
        }
        $cards = community::export_cards($this->course, $community, $this->default_images($output), $limit + 1, $excludeid);
        $hasmore = count($cards) > $limit;
        $cards = array_slice($cards, 0, $limit);
        return [
            'hascommunity' => true,
            'cards' => $cards,
            'hascards' => !empty($cards),
            'hasmore' => $hasmore,
            'cardcount' => count($cards),
            'canpost' => has_capability('mod/forum:startdiscussion', $community['context']),
            'canmoderate' => has_capability('mod/forum:editanypost', $community['context']),
            'forumurl' => (new \moodle_url('/mod/forum/view.php', ['id' => $community['cm']->id]))->out(false),
        ];
    }

    /**
     * The conversation of a learning step: its own forum in that section, shown large with its own field to share.
     *
     * @param \renderer_base $output Renderer.
     * @param \section_info $section Section.
     * @return array
     */
    protected function export_step_forum(\renderer_base $output, \section_info $section): array {
        $step = community::step_forum($this->course, $section);
        if (!$step) {
            $canadd = has_capability('moodle/course:manageactivities', $this->context);
            return ['hasstepforum' => false, 'canaddstepforum' => $canadd,
                'addstepforumurl' => $canadd ? (new \moodle_url('/course/modedit.php', ['add' => 'forum',
                    'course' => $this->course->id, 'section' => $section->section, 'return' => 0]))->out(false) : ''];
        }
        $cards = community::export_cards($this->course, $step, $this->default_images($output), 30);
        return [
            'hasstepforum' => true,
            'stepforumid' => (int)$step['forum']->id,
            'stepforumname' => format_string($step['forum']->name, true, ['context' => $step['context']]),
            'stepcards' => $cards,
            'hasstepcards' => !empty($cards),
            'canpoststep' => has_capability('mod/forum:startdiscussion', $step['context']),
            'types' => $this->export_types(),
            'stepforumurl' => (new \moodle_url('/mod/forum/view.php', ['id' => $step['cm']->id]))->out(false),
        ];
    }

    /**
     * All course conversations, small: shelves per topic field (or one row of tiles without topic fields).
     *
     * @param \renderer_base $output Renderer.
     * @return array
     */
    protected function export_course_tiles(\renderer_base $output): array {
        $community = $this->get_community();
        if (!$community) {
            return ['hascoursetiles' => false];
        }
        $courseurl = course_get_url($this->course)->out(false);
        $shelves = $this->export_topics($output, 0)['shelves'] ?? [];
        if (!$shelves) {
            $cards = community::export_cards($this->course, $community, $this->default_images($output), 24);
            $shelves = $cards ? [['topicid' => 0, 'name' => '', 'cards' => $cards]] : [];
        }
        foreach ($shelves as &$shelf) {
            $shelf['hasname'] = $shelf['name'] !== '';
            $shelf['allurl'] = $courseurl . ($shelf['topicid'] ? '#thema-' . $shelf['topicid'] : '#runde');
        }
        return ['hascoursetiles' => !empty($shelves), 'courseshelves' => $shelves];
    }

    /**
     * Topic fields: shelves of the newest conversations per topic field, chips to choose one, and the view.
     *
     * @param \renderer_base $output Renderer.
     * @param int $excludeid Conversation shown separately (question of the day).
     * @return array
     */
    protected function export_topics(\renderer_base $output, int $excludeid): array {
        $community = $this->get_community();
        if (!$community) {
            return ['hastopics' => false];
        }
        $courseid = (int)$this->course->id;
        $all = topics::get_all($courseid);
        $discussions = $all ? community::load_discussions($this->course, $community, topics::SCAN) : [];
        $counts = [];
        $chosen = [];
        $map = topics::of_discussions(array_keys($discussions));
        foreach ($discussions as $id => $d) {
            $topicid = $map[$id] ?? 0;
            $counts[$topicid] = ($counts[$topicid] ?? 0) + 1;
            if ($id !== $excludeid && count($chosen[$topicid] ?? []) < topics::SHELF) {
                $chosen[$topicid][$id] = $d;
            }
        }
        if (count($discussions) >= topics::SCAN) {
            $counts = topics::counts($courseid, (int)$community['forum']->id);
        }
        $chips = topics::export_list($this->course, $counts);
        $shelves = [];
        if ($all) {
            $picked = [];
            foreach ($chosen as $list) {
                $picked += $list;
            }
            $cards = [];
            foreach (community::export_discussions($community, $picked, $this->default_images($output)) as $card) {
                $card['idsuffix'] = '-s';
                $cards[(int)$card['discussionid']] = $card;
            }
            $maturity = quality::enabled($this->course) ? topics::maturity($courseid) : [];
            foreach (array_merge(array_keys($all), [0]) as $topicid) {
                if (empty($chosen[$topicid])) {
                    continue;
                }
                $count = $counts[$topicid] ?? 0;
                $shelf = [
                    'topicid' => $topicid ?: -1,
                    'name' => $topicid ? topics::name($all[$topicid]) : get_string('topic_without', 'format_fredki'),
                    'iswithout' => !$topicid,
                    'count' => $count,
                    'countlabel' => get_string($count === 1 ? 'topic_countone' : 'topic_count', 'format_fredki', $count),
                    'cards' => array_values(array_filter(array_map(
                        fn($id) => $cards[$id] ?? null,
                        array_keys($chosen[$topicid])
                    ))),
                    'hasmore' => $count > count($chosen[$topicid]),
                ];
                if (isset($maturity[$topicid])) {
                    $shelf += quality::export((object)['score' => $maturity[$topicid], 'aspects' => '', 'hint' => '',
                        'courseid' => $courseid]);
                }
                $shelves[] = $shelf;
            }
        }
        $view = (string)get_user_preferences('format_fredki_view', '');
        if (!in_array($view, ['topics', 'cards', 'list'], true) || ($view === 'topics' && !$shelves)) {
            $view = $shelves ? 'topics' : 'cards';
        }
        $views = [];
        foreach ($shelves ? ['topics', 'cards', 'list'] : ['cards', 'list'] as $name) {
            $views[] = ['value' => $name, 'label' => get_string('view_' . $name, 'format_fredki'), 'active' => $name === $view,
                'is' . $name => true];
        }
        $canmanage = topics::can_manage($this->context);
        return [
            'hastopics' => !empty($chips),
            'topicchips' => $chips,
            'withoutcount' => $counts[0] ?? 0,
            'haswithout' => !empty($chips) && !empty($counts[0]),
            'shelves' => $shelves,
            'hasshelves' => !empty($shelves),
            'view' => $view,
            'views' => $views,
            'canmanagetopics' => $canmanage,
            'topicsurl' => $canmanage
                ? (new \moodle_url('/course/format/fredki/topics.php', ['id' => $courseid]))->out(false) : '',
        ];
    }

    /**
     * More cards for the wall: the next page, or the results of a search.
     *
     * @param \renderer_base $output Renderer.
     * @param int $offset Cards already shown.
     * @param int $limit Cards to load.
     * @param string $query Search text, empty for all.
     * @param int $topicid Topic field, 0 for all, -1 for conversations without topic field.
     * @return array ['cards' => array, 'hasmore' => bool]
     */
    public function export_more_cards(
        \renderer_base $output,
        int $offset,
        int $limit,
        string $query = '',
        int $topicid = 0
    ): array {
        $community = $this->get_community();
        if (!$community) {
            return ['cards' => [], 'hasmore' => false];
        }
        $cards = community::export_cards(
            $this->course,
            $community,
            $this->default_images($output),
            $limit + 1,
            0,
            $offset,
            $query,
            $topicid
        );
        $hasfredki = fredki::is_available($this->course);
        foreach ($cards as &$card) {
            $card['hasfredki'] = $hasfredki;
        }
        return ['cards' => array_slice($cards, 0, $limit), 'hasmore' => count($cards) > $limit];
    }

    /**
     * "Datenschutz & KI": what happens with posts when AI takes part. The course can replace the default text
     * (Markdown); the default names this Moodle site and its support contact.
     *
     * @param bool $hasfredki Whether FredKI answers in this course.
     * @return array
     */
    protected function export_privacy(bool $hasfredki): array {
        global $CFG;
        if (!$hasfredki && !council::has_teamagents()) {
            return ['show' => false];
        }
        $text = trim((string)($this->options['privacynote'] ?? ''));
        if ($text === '') {
            $site = get_site();
            $contact = !empty($CFG->supportemail) ? $CFG->supportemail : get_string('privacynote_contactfallback', 'format_fredki');
            $text = get_string('privacynote_default', 'format_fredki', (object)[
                'operator' => format_string($site->fullname),
                'contact' => $contact,
            ]);
        }
        return [
            'show' => true,
            'html' => format_text($text, FORMAT_MARKDOWN, ['context' => $this->context]),
        ];
    }

    /**
     * People of the course for the row at the top, if the course shows who is here.
     *
     * @return array
     */
    protected function export_people(): array {
        if (empty($this->options['showpresence'])) {
            return ['show' => false];
        }
        return council::people($this->course, $this->context);
    }

    /**
     * Discreet links for the top bar.
     *
     * @return array
     */
    protected function export_links(): array {
        global $USER;
        $links = [];
        if (has_capability('moodle/course:viewparticipants', $this->context)) {
            $links[] = [
                'label' => get_string('participants'),
                'url' => (new \moodle_url('/user/index.php', ['id' => $this->course->id]))->out(false),
            ];
        }
        if (has_capability('moodle/grade:view', $this->context) || has_capability('moodle/grade:viewall', $this->context)) {
            $links[] = [
                'label' => get_string('grades'),
                'url' => (new \moodle_url('/grade/report/index.php', ['id' => $this->course->id]))->out(false),
            ];
        }
        $canedit = has_capability('moodle/course:update', $this->context)
            || has_capability('moodle/course:manageactivities', $this->context);
        return [
            'items' => $links,
            'canedit' => $canedit,
            'editurl' => $canedit ? (new \moodle_url('/course/view.php', [
                'id' => $this->course->id,
                'edit' => 'on',
                'sesskey' => sesskey(),
            ]))->out(false) : '',
            'me' => community::export_user($USER),
        ];
    }

    /**
     * Maturity of the discussion in the whole course, for the side bar.
     *
     * @return array|null
     */
    protected function course_maturity(): ?array {
        if (!quality::enabled($this->course)) {
            return null;
        }
        $maturity = quality::course((int)$this->course->id);
        $canoverview = has_capability('moodle/course:update', $this->context);
        if (!$maturity) {
            return ['hasscore' => false, 'canoverview' => $canoverview,
                'overviewurl' => (new \moodle_url('/course/format/fredki/maturity.php', ['id' => $this->course->id]))->out(false)];
        }
        return $maturity + [
            'hasscore' => true,
            'ratedlabel' => get_string('maturity_rated', 'format_fredki', $maturity['conversations']),
            'canoverview' => $canoverview,
            'overviewurl' => (new \moodle_url('/course/format/fredki/maturity.php', ['id' => $this->course->id]))->out(false),
        ];
    }

    /**
     * Starter questions of the learning space.
     *
     * @return array
     */
    protected function default_starters(): array {
        return [
            ['label' => get_string('starter_exercise', 'format_fredki'), 'mode' => 'ask', 'personal' => true],
            ['label' => get_string('starter_askme', 'format_fredki'), 'mode' => 'ask', 'personal' => true],
        ];
    }

    /**
     * Starter questions of a learning step: set by the teacher, otherwise defaults.
     *
     * @param \section_info $section Section.
     * @return array
     */
    protected function step_starters(\section_info $section): array {
        $options = course_get_format($this->course)->get_format_options($section);
        $lines = array_filter(array_map('trim', preg_split('/\R/u', (string)($options['fredkistarters'] ?? ''))));
        // The built-in suggestions are personal learning (an exercise, prior knowledge): nothing to share with all.
        $personal = !$lines;
        if (!$lines) {
            $lines = [
                get_string('starter_prior', 'format_fredki'),
                get_string('starter_exercise', 'format_fredki'),
            ];
        }
        $starters = [];
        foreach (array_slice($lines, 0, 4) as $line) {
            $starters[] = ['label' => shorten_text($line, 80), 'mode' => 'ask', 'personal' => $personal];
        }
        return $starters;
    }


    /**
     * Picture of a learning step: first image of its description, the chosen photo or a calm default.
     *
     * @param \section_info $section Section.
     * @param array $media Result of section_media().
     * @return string URL.
     */
    protected function section_image(\section_info $section, array $media): string {
        $own = images::section_upload((int)$this->course->id, (int)$section->id);
        if ($own !== '') {
            return $own;
        }
        if ($media['image'] !== '') {
            return $media['image'];
        }
        $options = course_get_format($this->course)->get_format_options($section);
        $choice = (string)($options['fredkiimage'] ?? '');
        if ($choice !== '' && in_array($choice, images::PHOTOS, true)) {
            return images::url($choice);
        }
        return images::for_index((int)$section->section - 1);
    }

    /**
     * Kinds of contributions learners can choose when they share something.
     *
     * @return array
     */
    protected function export_types(): array {
        $types = [];
        foreach (community::TYPES as $i => $type) {
            $types[] = ['value' => $type, 'label' => get_string('type_' . $type, 'format_fredki'), 'checked' => $i === 0];
        }
        return $types;
    }

    /**
     * Filters of the wall.
     *
     * @param bool $hasfredki Whether FredKI takes part.
     * @return array
     */
    protected function export_filters(bool $hasfredki): array {
        $filters = [['value' => 'all', 'label' => get_string('filter_all', 'format_fredki'), 'active' => true]];
        foreach (community::TYPES as $type) {
            $filters[] = ['value' => $type, 'label' => get_string('filter_' . $type, 'format_fredki'), 'active' => false];
        }
        if ($hasfredki) {
            $filters[] = ['value' => 'fredki', 'label' => 'FredKI', 'active' => false];
        }
        return $filters;
    }

    /**
     * One card of the wall, with its latest replies.
     *
     * @param int $discussionid Discussion id.
     * @param \renderer_base $output Renderer.
     * @param bool $full Whole conversation instead of the card.
     * @return array|null
     */
    public function export_card(int $discussionid, \renderer_base $output, bool $full = false): ?array {
        $community = $this->get_community();
        if (!$community) {
            return null;
        }
        $discussions = community::load_discussions($this->course, $community, 1, $discussionid);
        if (!$discussions) {
            return null;
        }
        $discussion = reset($discussions);
        $replies = community::load_replies([(int)$discussion->id])[(int)$discussion->id] ?? [];
        $postids = array_merge([(int)$discussion->firstpost], array_map(fn($r) => (int)$r->id, $replies));
        $card = community::export_card(
            $discussion,
            $replies,
            $community,
            community::get_likes($postids),
            community::get_ai_posts($postids),
            $this->default_images($output),
            (int)$discussion->id,
            community::get_meta($postids),
            $full
        );
        $card['hasfredki'] = fredki::is_available($this->course);
        return $card;
    }

    /**
     * First image, first video and short text of a section summary.
     *
     * @param \section_info $section Section.
     * @return array ['image' => string, 'video' => string, 'text' => string]
     */
    protected function section_media(\section_info $section): array {
        $summary = file_rewrite_pluginfile_urls(
            (string)$section->summary,
            'pluginfile.php',
            $this->context->id,
            'course',
            'section',
            $section->id
        );
        $image = '';
        $video = '';
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $summary, $m)) {
            $image = html_entity_decode($m[1], ENT_QUOTES);
        }
        if (preg_match('/(?:src|href)=["\']([^"\']+\.(?:mp4|webm)(?:\?[^"\']*)?)["\']/i', $summary, $m)) {
            $video = html_entity_decode($m[1], ENT_QUOTES);
        }
        $clean = preg_replace('/<(video|audio)\b.*?<\/\1>|<img[^>]*>|<a\b[^>]*\.(?:mp4|webm)[^>]*>.*?<\/a>/is', '', $summary);
        $text = trim(preg_replace('/\s+/u', ' ', content_to_text($clean, (int)$section->summaryformat)));
        return [
            'image' => $image,
            'video' => $video,
            'text' => shorten_text($text, 220, true),
        ];
    }

    /**
     * Forum of the group, loaded once.
     *
     * @return array|null
     */
    protected function get_community(): ?array {
        if ($this->community === false) {
            $this->community = community::get_forum($this->course);
        }
        return $this->community;
    }

    /**
     * Configuration of the JavaScript module.
     *
     * @param int $sectionnum Learning step, 0 for the learning space.
     * @param bool $hasfredki Whether FredKI is available.
     * @param bool $impulsepending Whether the impulse must be fetched from FredKI.
     * @return array
     */
    protected function js_config(int $sectionnum, bool $hasfredki, bool $impulsepending): array {
        return [
            'courseid' => (int)$this->course->id,
            'section' => $sectionnum,
            'askurl' => (new \moodle_url('/course/format/fredki/ask.php'))->out(false),
            'sesskey' => sesskey(),
            'hasfredki' => $hasfredki,
            'impulsepending' => $impulsepending,
            'fredkiquestions' => !empty($this->options['fredkiquestions']),
            'canconvene' => council::can_convene($this->course),
            'agents' => $this->js_agents(),
            'answerdelay' => council::answer_delay(),
            'terms' => $this->definitions()['terms'],
            'definitionsurl' => $this->definitions()['url'],
            'caneditdefinitions' => $this->definitions()['url'] !== ''
                && has_capability('moodle/course:update', $this->context),
            'topics' => topics::export_list($this->course, []),
            'canmanagetopics' => topics::can_manage($this->context),
        ];
    }

    /**
     * Look of the conversation cards: image (full photo, white text), light, calm or clear (no photos).
     *
     * @return string
     */
    protected function card_style(): string {
        $style = (string)($this->options['cardstyle'] ?? 'light');
        return in_array($style, ['image', 'light', 'calm', 'clear'], true) ? $style : 'light';
    }

    /**
     * Agents that can answer, for mentions and "Ask the council" in the browser.
     *
     * @return array
     */
    protected function js_agents(): array {
        $result = [];
        if (council::mode($this->course) !== council::MODE_TEAM) {
            return $result;
        }
        foreach (council::agents((int)$this->course->id) as $agent) {
            if (!$agent['ready']) {
                continue;
            }
            $result[] = [
                'id' => $agent['id'],
                'name' => $agent['name'],
                'image' => $agent['image'],
                'colour' => $agent['colour'],
                'active' => $agent['active'],
                'mentions' => array_merge([ltrim($agent['mention'], '@'), $agent['name']], $agent['aliases'] ?? []),
                'thinking' => get_string('agentthinking', 'format_fredki', $agent['name']),
            ];
        }
        return $result;
    }

    /**
     * Moodle messages with the learning companion, if the course has one and messaging is on.
     *
     * @return string URL or empty string.
     */
    protected function companion_message_url(): string {
        global $CFG;
        if (empty($CFG->messaging) || !isloggedin() || isguestuser()) {
            return '';
        }
        $agent = council::companion((int)$this->course->id);
        return $agent ? (new \moodle_url('/message/index.php', ['id' => (int)$agent->userid]))->out(false) : '';
    }

    /** @var array|null Definitions of the group, loaded once. */
    protected $definitions = null;

    /**
     * Definitions of the group (glossary), loaded once per page.
     *
     * @return array ['terms' => list, 'url' => string]
     */
    protected function definitions(): array {
        if ($this->definitions === null) {
            $this->definitions = definitions::enabled($this->course) ? definitions::export($this->course)
                : ['terms' => [], 'url' => ''];
        }
        return $this->definitions;
    }
}
