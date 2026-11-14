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
 * How FredKI answers in a course: through the learning companion of FredKI Team Agents (knowledge
 * folder of the course) or through a chatbot design of FredKI Chat.
 *
 * @package    format_fredki
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fredki {
    /** @var string[] Ways in which FredKI takes part in the learning space. */
    const MODES = ['ask', 'define', 'summary', 'contrast', 'perspective', 'impulse', 'mention', 'answer'];

    /** @var string Answers come from a chatbot design of FredKI Chat. */
    const SOURCE_DESIGN = 'design';

    /** @var string Answers come from the learning companion added in the block FredKI Team Agents. */
    const SOURCE_COMPANION = 'companion';

    /**
     * Whether local_fredkichat is installed and configured.
     *
     * @return bool
     */
    public static function design_installed(): bool {
        global $DB;
        if (
            !\core_component::get_component_directory('local_fredkichat')
                || !class_exists('\local_fredkichat\client')
        ) {
            return false;
        }
        return $DB->get_manager()->table_exists('local_fredkichat_bots')
            && trim((string)get_config('local_fredkichat', 'host')) !== '';
    }

    /**
     * Chatbot designs a course may use.
     *
     * Managers of FredKI chatbots see all designs. Otherwise the folder of the course decides, as in
     * the FredKI Flex Agent block. Without that block only designs available to all users are offered.
     *
     * @param int $courseid Course id, 0 while a course is created.
     * @return array botid => name
     */
    public static function get_bot_choices(int $courseid): array {
        global $DB;
        $choices = [0 => get_string('fredkibot_none', 'format_fredki')];
        if (!self::design_installed()) {
            return $choices;
        }
        $bots = $DB->get_records('local_fredkichat_bots', null, 'name ASC', 'id, name, siteaccess');
        $allowed = null;
        if (!has_capability('local/fredkichat:manage', \context_system::instance())) {
            $allowed = [];
            foreach ($bots as $bot) {
                if (!empty($bot->siteaccess)) {
                    $allowed[(int)$bot->id] = true;
                }
            }
            $helper = '\block_fredkiflexagent\local\access_helper';
            if ($courseid > 0 && $courseid != SITEID && class_exists($helper)) {
                try {
                    foreach ($helper::get_allowed_choices($courseid) as $choice) {
                        if (($choice['type'] ?? '') === 'bot' && !empty($choice['botid'])) {
                            $allowed[(int)$choice['botid']] = true;
                        }
                    }
                } catch (\Throwable $e) {
                    debugging('FredKI choices could not be loaded: ' . $e->getMessage(), DEBUG_DEVELOPER);
                }
            }
        }
        $current = 0;
        if ($courseid > 0 && $courseid != SITEID) {
            $current = (int)(course_get_format($courseid)->get_format_options()['fredkibot'] ?? 0);
        }
        foreach ($bots as $bot) {
            if ($allowed === null || isset($allowed[(int)$bot->id]) || (int)$bot->id === $current) {
                $choices[(int)$bot->id] = format_string($bot->name);
            }
        }
        return $choices;
    }

    /**
     * Chatbot design chosen for a course.
     *
     * @param \stdClass $course Course.
     * @return \stdClass|null
     */
    protected static function get_design(\stdClass $course): ?\stdClass {
        global $DB;
        if (!self::design_installed()) {
            return null;
        }
        $botid = (int)(course_get_format($course)->get_format_options()['fredkibot'] ?? 0);
        if ($botid <= 0) {
            return null;
        }
        return $DB->get_record('local_fredkichat_bots', ['id' => $botid]) ?: null;
    }

    /**
     * Which source answers in a course right now, independent of the current user.
     *
     * @param \stdClass $course Course.
     * @return string Source constant or empty string if FredKI cannot answer.
     */
    public static function get_source(\stdClass $course): string {
        global $CFG;
        require_once($CFG->dirroot . '/course/format/lib.php');
        // The learning companion comes into the course like the team agents, through their block. It also works next
        // to the expert round: there, a question can go privately to FredKI or to the agents of the round.
        if (council::companion((int)$course->id)) {
            return self::SOURCE_COMPANION;
        }
        // In the expert round without a learning companion, the team agents speak.
        if (council::mode($course) === council::MODE_TEAM) {
            return '';
        }
        return self::get_design($course) !== null ? self::SOURCE_DESIGN : '';
    }

    /**
     * Whether the current user can talk with FredKI in a course.
     *
     * @param \stdClass $course Course.
     * @return bool
     */
    public static function is_available(\stdClass $course): bool {
        return has_capability('format/fredki:usefredki', \context_course::instance($course->id))
            && self::get_source($course) !== '';
    }

    /**
     * Prompt template of a mode: site setting or language default.
     *
     * @param string $mode Mode.
     * @return string
     */
    public static function get_template(string $mode): string {
        $template = trim((string)get_config('format_fredki', 'prompt_' . $mode));
        return $template !== '' ? $template : get_string('prompt_' . $mode . '_default', 'format_fredki');
    }

    /**
     * Build the message sent to FredKI.
     *
     * The agent keeps its own instructions in FredKI. This message only adds the situation in the
     * course: the learning step and, where the mode asks for it, recent contributions of the group.
     *
     * @param string $mode Mode.
     * @param \stdClass $course Course.
     * @param int $sectionnum Learning step, 0 for the learning space.
     * @param string $message Question of the learner (modes "ask" and "mention").
     * @param int $discussionid Conversation (modes "perspective", "mention", "answer").
     * @return string
     */
    public static function build_message(
        string $mode,
        \stdClass $course,
        int $sectionnum,
        string $message,
        int $discussionid
    ): string {
        if (!in_array($mode, self::MODES, true)) {
            throw new \moodle_exception('invalidmode', 'format_fredki');
        }
        $context = \context_course::instance($course->id);
        $step = '';
        if ($sectionnum > 0) {
            $section = get_fast_modinfo($course)->get_section_info($sectionnum);
            if ($section && $section->uservisible) {
                $step = get_section_name($course, $section);
            }
        }
        $posts = '';
        if ($mode !== 'ask' && $mode !== 'define') {
            $single = in_array($mode, ['perspective', 'mention', 'answer'], true);
            $posts = community::text_for_fredki($course, $single ? $discussionid : 0, $mode === 'impulse' ? 8 : 10);
            if ($posts === '') {
                $posts = get_string('noposts_forfredki', 'format_fredki');
            }
        }
        $replace = [
            '{course}' => format_string($course->fullname, true, ['context' => $context]),
            '{step}' => $step !== '' ? get_string('prompt_step', 'format_fredki', $step) : '',
            '{posts}' => \core_text::substr($posts, 0, 3500),
            '{message}' => \core_text::substr(trim($message), 0, 1500),
        ];
        $text = strtr(self::get_template($mode), $replace);
        // Same line as for the team agents: agents with strict prompts still answer in the course language.
        $head = get_string('prompt_language', 'format_fredki', self::answer_language($course, $message));
        if (in_array($mode, ['ask', 'answer', 'mention', 'perspective'], true)) {
            // The first sentence is the answer: it is also the preview on the card.
            $head .= "\n" . get_string('prompt_style', 'format_fredki');
        }
        return $head . "\n\n" . $text;
    }

    /**
     * Language FredKI answers in: the forced course language, the language of the question, otherwise the language
     * of the person.
     *
     * @param \stdClass $course Course.
     * @param string $message Question of the person, if any.
     * @return string English name of the language, e.g. "German".
     */
    public static function answer_language(\stdClass $course, string $message = ''): string {
        $lang = (string)($course->lang ?? '');
        $detect = '\\block_fredkiteamagents\\local\\responder';
        if ($lang === '' && trim($message) !== '' && method_exists($detect, 'detect_language')) {
            // The language the person writes in, as for the team agents.
            $lang = $detect::detect_language($message);
        }
        if ($lang === '' || (strlen($lang) > 2 && !get_string_manager()->translation_exists($lang))) {
            $lang = current_language();
        }
        $code = explode('_', $lang)[0];
        $names = get_string_manager()->get_list_of_languages('en');
        return $names[$code] ?? $code;
    }

    /**
     * Send a message to the learning companion and wait for the whole answer.
     *
     * @param \stdClass $course Course.
     * @param string $source Source constant.
     * @param string $prompt Message.
     * @param array $history Earlier turns.
     * @return string
     */
    protected static function send_agent(\stdClass $course, string $source, string $prompt, array $history): string {
        $agent = council::companion((int)$course->id);
        if (!$agent) {
            throw new \moodle_exception('nofredki', 'format_fredki');
        }
        \core_php_time_limit::raise(360);
        $answer = \block_fredkiteamagents\local\gateway::chat(
            $agent,
            'companion',
            \core_text::substr($prompt, 0, 15000),
            $history
        );
        if (trim($answer) === '') {
            throw new \moodle_exception('fredkierror', 'format_fredki');
        }
        return $answer;
    }

    /**
     * Ask FredKI and stream the answer.
     *
     * @param \stdClass $course Course.
     * @param string $prompt Message for FredKI.
     * @param string $threadkey Key of a conversation that keeps its context, empty for a fresh one.
     * @param callable $onchunk Called with each piece of the answer.
     * @return string Full answer.
     */
    public static function stream(\stdClass $course, string $prompt, string $threadkey, callable $onchunk): string {
        global $SESSION;
        $source = self::get_source($course);
        if ($source === '') {
            throw new \moodle_exception('nofredki', 'format_fredki');
        }
        $sessionstate = $SESSION->format_fredki ?? [];
        if (!is_array($sessionstate)) {
            $sessionstate = [];
        }
        $full = '';

        if ($source === self::SOURCE_COMPANION) {
            $history = $threadkey !== '' ? ($sessionstate['history'][$threadkey] ?? []) : [];
            $answer = self::send_agent($course, $source, $prompt, is_array($history) ? $history : []);
            if ($threadkey !== '') {
                $history[] = ['role' => 'user', 'content' => \core_text::substr($prompt, 0, 4000)];
                $history[] = ['role' => 'assistant', 'content' => \core_text::substr($answer, 0, 4000)];
                $sessionstate['history'][$threadkey] = array_slice($history, -10);
                $SESSION->format_fredki = $sessionstate;
            }
            \core\session\manager::write_close();
            // The learning companion answers in one piece; it is revealed in small steps like a stream.
            foreach (preg_split('/(?<=\s)/u', $answer) as $part) {
                $full .= $part;
                $onchunk($part);
            }
            return $full;
        }

        $bot = self::get_design($course);
        \local_fredkichat\bot_helper::check_rate_limit($bot, false);
        $client = new \local_fredkichat\client((string)get_config('local_fredkichat', 'host'), (string)$bot->token);
        $state = $threadkey !== '' ? ($sessionstate['design'][$threadkey] ?? null) : null;
        if (!is_array($state) || empty($state['bearer']) || empty($state['chatid'])) {
            $state = $client->open_session();
            if ($threadkey !== '') {
                $sessionstate['design'][$threadkey] = $state;
                $SESSION->format_fredki = $sessionstate;
            }
        }
        \core\session\manager::write_close();
        $client->stream_message(
            $state['bearer'],
            $state['chatid'],
            $prompt,
            static function (string $content) use (&$full, $onchunk): void {
                $full .= $content;
                $onchunk($content);
            },
            static function (): void {
            }
        );
        return $full;
    }

    /**
     * Ask FredKI and wait for the whole answer (for contributions FredKI posts itself).
     *
     * @param \stdClass $course Course.
     * @param string $prompt Message for FredKI.
     * @return string
     */
    public static function complete(\stdClass $course, string $prompt): string {
        $source = self::get_source($course);
        if ($source === self::SOURCE_COMPANION) {
            return trim(self::send_agent($course, $source, $prompt, []));
        }
        if ($source === '') {
            throw new \moodle_exception('nofredki', 'format_fredki');
        }
        $bot = self::get_design($course);
        $client = new \local_fredkichat\client((string)get_config('local_fredkichat', 'host'), (string)$bot->token);
        $state = $client->open_session();
        $full = '';
        $client->stream_message(
            $state['bearer'],
            $state['chatid'],
            $prompt,
            static function (string $content) use (&$full): void {
                $full .= $content;
            },
            static function (): void {
            }
        );
        if (trim($full) === '') {
            throw new \moodle_exception('fredkierror', 'format_fredki');
        }
        return trim($full);
    }

    /**
     * Remember an answer that the learner may later share as FredKI's public contribution.
     *
     * @param int $courseid Course id.
     * @param int $discussionid Conversation the answer belongs to, 0 for none.
     * @param string $answer Answer.
     * @return string Token.
     */
    public static function remember_answer(int $courseid, int $discussionid, string $answer): string {
        global $USER;
        $token = random_string(24);
        \cache::make('format_fredki', 'answers')->set(sha1($token . '|' . $USER->id), [
            'courseid' => $courseid,
            'discussionid' => $discussionid,
            'answer' => $answer,
        ]);
        return $token;
    }

    /**
     * Take a remembered answer. Only the learner who received it can take it, and only once.
     *
     * @param string $token Token.
     * @param int $courseid Course id.
     * @return array|null ['discussionid' => int, 'answer' => string]
     */
    public static function take_answer(string $token, int $courseid): ?array {
        global $USER;
        $cache = \cache::make('format_fredki', 'answers');
        $key = sha1($token . '|' . $USER->id);
        $data = $cache->get($key);
        if (!is_array($data) || (int)$data['courseid'] !== $courseid) {
            return null;
        }
        $cache->delete($key);
        return $data;
    }
}
