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
 * Server-sent events endpoint: FredKI answers inside a course of the FredKI format.
 *
 * @package    format_fredki
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Output must reach the browser immediately.
define('NO_OUTPUT_BUFFERING', true);

require_once(__DIR__ . '/../../../config.php');

use format_fredki\local\community;
use format_fredki\local\fredki;
use format_fredki\local\fredkiposter;

$courseid = required_param('courseid', PARAM_INT);
$mode = required_param('mode', PARAM_ALPHA);
$message = optional_param('message', '', PARAM_RAW_TRIMMED);
$sectionnum = optional_param('section', 0, PARAM_INT);
$discussionid = optional_param('discussionid', 0, PARAM_INT);
$threadid = optional_param('threadid', 'main', PARAM_ALPHANUMEXT);

$course = get_course($courseid);
require_login($course, false);
require_sesskey();
$context = context_course::instance($course->id);
require_capability('format/fredki:usefredki', $context);
$PAGE->set_context($context);

$setuperror = '';
$prompt = '';
try {
    if ($course->format !== 'fredki' || !in_array($mode, ['ask', 'summary', 'contrast', 'perspective', 'impulse'], true)) {
        throw new moodle_exception('invalidmode', 'format_fredki');
    }
    if ($mode === 'ask' && ($message === '' || core_text::strlen($message) > 4000)) {
        throw new moodle_exception('emptymessage', 'format_fredki');
    }
    if (!fredki::is_available($course)) {
        throw new moodle_exception('nofredki', 'format_fredki');
    }
    if ($discussionid > 0) {
        community::use_forum_of_discussion($course, $discussionid);
        // The learner must be able to see the conversation FredKI is asked about.
        $community = community::require_forum($course);
        if (!community::load_discussions($course, $community, 1, $discussionid)) {
            throw new moodle_exception('nopermissions', 'error', '', 'fredki');
        }
    }
    // Definitions of the group:: a question for a definition gets a concise answer that is kept in the glossary.
    $isdefinition = $mode === 'ask' && \format_fredki\local\definitions::enabled($course)
        && \format_fredki\local\definitions::is_definition_question($message);
    $prompt = fredki::build_message($isdefinition ? 'define' : $mode, $course, $sectionnum, $message, $discussionid);
} catch (Throwable $e) {
    $setuperror = $e instanceof moodle_exception ? $e->getMessage() : get_string('fredkierror', 'format_fredki');
}

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate, no-transform');
header('X-Accel-Buffering: no');
header_remove('Content-Length');
if (function_exists('apache_setenv')) {
    apache_setenv('no-gzip', '1');
}

$emit = static function (array $payload): void {
    echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
    // Some FastCGI and proxy stacks coalesce small writes. An ignored SSE comment forces a flush.
    echo ':pad ' . str_repeat(' ', 4096) . "\n\n";
    flush();
};

echo ':open ' . str_repeat(' ', 8192) . "\n\n";
flush();

if ($setuperror !== '') {
    \core\session\manager::write_close();
    $emit(['type' => 'error', 'error' => $setuperror]);
    die();
}

$emit(['type' => 'status', 'status' => 'thinking']);

// While FredKI works (it may take minutes with large models), the browser hears a short comment now and then,
// so no web server or proxy on the way closes the quiet connection.
$gatewayclass = '\\block_fredkiteamagents\\local\\gateway';
if (class_exists($gatewayclass) && property_exists($gatewayclass, 'heartbeat')) {
    \block_fredkiteamagents\local\gateway::$heartbeat = static function (): void {
        echo ':wait ' . str_repeat(' ', 2048) . "\n\n";
        flush();
    };
}

try {
    $buffer = '';
    $threadkey = $mode === 'ask' ? $course->id . '_' . $threadid : '';
    $full = fredki::stream(
        $course,
        $prompt,
        $threadkey,
        static function (string $content) use ($emit, &$buffer, $isdefinition): void {
            if ($isdefinition) {
                // The raw "TERM: … DEFINITION: …" is not shown; the definition follows in one piece.
                return;
            }
            $buffer .= $content;
            if (
                core_text::strlen($buffer) >= 16 || strpos($content, "\n") !== false
                    || preg_match('/[.!?;:]\s*$/u', $content)
            ) {
                $emit(['type' => 'chunk', 'content' => $buffer]);
                $buffer = '';
            }
        }
    );
    if ($buffer !== '') {
        $emit(['type' => 'chunk', 'content' => $buffer]);
    }
    $done = ['type' => 'done'];
    if ($isdefinition) {
        $parsed = \format_fredki\local\definitions::parse($full);
        if ($parsed) {
            [$term, $definition] = $parsed;
            $full = '**' . $term . '**: ' . $definition;
            $saved = \format_fredki\local\definitions::add($course, $term, $definition, $sectionnum);
            $done['term'] = $term;
            $done['termsaved'] = $saved['saved'];
            $done['termexists'] = $saved['exists'];
            $done['termurl'] = $saved['url'];
            $done['termdefinition'] = $definition;
        }
        $emit(['type' => 'chunk', 'content' => $full]);
    }
    if ($mode === 'impulse') {
        // Some agents ignore "one short question" and write a long text. Only a short question is used.
        $question = fredkiposter::extract_question($full);
        if ($question === '') {
            $question = fredkiposter::fallback_question();
        }
        $done['question'] = $question;
        $options = course_get_format($course)->get_format_options();
        if (!empty($options['fredkiquestions'])) {
            $done['discussionid'] = fredkiposter::post_daily_question($course, $question);
        }
    } else {
        // The learner may decide to share this answer as FredKI's public contribution.
        $done['token'] = fredki::remember_answer((int)$course->id, $discussionid, $full);
        // The talk is also kept in the person's Moodle messages with the learning companion.
        if (
            fredki::get_source($course) === fredki::SOURCE_COMPANION
                && method_exists('\\block_fredkiteamagents\\local\\companionmessage', 'mirror')
        ) {
            try {
                \block_fredkiteamagents\local\companionmessage::mirror((int)$course->id, (int)$USER->id, $message, $full);
            } catch (Throwable $mirrorerror) {
                debugging('FredKI talk not kept in messages: ' . $mirrorerror->getMessage(), DEBUG_DEVELOPER);
            }
        }
    }
    $emit($done);
} catch (Throwable $e) {
    $error = $e instanceof moodle_exception ? $e->getMessage() : get_string('fredkierror', 'format_fredki');
    // Teachers see the safe diagnostic code (e.g. DIAG-TEAMAGENTS-teamagents/chat-HTTP-502), so they know where to look.
    if (
        $e instanceof moodle_exception && preg_match('/^DIAG-[A-Za-z0-9\/_ -]{2,120}$/', (string)$e->debuginfo)
            && has_capability('moodle/course:update', context_course::instance($course->id))
    ) {
        $error .= ' (' . $e->debuginfo . ')';
    }
    debugging('FredKI answer failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
    $emit(['type' => 'error', 'error' => $error]);
}
