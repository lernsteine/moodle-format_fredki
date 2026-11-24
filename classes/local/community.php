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
 * The learning group ("Runde"): a Moodle forum shown as a calm wall of images and short thoughts.
 *
 * All contributions are real forum posts, so they stay visible in the forum, in notifications,
 * in backups and in reports.
 *
 * @package    format_fredki
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class community {
    /** @var string[] Forum types that work as an open conversation of the group. */
    const FORUM_TYPES = ['general', 'eachuser', 'blog'];

    /** @var string[] Kinds of contributions. */
    const TYPES = ['question', 'idea', 'experience'];

    /** @var string[] Card shapes, cycled for a lively but ordered wall. */
    const SHAPES = ['wide', 'tall', 'square', 'wide', 'square', 'tall'];

    /**
     * Forums of a course that can carry the conversation of the group.
     *
     * @param int $courseid Course id, 0 while a course is created.
     * @return array id => name, 0 => automatic choice
     */
    public static function get_forum_choices(int $courseid): array {
        global $DB;
        $choices = [0 => get_string('communityforum_auto', 'format_fredki')];
        if ($courseid <= 0 || $courseid == SITEID) {
            return $choices;
        }
        [$insql, $params] = $DB->get_in_or_equal(self::FORUM_TYPES, SQL_PARAMS_NAMED);
        $params['course'] = $courseid;
        $forums = $DB->get_records_select('forum', "course = :course AND type $insql", $params, 'name ASC', 'id, name');
        foreach ($forums as $forum) {
            $choices[$forum->id] = format_string($forum->name, true, ['context' => \context_course::instance($courseid)]);
        }
        return $choices;
    }

    /**
     * Give a course in this format its forum for the conversations, once: when it has no open forum yet, a forum
     * "Course conversations" is added at the top of the course. A forum the teacher deletes later is not added again.
     *
     * @param \stdClass $course Course.
     * @return bool Whether a forum was created.
     */
    public static function ensure_forum(\stdClass $course): bool {
        global $CFG, $DB, $USER;
        if ((int)$course->id === SITEID || $course->format !== 'fredki') {
            return false;
        }
        if (empty($USER->id) || isguestuser()) {
            // Without a real user (e.g. a course created by a script) the forum cannot be added now;
            // the first visit of a trainer adds it.
            return false;
        }
        require_once($CFG->dirroot . '/course/lib.php');
        $format = course_get_format($course);
        if (!empty($format->get_format_options()['forumprepared'])) {
            return false;
        }
        [$insql, $params] = $DB->get_in_or_equal(self::FORUM_TYPES, SQL_PARAMS_NAMED);
        $params['course'] = $course->id;
        $exists = $DB->record_exists_select('forum', "course = :course AND type $insql", $params);
        $moduleid = (int)$DB->get_field('modules', 'id', ['name' => 'forum', 'visible' => 1]);
        // Mark first, so events fired while the forum is added cannot add a second one.
        $format->update_course_format_options(['forumprepared' => 1]);
        $created = false;
        if (!$exists && $moduleid) {
            require_once($CFG->dirroot . '/course/modlib.php');
            course_create_sections_if_missing($course, 0);
            $moduleinfo = (object)[
                'modulename' => 'forum',
                'module' => $moduleid,
                'course' => $course->id,
                'section' => 0,
                'visible' => 1,
                'visibleoncoursepage' => 1,
                'name' => get_string('autoforum_name', 'format_fredki'),
                'introeditor' => ['text' => get_string('autoforum_intro', 'format_fredki'), 'format' => FORMAT_HTML,
                    'itemid' => 0],
                'type' => 'general',
                'forcesubscribe' => 0,
                'trackingtype' => 1,
                'maxbytes' => 0,
                'maxattachments' => 9,
                'displaywordcount' => 0,
                'blockperiod' => 0,
                'blockafter' => 0,
                'warnafter' => 0,
                'assessed' => 0,
                'scale' => 0,
                'grade_forum' => 0,
                'groupmode' => 0,
                'groupingid' => 0,
                'cmidnumber' => '',
                'completion' => 0,
            ];
            add_moduleinfo($moduleinfo, $course);
            rebuild_course_cache($course->id, true);
            $created = true;
        }
        return $created;
    }

    /** @var array Forum of a learning step that this request works on instead of the course forum, per course. */
    protected static $override = [];

    /**
     * Id of the forum of the course conversations, without checking what the user may see.
     *
     * @param \stdClass $course Course.
     * @return int 0 when there is none.
     */
    public static function course_forum_id(\stdClass $course): int {
        $saved = self::$override[(int)$course->id] ?? null;
        unset(self::$override[(int)$course->id]);
        try {
            return (int)self::require_forum_record($course)['forum']->id;
        } catch (\moodle_exception $e) {
            return 0;
        } finally {
            if ($saved) {
                self::$override[(int)$course->id] = $saved;
            }
        }
    }

    /**
     * The conversation forum of a learning step: the first open forum in that section that is not the forum of the
     * course conversations, if the step uses one and the user can see it.
     *
     * @param \stdClass $course Course.
     * @param \section_info $section Section.
     * @return array|null Like get_forum().
     */
    public static function step_forum(\stdClass $course, \section_info $section): ?array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/format/lib.php');
        $options = course_get_format($course)->get_format_options($section);
        if (($options['stepforum'] ?? 'auto') === 'off') {
            return null;
        }
        $modinfo = get_fast_modinfo($course);
        $courseforum = self::course_forum_id($course);
        foreach ($modinfo->sections[$section->section] ?? [] as $cmid) {
            $cm = $modinfo->get_cm($cmid);
            if ($cm->modname !== 'forum' || (int)$cm->instance === $courseforum || $cm->deletioninprogress) {
                continue;
            }
            $forum = $DB->get_record('forum', ['id' => $cm->instance]);
            if (!$forum || !in_array($forum->type, self::FORUM_TYPES, true)) {
                continue;
            }
            if (!$cm->uservisible) {
                return null;
            }
            $context = \context_module::instance($cm->id);
            if (!has_capability('mod/forum:viewdiscussion', $context)) {
                return null;
            }
            return ['forum' => $forum, 'cm' => $cm, 'context' => $context, 'step' => (int)$section->section];
        }
        return null;
    }

    /**
     * Work on the forum of a learning step for the rest of this request (0: the course conversations).
     *
     * @param \stdClass $course Course.
     * @param int $forumid Forum id.
     */
    public static function use_forum(\stdClass $course, int $forumid): void {
        unset(self::$override[(int)$course->id]);
        if ($forumid <= 0 || $forumid === self::course_forum_id($course)) {
            return;
        }
        $modinfo = get_fast_modinfo($course);
        $instances = $modinfo->get_instances_of('forum');
        if (empty($instances[$forumid])) {
            throw new \moodle_exception('nocommunity', 'format_fredki');
        }
        $step = self::step_forum($course, $modinfo->get_section_info($instances[$forumid]->sectionnum));
        if (!$step || (int)$step['forum']->id !== $forumid) {
            throw new \moodle_exception('nocommunity', 'format_fredki');
        }
        self::$override[(int)$course->id] = $step;
    }

    /**
     * Work on the forum a conversation belongs to.
     *
     * @param \stdClass $course Course.
     * @param int $discussionid Conversation.
     */
    public static function use_forum_of_discussion(\stdClass $course, int $discussionid): void {
        global $DB;
        $forumid = (int)$DB->get_field('forum_discussions', 'forum', ['id' => $discussionid, 'course' => $course->id]);
        self::use_forum($course, $forumid);
    }

    /**
     * Work on the forum a post belongs to.
     *
     * @param \stdClass $course Course.
     * @param int $postid Post.
     */
    public static function use_forum_of_post(\stdClass $course, int $postid): void {
        global $DB;
        $discussionid = (int)$DB->get_field('forum_posts', 'discussion', ['id' => $postid]);
        if ($discussionid) {
            self::use_forum_of_discussion($course, $discussionid);
        }
    }

    /**
     * The forum of the learning group and its course module, if it exists and the user can see it.
     *
     * @param \stdClass $course Course.
     * @return array|null ['forum' => stdClass, 'cm' => \cm_info, 'context' => \context_module]
     */
    public static function get_forum(\stdClass $course): ?array {
        global $CFG, $DB;
        if (isset(self::$override[(int)$course->id])) {
            return self::$override[(int)$course->id];
        }
        require_once($CFG->dirroot . '/course/format/lib.php');
        $options = course_get_format($course)->get_format_options();
        $modinfo = get_fast_modinfo($course);
        $forumid = (int)($options['communityforum'] ?? 0);
        $forum = null;
        if ($forumid > 0) {
            $forum = $DB->get_record('forum', ['id' => $forumid, 'course' => $course->id]);
        }
        if (!$forum) {
            // Automatic choice: the first open forum in course order.
            [$insql, $params] = $DB->get_in_or_equal(self::FORUM_TYPES, SQL_PARAMS_NAMED);
            $params['course'] = $course->id;
            $candidates = $DB->get_records_select('forum', "course = :course AND type $insql", $params);
            foreach ($modinfo->get_instances_of('forum') as $instanceid => $cm) {
                if (isset($candidates[$instanceid])) {
                    $forum = $candidates[$instanceid];
                    break;
                }
            }
        }
        if (!$forum) {
            return null;
        }
        $instances = $modinfo->get_instances_of('forum');
        if (empty($instances[$forum->id])) {
            return null;
        }
        $cm = $instances[$forum->id];
        if (!$cm->uservisible) {
            return null;
        }
        $context = \context_module::instance($cm->id);
        if (!has_capability('mod/forum:viewdiscussion', $context)) {
            return null;
        }
        return ['forum' => $forum, 'cm' => $cm, 'context' => $context];
    }

    /**
     * Require the forum of the learning group.
     *
     * @param \stdClass $course Course.
     * @return array
     * @throws \moodle_exception
     */
    public static function require_forum(\stdClass $course): array {
        $community = self::get_forum($course);
        if (!$community) {
            throw new \moodle_exception('nocommunity', 'format_fredki');
        }
        return $community;
    }

    /**
     * Load the latest discussions the current user may see, with their first post and author.
     *
     * @param \stdClass $course Course.
     * @param array $community Result of get_forum().
     * @param int $limit Maximum number of discussions.
     * @param int $discussionid Restrict to one discussion.
     * @param int $offset Discussions to skip.
     * @param string $query Search text, empty for all.
     * @param int $topicid Topic field, 0 for all, -1 for conversations without topic field.
     * @return \stdClass[]
     */
    public static function load_discussions(
        \stdClass $course,
        array $community,
        int $limit,
        int $discussionid = 0,
        int $offset = 0,
        string $query = '',
        int $topicid = 0
    ): array {
        global $DB, $USER;
        $forum = $community['forum'];
        $cm = $community['cm'];
        $context = $community['context'];

        $where = 'd.forum = :forumid';
        $params = ['forumid' => $forum->id];
        if ($discussionid > 0) {
            $where .= ' AND d.id = :discussionid';
            $params['discussionid'] = $discussionid;
        }
        if (!has_capability('mod/forum:viewhiddentimedposts', $context)) {
            $where .= ' AND (((d.timestart = 0 OR d.timestart <= :now1) AND (d.timeend = 0 OR d.timeend > :now2))
                        OR d.userid = :me)';
            $now = time();
            $params += ['now1' => $now, 'now2' => $now, 'me' => $USER->id];
        }
        $groupmode = groups_get_activity_groupmode($cm, $course);
        if ($groupmode == SEPARATEGROUPS && !has_capability('moodle/site:accessallgroups', $context)) {
            $groups = array_keys(groups_get_all_groups($course->id, $USER->id, $cm->groupingid, 'g.id'));
            $groups[] = -1;
            [$insql, $inparams] = $DB->get_in_or_equal($groups, SQL_PARAMS_NAMED, 'grp');
            $where .= " AND d.groupid $insql";
            $params += $inparams;
        }
        $query = trim($query);
        if ($query !== '') {
            // Simple search: subject, opening post or any visible reply.
            $like = '%' . $DB->sql_like_escape($query) . '%';
            $where .= ' AND (' . $DB->sql_like('d.name', ':q1', false) . ' OR ' . $DB->sql_like('p.message', ':q2', false)
                . ' OR EXISTS (SELECT 1 FROM {forum_posts} r WHERE r.discussion = d.id AND r.deleted = 0'
                . ' AND r.privatereplyto = 0 AND ' . $DB->sql_like('r.message', ':q3', false) . '))';
            $params += ['q1' => $like, 'q2' => $like, 'q3' => $like];
        }
        if ($topicid > 0) {
            $where .= ' AND EXISTS (SELECT 1 FROM {' . topics::MAP . '} tm WHERE tm.discussionid = d.id AND tm.topicid = :topicid)';
            $params['topicid'] = $topicid;
        } else if ($topicid < 0) {
            $where .= ' AND NOT EXISTS (SELECT 1 FROM {' . topics::MAP . '} tm WHERE tm.discussionid = d.id)';
        }
        $userfields = \core_user\fields::for_userpic()->get_sql('u', false, 'u_', '', true);
        $sql = "SELECT d.id, d.name, d.firstpost, d.timemodified, d.pinned, d.groupid,
                       p.message, p.messageformat, p.created, p.userid {$userfields->selects}
                  FROM {forum_discussions} d
                  JOIN {forum_posts} p ON p.id = d.firstpost
                  JOIN {user} u ON u.id = p.userid
                 WHERE $where
              ORDER BY d.pinned DESC, d.timemodified DESC";
        return $DB->get_records_sql($sql, $params + $userfields->params, $offset, $limit);
    }

    /**
     * Load the visible replies of discussions, newest last.
     *
     * @param int[] $discussionids Discussion ids.
     * @return array discussionid => \stdClass[]
     */
    public static function load_replies(array $discussionids): array {
        global $DB, $USER;
        if (!$discussionids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($discussionids, SQL_PARAMS_NAMED);
        $userfields = \core_user\fields::for_userpic()->get_sql('u', false, 'u_', '', true);
        $sql = "SELECT p.id, p.discussion, p.parent, p.message, p.messageformat, p.created, p.userid {$userfields->selects}
                  FROM {forum_posts} p
                  JOIN {user} u ON u.id = p.userid
                 WHERE p.discussion $insql AND p.parent <> 0 AND p.deleted = 0
                       AND (p.privatereplyto = 0 OR p.privatereplyto = :me1 OR p.userid = :me2)
              ORDER BY p.created ASC";
        $params += ['me1' => $USER->id, 'me2' => $USER->id];
        $result = [];
        foreach ($DB->get_records_sql($sql, $params + $userfields->params) as $reply) {
            $result[$reply->discussion][] = $reply;
        }
        return $result;
    }

    /**
     * Export discussions as image cards of the learning wall.
     *
     * @param \stdClass $course Course.
     * @param array $community Result of get_forum().
     * @param string[] $fallbackimages Image URLs used when a post has no image of its own.
     * @param int $limit Maximum number of cards.
     * @param int $excludeid Conversation shown separately (question of the day).
     * @param int $offset Cards to skip.
     * @param string $query Search text, empty for all.
     * @param int $topicid Topic field, 0 for all, -1 for conversations without topic field.
     * @return array
     */
    public static function export_cards(
        \stdClass $course,
        array $community,
        array $fallbackimages,
        int $limit,
        int $excludeid = 0,
        int $offset = 0,
        string $query = '',
        int $topicid = 0
    ): array {
        $discussions = self::load_discussions($course, $community, $limit + 1, 0, $offset, $query, $topicid);
        unset($discussions[$excludeid]);
        $discussions = array_slice($discussions, 0, $limit, true);
        return self::export_discussions($community, $discussions, $fallbackimages, $offset);
    }

    /**
     * Export loaded discussions as cards, reading likes, images and ratings for all of them at once.
     *
     * @param array $community Result of get_forum().
     * @param \stdClass[] $discussions Result of load_discussions(), keyed by id.
     * @param string[] $fallbackimages Image URLs.
     * @param int $offset Position of the first card on the wall.
     * @return array
     */
    public static function export_discussions(
        array $community,
        array $discussions,
        array $fallbackimages,
        int $offset = 0
    ): array {
        $cards = [];
        $i = $offset;
        $replies = self::load_replies(array_keys($discussions));
        $postids = [];
        foreach ($discussions as $d) {
            $postids[] = (int)$d->firstpost;
            foreach ($replies[$d->id] ?? [] as $reply) {
                $postids[] = (int)$reply->id;
            }
        }
        // Hearts belong to single posts: the opening post and each reply.
        $likes = self::get_likes($postids);
        $aiposts = self::get_ai_posts($postids);
        $meta = self::get_meta($postids);
        self::preload_images($community['context'], array_map(fn($d) => (int)$d->firstpost, $discussions));
        self::$maturity += quality::for_discussions(array_map(fn($d) => (int)$d->id, $discussions));
        topics::of_discussions(array_keys($discussions));
        foreach ($discussions as $d) {
            self::$maturity[(int)$d->id] ??= null;
        }
        foreach ($discussions as $d) {
            $cards[] = self::export_card(
                $d,
                $replies[$d->id] ?? [],
                $community,
                $likes,
                $aiposts,
                $fallbackimages,
                $i,
                $meta
            );
            $i++;
        }
        return $cards;
    }

    /**
     * Export one discussion as card.
     *
     * @param \stdClass $d Discussion with first post and author fields.
     * @param \stdClass[] $replies Replies of the discussion.
     * @param array $community Result of get_forum().
     * @param array $likes Likes per post id.
     * @param array $aiposts Post ids that carry a FredKI contribution.
     * @param string[] $fallbackimages Image URLs.
     * @param int $index Position on the wall.
     * @param array $meta Kind of each post, see get_meta().
     * @param bool $full Whole conversation (all replies, full texts) instead of the card on the wall.
     * @return array
     */
    public static function export_card(
        \stdClass $d,
        array $replies,
        array $community,
        array $likes,
        array $aiposts,
        array $fallbackimages,
        int $index,
        array $meta = [],
        bool $full = false
    ): array {
        $context = $community['context'];
        $author = \user_picture::unalias($d, null, 'u_id', 'u_');
        $byfredki = botuser::is_bot((int)$d->userid);
        $type = (string)($meta[(int)$d->firstpost]->type ?? '');
        if (!in_array($type, self::TYPES, true)) {
            $type = '';
        }
        $image = self::get_post_image($context, (int)$d->firstpost);
        $imagestyle = '';
        if ($image === '') {
            // Without an own picture: the picture of its topic field (same picture, same theme), else a neutral one.
            // Fixed per conversation, so a card keeps its picture wherever it stands on the wall.
            if (isset($community['step'])) {
                // A conversation of a learning step: a calm section of the picture of that step.
                $image = images::step_picture((int)$community['forum']->course, (int)$community['step']);
                $imagestyle = images::section_style((int)$d->id);
            } else {
                [$image, $imagestyle] = self::topic_picture((int)$community['forum']->course, (int)$d->id);
            }
        }
        $postlikes = $likes[(int)$d->firstpost] ?? ['count' => 0, 'mine' => false];

        $courseid = (int)$community['forum']->course;
        $course = self::course($courseid);
        $exportreplies = [];
        $shortlength = $full ? 4000 : 180;
        // Who wrote which post, so a reply to a single post can say whom it answers.
        $names = [(int)$d->firstpost => $byfredki ? 'FredKI' : (string)$author->firstname];
        foreach ($replies as $reply) {
            $replyagent = council::agent_of($courseid, (int)$reply->userid);
            $names[(int)$reply->id] = $replyagent ? $replyagent['name']
                : (botuser::is_bot((int)$reply->userid) ? 'FredKI' : (string)($reply->u_firstname ?? ''));
        }
        // A card only shows the newest answer as a preview; the conversation shows all of them.
        foreach ($full ? $replies : array_slice($replies, -1) as $reply) {
            $replyauthor = \user_picture::unalias($reply, null, 'u_id', 'u_');
            $replybot = botuser::is_bot((int)$reply->userid);
            $shared = isset($aiposts[(int)$reply->id]);
            $agent = council::agent_of($courseid, (int)$reply->userid);
            $replylikes = $likes[(int)$reply->id] ?? ['count' => 0, 'mine' => false];
            $exportreply = [
                'postid' => (int)$reply->id,
                'likes' => (int)$replylikes['count'],
                'liked' => (bool)$replylikes['mine'],
                'isagent' => (bool)$agent,
                'isfredki' => !$agent && ($replybot || $shared),
                'sharedby' => $shared && !$replybot ? (string)$replyauthor->firstname : '',
                'issharedby' => $shared && !$replybot,
                'text' => shorten_text($plainreply = self::plain(
                    $reply->message,
                    (int)$reply->messageformat,
                    $replybot || $shared
                ), $shortlength, true),
                'fulltext' => shorten_text($plainreply, 4000, true),
                'truncated' => !$full && \core_text::strlen($plainreply) > 180,
                'author' => self::export_user($replyauthor),
                // Agents address the person by name anyway ("Ralf, …"); the marker is for people only.
                'isreplyto' => !$agent && !empty($reply->parent) && (int)$reply->parent !== (int)$d->firstpost
                    && isset($names[(int)$reply->parent]),
                'replyto' => $names[(int)($reply->parent ?? 0)] ?? '',
                'canedit' => !$agent && self::can_edit($reply, $aiposts),
                'edituntil' => $agent ? 0 : self::edit_until($reply, $aiposts),
            ];
            if ($agent) {
                $exportreply['agent'] = ['name' => $agent['name'], 'colour' => $agent['colour'],
                    'image' => $agent['image']];
            }
            $exportreplies[] = $exportreply;
        }

        // Who has spoken: the last post of a person (agents answer people), and the agents not heard yet.
        $lastperson = (int)$d->firstpost;
        $heard = [];
        foreach ($replies as $reply) {
            if (council::agent_of($courseid, (int)$reply->userid)) {
                $heard[(int)$reply->userid] = true;
            } else if (!botuser::is_bot((int)$reply->userid)) {
                $lastperson = (int)$reply->id;
            }
        }
        // Who has answered, as a small row of faces on the card.
        $voices = [];
        foreach (array_reverse($replies) as $reply) {
            $agent = council::agent_of($courseid, (int)$reply->userid);
            $bot = !$agent && botuser::is_bot((int)$reply->userid);
            $key = $bot ? 'fredki' : (int)$reply->userid;
            if (isset($voices[$key])) {
                continue;
            }
            if ($agent) {
                $voices[$key] = ['isagent' => true, 'name' => $agent['name'], 'image' => $agent['image'],
                    'colour' => $agent['colour']];
            } else if ($bot) {
                $voices[$key] = ['isfredki' => true, 'name' => 'FredKI'];
            } else {
                $person = \user_picture::unalias($reply, null, 'u_id', 'u_');
                $voices[$key] = ['isperson' => true, 'name' => (string)$person->firstname,
                    'person' => self::export_user($person)];
            }
        }
        $voicenames = implode(', ', array_column($voices, 'name'));
        $morevoices = max(0, count($voices) - 4);
        $voices = array_slice(array_values($voices), 0, 4);

        $canconvene = council::can_convene($course);
        $nextagents = [];
        if ($canconvene && $heard) {
            foreach (council::agents($courseid) as $userid => $a) {
                if ($a['ready'] && $a['active'] && empty($heard[$userid])) {
                    $nextagents[] = ['id' => (int)$a['id'], 'name' => $a['name'], 'image' => $a['image'],
                        'colour' => $a['colour']];
                }
            }
        }
        $canmoderate = has_capability('mod/forum:editanypost', $context);

        return [
            'discussionid' => (int)$d->id,
            'postid' => (int)$d->firstpost,
            'full' => $full,
            'body' => $full ? self::body_text($d) : '',
            'lastpersonpost' => $lastperson,
            'nextagents' => $nextagents,
            'hasnextagents' => !empty($nextagents),
            'canmoderate' => $canmoderate,
            'canedit' => !$byfredki && self::can_edit($firstpost = (object)['id' => $d->firstpost,
                'userid' => $d->userid, 'created' => $d->created], $aiposts),
            'edituntil' => $byfredki ? 0 : self::edit_until($firstpost, $aiposts),
            'url' => (new \moodle_url('/mod/forum/discuss.php', ['d' => $d->id]))->out(false),
            'subject' => format_string($d->name, true, ['context' => $context]),
            'excerpt' => self::excerpt($d->message, (int)$d->messageformat, 140),
            'image' => $image,
            'imagestyle' => $imagestyle,
            'withfredki' => isset($aiposts[(int)$d->firstpost]) && !$byfredki,
            'byfredki' => $byfredki,
            'type' => $type !== '' ? $type : ($byfredki ? 'question' : 'none'),
            'typelabel' => $type !== '' ? get_string('type_' . $type, 'format_fredki')
                : ($byfredki ? get_string('type_question', 'format_fredki') : ''),
            // Almost everything is a question: only ideas and experiences carry a label.
            'hastype' => in_array($type, ['idea', 'experience'], true),
            'author' => self::export_user($author),
            'time' => self::relative_time((int)$d->created),
            'likes' => (int)$postlikes['count'],
            'liked' => (bool)$postlikes['mine'],
            'replycount' => count($replies),
            'replies' => $exportreplies,
            'hasreplies' => !empty($exportreplies),
            'voices' => $voices,
            'hasvoices' => !empty($voices),
            'voicenames' => $voicenames,
            'morevoices' => $morevoices,
            'hasmorevoices' => $morevoices > 0,
        ] + self::maturity($course, (int)$d->id, $full) + (isset($community['step'])
            ? ['topicid' => 0, 'topicname' => '', 'hastopic' => false, 'canchangetopic' => false]
            : self::topic($course, $context, $d)) + [
            'shape' => self::SHAPES[$index % count(self::SHAPES)],
            'canreply' => has_capability('mod/forum:replypost', $context),
            'canconvene' => $canconvene,
        ];
    }

    /**
     * Whole text of the opening post for the conversation view, unless it only repeats the subject.
     *
     * @param \stdClass $d Discussion with first post.
     * @return string
     */
    protected static function body_text(\stdClass $d): string {
        $body = shorten_text(self::plain($d->message, (int)$d->messageformat), 6000, true);
        $subject = rtrim(trim((string)$d->name), '.…');
        $norm = static fn(string $t): string => \core_text::strtolower(preg_replace('/[\s.…]+/u', ' ', trim($t)));
        if (
            $norm($body) === $norm($subject) || ($subject !== '' && str_starts_with($norm($body), $norm($subject))
                && \core_text::strlen($body) <= \core_text::strlen($subject) + 3)
        ) {
            return '';
        }
        return $body;
    }

    /**
     * Plain short text of a post.
     *
     * @param string $message Message.
     * @param int $format Text format.
     * @param int $length Maximum length.
     * @return string
     */
    public static function excerpt(string $message, int $format, int $length): string {
        return shorten_text(self::plain($message, $format), $length, true);
    }

    /**
     * Plain text of a post without markup. A shared FredKI answer keeps a "FredKI:" label, the
     * heading of a FredKI reply is dropped because the reply is labelled already.
     *
     * @param string $message Message.
     * @param int $format Text format.
     * @param bool $isfredki Whether the post is a FredKI reply.
     * @return string
     */
    public static function plain(string $message, int $format, bool $isfredki = false): string {
        if ($format == FORMAT_HTML || $format == FORMAT_MOODLE) {
            $heading = '~<p>\s*<strong>\s*FredKI\s*</strong>\s*</p>~iu';
            $message = $isfredki ? preg_replace($heading, '', $message, 1) : preg_replace($heading, ' FredKI: ', $message);
            $message = preg_replace('~<(?:br|/p|/li|/blockquote|/h[1-6]|/div)\b[^>]*>~i', ' ', $message);
            $text = html_entity_decode(strip_tags($message), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        } else {
            $text = content_to_text($message, $format);
        }
        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    /** @var array Ratings of conversations read for a whole wall, keyed by discussion id (null = none yet). */
    protected static $maturity = [];

    /** @var bool[] Whether the maturity display is on, per course. */
    protected static $maturityon = [];

    /**
     * Maturity of a conversation for a card or the conversation view.
     *
     * @param \stdClass $course Course.
     * @param int $discussionid Conversation.
     * @param bool $full With aspects and hint.
     * @return array
     */
    protected static function maturity(\stdClass $course, int $discussionid, bool $full): array {
        $on = self::$maturityon[(int)$course->id] ??= quality::enabled($course);
        if (!$on) {
            return ['hasmaturity' => false];
        }
        if (!array_key_exists($discussionid, self::$maturity)) {
            self::$maturity += quality::for_discussions([$discussionid]);
            self::$maturity[$discussionid] ??= null;
        }
        return quality::export(self::$maturity[$discussionid], $full);
    }

    /** @var array Topic fields per course, read once per request. */
    protected static $topics = [];

    /**
     * Picture of a conversation without an own picture: that of its topic field, or the neutral one.
     *
     * @param int $courseid Course.
     * @param int $discussionid Conversation.
     * @return string URL.
     */
    public static function topic_image(int $courseid, int $discussionid): string {
        return self::topic_picture($courseid, $discussionid)[0];
    }

    /**
     * Picture of a conversation without an own picture, and the section of it to show.
     *
     * Pictures the trainers uploaded for its topic field (or for conversations without one) come first, one after
     * another per conversation. Otherwise the photo of the topic field, a calm section of it per conversation; the
     * neutral picture without a topic field.
     *
     * @param int $courseid Course.
     * @param int $discussionid Conversation.
     * @return array [url, css for the img]
     */
    public static function topic_picture(int $courseid, int $discussionid): array {
        $all = self::$topics[$courseid] ??= topics::get_all($courseid);
        $topicid = topics::of_discussions([$discussionid])[$discussionid] ?? 0;
        $topicid = isset($all[$topicid]) ? $topicid : 0;
        $own = images::uploads($courseid, $topicid);
        if ($own) {
            return [$own[$discussionid % count($own)], ''];
        }
        if ($topicid) {
            return [images::for_topic($all[$topicid]), images::section_style($discussionid)];
        }
        // Not yet in a topic field (FredKI sorts it in a moment later): a calm photo of its own, not a grey area.
        $photos = images::TOPIC_PHOTOS;
        return [images::url($photos[$discussionid % count($photos)]), images::section_style(intdiv($discussionid, 5))];
    }

    /**
     * Forget the topic fields read in this request (after a change).
     */
    public static function reset_topics(): void {
        self::$topics = [];
    }

    /**
     * Topic field of a card.
     *
     * @param \stdClass $course Course.
     * @param \context_module $context Forum context.
     * @param \stdClass $d Discussion with author.
     * @return array
     */
    protected static function topic(\stdClass $course, \context_module $context, \stdClass $d): array {
        $all = self::$topics[(int)$course->id] ??= topics::get_all((int)$course->id);
        $topicid = topics::of_discussions([(int)$d->id])[(int)$d->id] ?? 0;
        $topic = $all[$topicid] ?? null;
        return [
            'topicid' => $topic ? (int)$topic->id : 0,
            'topicname' => $topic ? topics::name($topic) : '',
            'hastopic' => (bool)$topic,
            'canchangetopic' => topics::can_change($context, (int)$d->userid),
        ];
    }

    /** @var array Images of opening posts per context and post id, read in one go for a whole wall. */
    protected static $images = [];

    /** @var \stdClass[] Courses read during this request. */
    protected static $courses = [];

    /**
     * Read the first images of many posts with one query (instead of two per card).
     *
     * @param \context_module $context Forum context.
     * @param int[] $postids Post ids.
     */
    public static function preload_images(\context_module $context, array $postids): void {
        global $DB;
        $postids = array_values(array_unique(array_filter(array_map('intval', $postids))));
        if (!$postids) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($postids, SQL_PARAMS_NAMED);
        $params += ['contextid' => $context->id, 'component' => 'mod_forum', 'dot' => '.', 'image' => 'image/%'];
        $files = $DB->get_records_select(
            'files',
            "contextid = :contextid AND component = :component
                AND filearea IN ('attachment', 'post') AND itemid $insql AND filename <> :dot
                AND " . $DB->sql_like('mimetype', ':image'),
            $params,
            "itemid, filearea ASC, filename",
            'id, itemid, filearea, filepath, filename'
        );
        foreach ($postids as $postid) {
            self::$images[$context->id][$postid] = '';
        }
        // Attachments come before inline images ("attachment" < "post"); the first one wins.
        foreach ($files as $file) {
            if (self::$images[$context->id][(int)$file->itemid] === '') {
                self::$images[$context->id][(int)$file->itemid] = \moodle_url::make_pluginfile_url(
                    $context->id,
                    'mod_forum',
                    $file->filearea,
                    (int)$file->itemid,
                    $file->filepath,
                    $file->filename
                )->out(false);
            }
        }
    }

    /**
     * A course, read once per request.
     *
     * @param int $courseid Course id.
     * @return \stdClass
     */
    protected static function course(int $courseid): \stdClass {
        return self::$courses[$courseid] ??= get_course($courseid);
    }

    /**
     * First image of a forum post (attachment or inline image).
     *
     * @param \context_module $context Forum context.
     * @param int $postid Post id.
     * @return string URL or empty string.
     */
    public static function get_post_image(\context_module $context, int $postid): string {
        if (isset(self::$images[$context->id]) && array_key_exists($postid, self::$images[$context->id])) {
            return self::$images[$context->id][$postid];
        }
        $fs = get_file_storage();
        foreach (['attachment', 'post'] as $area) {
            foreach ($fs->get_area_files($context->id, 'mod_forum', $area, $postid, 'filename', false) as $file) {
                if ($file->is_valid_image()) {
                    return \moodle_url::make_pluginfile_url(
                        $context->id,
                        'mod_forum',
                        $area,
                        $postid,
                        $file->get_filepath(),
                        $file->get_filename()
                    )->out(false);
                }
            }
        }
        return '';
    }

    /**
     * Export a user for an avatar: picture if uploaded, otherwise initials on a calm colour.
     *
     * @param \stdClass $user User with picture fields.
     * @return array
     */
    public static function export_user(\stdClass $user): array {
        global $PAGE;
        $hasavatar = !empty($user->picture);
        $url = '';
        if ($hasavatar) {
            $picture = new \user_picture($user);
            $picture->size = 100;
            $url = $picture->get_url($PAGE)->out(false);
        }
        $first = (string)($user->firstname ?? '');
        $last = (string)($user->lastname ?? '');
        $initials = \core_text::strtoupper(\core_text::substr($first, 0, 1) . \core_text::substr($last, 0, 1));
        return [
            'id' => (int)$user->id,
            'firstname' => $first,
            'fullname' => fullname($user),
            'initials' => $initials,
            'hasavatar' => $hasavatar,
            'avatarurl' => $url,
            'colour' => 'c' . (((int)$user->id % 5) + 1),
        ];
    }

    /**
     * Short relative time.
     *
     * @param int $time Timestamp.
     * @return string
     */
    public static function relative_time(int $time): string {
        $diff = max(0, time() - $time);
        if ($diff < MINSECS) {
            return get_string('justnow', 'format_fredki');
        }
        if ($diff < HOURSECS) {
            return get_string('minutesago', 'format_fredki', (int)floor($diff / MINSECS));
        }
        if ($diff < DAYSECS) {
            return get_string('hoursago', 'format_fredki', (int)floor($diff / HOURSECS));
        }
        if ($diff < 7 * DAYSECS) {
            return get_string('daysago', 'format_fredki', (int)floor($diff / DAYSECS));
        }
        return userdate($time, get_string('strftimedateshort', 'langconfig'));
    }

    /**
     * Helpful marks of posts.
     *
     * @param int[] $postids Post ids.
     * @return array postid => ['count' => int, 'mine' => bool]
     */
    public static function get_likes(array $postids): array {
        global $DB, $USER;
        $postids = array_values(array_filter(array_unique($postids)));
        if (!$postids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($postids, SQL_PARAMS_NAMED);
        $result = [];
        foreach ($DB->get_records_select('format_fredki_like', "postid $insql", $params, '', 'id, postid, userid') as $like) {
            $result[$like->postid] = $result[$like->postid] ?? ['count' => 0, 'mine' => false];
            $result[$like->postid]['count']++;
            if ((int)$like->userid === (int)$USER->id) {
                $result[$like->postid]['mine'] = true;
            }
        }
        return $result;
    }

    /**
     * Posts that share a contribution of FredKI.
     *
     * @param int[] $postids Post ids.
     * @return array postid => true
     */
    public static function get_ai_posts(array $postids): array {
        global $DB;
        $postids = array_values(array_filter(array_unique($postids)));
        if (!$postids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($postids, SQL_PARAMS_NAMED);
        $ids = $DB->get_fieldset_select('format_fredki_aipost', 'postid', "postid $insql", $params);
        return array_fill_keys(array_map('intval', $ids), true);
    }

    /**
     * Turn a FredKI answer (plain text with light Markdown) into safe HTML.
     *
     * @param string $text Answer.
     * @return string
     */
    public static function fredki_html(string $text): string {
        $text = trim(str_replace("\r", '', $text));
        $html = '';
        $list = [];
        $flushlist = function () use (&$html, &$list) {
            if ($list) {
                $html .= '<ul><li>' . implode('</li><li>', $list) . '</li></ul>';
                $list = [];
            }
        };
        foreach (preg_split('/\n{1,}/', $text) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $inline = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', s($line));
            if (preg_match('/^(?:[-*•]|\d+[.)])\s+(.*)$/u', $line)) {
                $list[] = preg_replace('/^(?:[-*•]|\d+[.)])\s+/u', '', $inline);
                continue;
            }
            $flushlist();
            $html .= '<p>' . $inline . '</p>';
        }
        $flushlist();
        return $html;
    }

    /**
     * Start a new conversation in the forum of the group.
     *
     * @param \stdClass $course Course.
     * @param string $text Own words of the user.
     * @param string $question Question the user asked FredKI, if shared.
     * @param string $answer Answer of FredKI, if shared.
     * @param string $type Kind of the conversation (question, idea or experience), empty for none.
     * @return int Discussion id.
     */
    public static function create_discussion(
        \stdClass $course,
        string $text,
        string $question,
        string $answer,
        string $type = ''
    ): int {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/mod/forum/externallib.php');
        $community = self::require_forum($course);

        $text = trim($text);
        $question = trim($question);
        $answer = trim($answer);
        if ($text === '' && $answer === '') {
            throw new \moodle_exception('emptymessage', 'format_fredki');
        }
        $source = $text !== '' ? $text : $question;
        $subject = shorten_text(trim(preg_replace('/\s+/u', ' ', $source)), 80, true);

        $html = '';
        if ($question !== '') {
            $html .= '<p><em>' . s($question) . '</em></p>';
        }
        if ($text !== '') {
            $html .= '<p>' . nl2br(s($text)) . '</p>';
        }
        if ($answer !== '') {
            $html .= '<blockquote><p><strong>FredKI</strong></p>' . self::fredki_html($answer) . '</blockquote>';
        }
        $result = \mod_forum_external::add_discussion((int)$community['forum']->id, $subject, $html, 0, []);
        $discussionid = (int)$result['discussionid'];
        $postid = (int)$DB->get_field('forum_discussions', 'firstpost', ['id' => $discussionid]);
        if (in_array($type, self::TYPES, true)) {
            self::set_meta($postid, $type);
        }
        if ($answer !== '') {
            $DB->insert_record('format_fredki_aipost', (object)[
                'postid' => $postid,
                'userid' => $USER->id,
                'timecreated' => time(),
            ]);
        }
        return $discussionid;
    }

    /**
     * Reply to a conversation of the group, in own words or by bringing in FredKI.
     *
     * @param \stdClass $course Course.
     * @param int $discussionid Discussion id.
     * @param string $text Reply text.
     * @param bool $isfredki Whether the reply shares a FredKI contribution.
     * @param int $parentid Post answered, 0 for the conversation as a whole.
     * @return int Post id.
     */
    public static function add_reply(
        \stdClass $course,
        int $discussionid,
        string $text,
        bool $isfredki,
        int $parentid = 0
    ): int {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/mod/forum/externallib.php');
        $community = self::require_forum($course);
        $discussion = $DB->get_record(
            'forum_discussions',
            ['id' => $discussionid, 'forum' => $community['forum']->id],
            '*',
            MUST_EXIST
        );
        if (!self::load_discussions($course, $community, 1, $discussionid)) {
            throw new \moodle_exception('nopermissions', 'error', '', 'reply');
        }
        $text = trim($text);
        if ($text === '') {
            throw new \moodle_exception('emptymessage', 'format_fredki');
        }
        $html = $isfredki
            ? '<p><strong>FredKI</strong></p>' . self::fredki_html($text)
            : '<p>' . nl2br(s($text)) . '</p>';
        $subject = get_string('re', 'forum') . ' ' . $discussion->name;
        // An answer to a single post of the conversation, or else to the conversation as a whole.
        $parent = (int)$discussion->firstpost;
        if (
            $parentid && $DB->record_exists('forum_posts', ['id' => $parentid, 'discussion' => $discussion->id,
                'deleted' => 0, 'privatereplyto' => 0])
        ) {
            $parent = $parentid;
        }
        $result = \mod_forum_external::add_discussion_post(
            $parent,
            shorten_text($subject, 250),
            $html,
            [],
            FORMAT_HTML
        );
        $postid = (int)$result['postid'];
        if ($isfredki) {
            $DB->insert_record('format_fredki_aipost', (object)[
                'postid' => $postid,
                'userid' => $USER->id,
                'timecreated' => time(),
            ]);
        }
        return $postid;
    }

    /**
     * Seconds after writing in which a person may still correct an own post: the waiting time before the agents
     * answer (two minutes if they answer at once), never longer than the editing time of the site.
     *
     * @return int
     */
    public static function edit_window(): int {
        global $CFG;
        $window = council::answer_delay() ?: 120;
        return min($window, max(0, (int)$CFG->maxeditingtime));
    }

    /**
     * Until when the current user may correct a post in the learning space: an own post in words of their own (not
     * a shared FredKI answer), within the short window after writing it.
     *
     * @param \stdClass $post Post with userid and created.
     * @param array $aiposts Posts that share a FredKI answer, keyed by post id.
     * @return int Unix time, 0 if the post cannot be corrected.
     */
    public static function edit_until(\stdClass $post, array $aiposts = []): int {
        global $USER;
        if (
            !isloggedin() || isguestuser() || (int)$post->userid !== (int)$USER->id
                || botuser::is_bot((int)$post->userid) || isset($aiposts[(int)$post->id])
        ) {
            return 0;
        }
        $until = (int)$post->created + self::edit_window();
        return $until > time() ? $until : 0;
    }

    /**
     * Whether the current user may correct a post right now, see edit_until().
     *
     * @param \stdClass $post Post with userid and created.
     * @param array $aiposts Posts that share a FredKI answer, keyed by post id.
     * @return bool
     */
    public static function can_edit(\stdClass $post, array $aiposts = []): bool {
        return self::edit_until($post, $aiposts) > 0;
    }

    /**
     * Own words of a post, as the person typed them: without the FredKI question it may answer.
     *
     * @param \stdClass $post Post with message and messageformat.
     * @return string
     */
    public static function own_words(\stdClass $post): string {
        $message = preg_replace('~^\s*<p>\s*<em>.*?</em>\s*</p>~su', '', (string)$post->message, 1);
        if ((int)$post->messageformat === FORMAT_HTML || (int)$post->messageformat === FORMAT_MOODLE) {
            $message = preg_replace('~<br\s*/?>\s*~i', "\n", $message);
            $message = preg_replace('~</p>\s*<p>~i', "\n\n", $message);
            return trim(html_entity_decode(strip_tags($message), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        return trim(content_to_text($message, (int)$post->messageformat));
    }

    /**
     * Correct an own post. The FredKI question a post answers stays; the subject of a conversation follows the text.
     *
     * @param \stdClass $course Course.
     * @param int $postid Post id.
     * @param string $text New words.
     * @return int Discussion id.
     */
    public static function edit_post(\stdClass $course, int $postid, string $text): int {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/forum/externallib.php');
        $community = self::require_forum($course);
        $post = $DB->get_record('forum_posts', ['id' => $postid, 'deleted' => 0], '*', MUST_EXIST);
        $discussion = $DB->get_record(
            'forum_discussions',
            ['id' => $post->discussion, 'forum' => $community['forum']->id],
            '*',
            MUST_EXIST
        );
        if (!self::can_edit($post, self::get_ai_posts([$postid]))) {
            throw new \moodle_exception('cannotupdatepost', 'forum');
        }
        $text = trim($text);
        if ($text === '') {
            throw new \moodle_exception('emptymessage', 'format_fredki');
        }
        $keep = preg_match('~^\s*<p>\s*<em>.*?</em>\s*</p>~su', (string)$post->message, $m) ? $m[0] : '';
        $html = $keep . '<p>' . nl2br(s($text)) . '</p>';
        $subject = '';
        if (!$post->parent) {
            $subject = shorten_text(trim(preg_replace('/\s+/u', ' ', $text)), 80, true);
        }
        \mod_forum_external::update_discussion_post($postid, $subject, $html, FORMAT_HTML, []);
        return (int)$discussion->id;
    }

    /**
     * Take back an own post shortly after writing it. The opening post takes the whole conversation with it, as in
     * the forum. Moodle refuses when others have answered already.
     *
     * @param \stdClass $course Course.
     * @param int $postid Post id.
     * @return array ['discussionid' => int, 'discussiondeleted' => bool]
     */
    public static function delete_post(\stdClass $course, int $postid): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/forum/externallib.php');
        $community = self::require_forum($course);
        $post = $DB->get_record('forum_posts', ['id' => $postid, 'deleted' => 0], '*', MUST_EXIST);
        $discussion = $DB->get_record(
            'forum_discussions',
            ['id' => $post->discussion, 'forum' => $community['forum']->id],
            '*',
            MUST_EXIST
        );
        if (!self::can_edit($post, self::get_ai_posts([$postid]))) {
            throw new \moodle_exception('cannotdeletepost', 'forum');
        }
        $whole = empty($post->parent);
        $postids = $whole ? $DB->get_fieldset_select('forum_posts', 'id', 'discussion = ?', [$discussion->id]) : [$postid];
        \mod_forum_external::delete_post($postid);
        [$insql, $params] = $DB->get_in_or_equal($postids);
        foreach (['format_fredki_like', 'format_fredki_meta', 'format_fredki_aipost'] as $table) {
            $DB->delete_records_select($table, "postid $insql", $params);
        }
        return ['discussionid' => (int)$discussion->id, 'discussiondeleted' => $whole];
    }

    /**
     * Mark a post of the group as helpful or remove the mark.
     *
     * @param \stdClass $course Course.
     * @param int $postid Post id.
     * @return array ['liked' => bool, 'count' => int]
     */
    public static function toggle_like(\stdClass $course, int $postid): array {
        global $DB, $USER;
        $community = self::require_forum($course);
        $post = $DB->get_record('forum_posts', ['id' => $postid], 'id, discussion', MUST_EXIST);
        if (!self::load_discussions($course, $community, 1, (int)$post->discussion)) {
            throw new \moodle_exception('nopermissions', 'error', '', 'like');
        }
        $existing = $DB->get_record('format_fredki_like', ['postid' => $postid, 'userid' => $USER->id]);
        if ($existing) {
            $DB->delete_records('format_fredki_like', ['id' => $existing->id]);
        } else {
            $DB->insert_record('format_fredki_like', (object)[
                'postid' => $postid,
                'userid' => $USER->id,
                'timecreated' => time(),
            ]);
        }
        return [
            'liked' => !$existing,
            'count' => $DB->count_records('format_fredki_like', ['postid' => $postid]),
        ];
    }

    /**
     * Conversations of the group as plain text for FredKI, with people replaced by neutral labels.
     *
     * @param \stdClass $course Course.
     * @param int $discussionid One discussion, or 0 for the latest ones.
     * @param int $limit Number of discussions.
     * @return string
     */
    public static function text_for_fredki(\stdClass $course, int $discussionid = 0, int $limit = 10): string {
        $community = self::get_forum($course);
        if (!$community) {
            return '';
        }
        $discussions = self::load_discussions($course, $community, $limit, $discussionid);
        if (!$discussions) {
            return '';
        }
        $replies = self::load_replies(array_keys($discussions));
        $postids = [];
        foreach ($replies as $list) {
            foreach ($list as $reply) {
                $postids[] = (int)$reply->id;
            }
        }
        $aiposts = self::get_ai_posts($postids);
        $anonymise = (bool)get_config('format_fredki', 'anonymise');
        $labels = [];
        $label = function (\stdClass $user) use (&$labels, $anonymise): string {
            if (!$anonymise) {
                return (string)$user->firstname;
            }
            if (!isset($labels[$user->id])) {
                $n = count($labels);
                $labels[$user->id] = get_string(
                    'person',
                    'format_fredki',
                    chr(65 + ($n % 26)) . ($n >= 26 ? (int)floor($n / 26) : '')
                );
            }
            return $labels[$user->id];
        };
        $maxreplies = $discussionid > 0 ? 12 : 2;
        $lines = [];
        foreach (array_reverse($discussions, true) as $d) {
            $author = \user_picture::unalias($d, null, 'u_id', 'u_');
            $authorlabel = botuser::is_bot((int)$d->userid) ? 'FredKI' : $label($author);
            $subject = format_string($d->name);
            $body = self::excerpt($d->message, (int)$d->messageformat, $discussionid > 0 ? 600 : 220);
            $line = '- ' . $authorlabel . ': ' . $subject;
            if ($body !== '' && strpos($body, rtrim($subject, '.…')) !== 0) {
                $line .= ' – ' . $body;
            } else if ($body !== '') {
                $line = '- ' . $authorlabel . ': ' . $body;
            }
            $lines[] = $line;
            foreach (array_slice($replies[$d->id] ?? [], -$maxreplies) as $reply) {
                $replyauthor = \user_picture::unalias($reply, null, 'u_id', 'u_');
                $isai = isset($aiposts[(int)$reply->id]) || botuser::is_bot((int)$reply->userid);
                $who = $isai ? 'FredKI' : $label($replyauthor);
                $lines[] = '  - ' . $who . ': '
                    . shorten_text(self::plain($reply->message, (int)$reply->messageformat, $isai), 300, true);
            }
        }
        return implode("\n", $lines);
    }

    /**
     * Conversations of the group that relate to a question, found by shared words.
     *
     * @param \stdClass $course Course.
     * @param string $text Question.
     * @return array
     */
    public static function related(\stdClass $course, string $text): array {
        global $USER;
        $community = self::get_forum($course);
        if (!$community) {
            return [];
        }
        $words = self::keywords($text);
        if (!$words) {
            return [];
        }
        $scored = [];
        foreach (self::load_discussions($course, $community, 40) as $d) {
            if ((int)$d->userid === (int)$USER->id) {
                continue;
            }
            $haystack = self::keywords(format_string($d->name) . ' ' . self::plain($d->message, (int)$d->messageformat));
            $score = count(array_intersect_key($words, $haystack));
            if ($score > 0) {
                $author = \user_picture::unalias($d, null, 'u_id', 'u_');
                $scored[] = [
                    'score' => $score,
                    'discussionid' => (int)$d->id,
                    'subject' => format_string($d->name, true, ['context' => $community['context']]),
                    'firstname' => (string)$author->firstname,
                    'url' => (new \moodle_url('/mod/forum/discuss.php', ['d' => $d->id]))->out(false),
                ];
            }
        }
        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_map(function ($item) {
            unset($item['score']);
            return $item;
        }, array_slice($scored, 0, 2));
    }

    /**
     * Significant words of a text.
     *
     * @param string $text Text.
     * @return array word => true
     */
    protected static function keywords(string $text): array {
        $stop = array_flip(explode(',', get_string('stopwords', 'format_fredki')));
        $result = [];
        foreach (preg_split('/[^\p{L}\p{N}]+/u', \core_text::strtolower($text)) as $word) {
            if (\core_text::strlen($word) >= 4 && !isset($stop[$word])) {
                $result[$word] = true;
            }
        }
        return $result;
    }

    /**
     * Kind of contributions and whether FredKI answered them.
     *
     * @param int[] $postids Post ids.
     * @return array postid => record with type and fredkianswered
     */
    public static function get_meta(array $postids): array {
        global $DB;
        $postids = array_values(array_filter(array_unique(array_map('intval', $postids))));
        if (!$postids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($postids, SQL_PARAMS_NAMED);
        $result = [];
        foreach ($DB->get_records_select('format_fredki_meta', "postid $insql", $params) as $record) {
            $result[(int)$record->postid] = $record;
        }
        return $result;
    }

    /**
     * Store the kind of a contribution or that FredKI answered it.
     *
     * @param int $postid Post id.
     * @param string|null $type Kind, null to keep.
     * @param bool|null $answered Whether FredKI answered, null to keep.
     */
    public static function set_meta(int $postid, ?string $type = null, ?bool $answered = null): void {
        global $DB;
        $record = $DB->get_record('format_fredki_meta', ['postid' => $postid]);
        if (!$record) {
            $record = (object)['postid' => $postid, 'type' => '', 'fredkianswered' => 0, 'timecreated' => time()];
            $record->id = $DB->insert_record('format_fredki_meta', $record);
        }
        if ($type !== null) {
            $record->type = $type;
        }
        if ($answered !== null) {
            $record->fredkianswered = $answered ? 1 : 0;
        }
        $DB->update_record('format_fredki_meta', $record);
    }

    /**
     * Whether a post asks FredKI directly.
     *
     * @param string $message Message.
     * @return bool
     */
    public static function mentions_fredki(string $message): bool {
        return (bool)preg_match('/@fredki\b/iu', strip_tags($message));
    }

    /**
     * FredKI starts a conversation: a question or an idea for the group.
     *
     * @param \stdClass $course Course.
     * @param string $text Text by FredKI.
     * @param string $type Kind of contribution.
     * @return int Discussion id.
     */
    public static function post_fredki_discussion(\stdClass $course, string $text, string $type): int {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/forum/lib.php');
        $community = self::require_forum_record($course);
        $forum = $community['forum'];
        $text = trim($text);
        if ($text === '') {
            throw new \moodle_exception('emptymessage', 'format_fredki');
        }
        $discussion = (object)[
            'course' => $course->id,
            'forum' => $forum->id,
            'name' => shorten_text(trim(preg_replace('/\s+/u', ' ', strip_tags($text))), 80, true),
            'message' => self::fredki_html($text),
            'messageformat' => FORMAT_HTML,
            'messagetrust' => 0,
            'mailnow' => 0,
            'groupid' => -1,
            'timestart' => 0,
            'timeend' => 0,
            'timelocked' => 0,
            'pinned' => FORUM_DISCUSSION_UNPINNED,
            'itemid' => 0,
        ];
        $discussionid = (int)forum_add_discussion($discussion, null, null, botuser::get_id());
        $event = \mod_forum\event\discussion_created::create([
            'context' => $community['context'],
            'objectid' => $discussionid,
            'other' => ['forumid' => $forum->id],
        ]);
        $event->trigger();
        $postid = (int)$DB->get_field('forum_discussions', 'firstpost', ['id' => $discussionid]);
        self::set_meta($postid, in_array($type, self::TYPES, true) ? $type : 'question');
        return $discussionid;
    }

    /**
     * FredKI replies in a conversation of the group.
     *
     * @param \stdClass $course Course.
     * @param int $discussionid Discussion id.
     * @param string $text Text by FredKI.
     * @param int $parentid Post FredKI replies to, 0 for the first post.
     * @return int Post id.
     */
    public static function post_fredki_reply(\stdClass $course, int $discussionid, string $text, int $parentid = 0): int {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/forum/lib.php');
        $community = self::require_forum_record($course);
        $forum = $community['forum'];
        $discussion = $DB->get_record('forum_discussions', ['id' => $discussionid, 'forum' => $forum->id], '*', MUST_EXIST);
        $text = trim($text);
        if ($text === '') {
            throw new \moodle_exception('emptymessage', 'format_fredki');
        }
        if ($parentid <= 0 || !$DB->record_exists('forum_posts', ['id' => $parentid, 'discussion' => $discussionid])) {
            $parentid = (int)$discussion->firstpost;
        }
        $now = time();
        $botid = botuser::get_id();
        $post = (object)[
            'discussion' => $discussionid,
            'parent' => $parentid,
            'userid' => $botid,
            'created' => $now,
            'modified' => $now,
            'mailed' => FORUM_MAILED_PENDING,
            'subject' => shorten_text(get_string('re', 'forum') . ' ' . $discussion->name, 250),
            'message' => self::fredki_html($text),
            'messageformat' => FORMAT_HTML,
            'messagetrust' => 0,
            'attachment' => '',
            'totalscore' => 0,
            'mailnow' => 0,
            'privatereplyto' => 0,
            'deleted' => 0,
        ];
        \mod_forum\local\entities\post::add_message_counts($post);
        $post->id = $DB->insert_record('forum_posts', $post);
        $DB->set_field('forum_discussions', 'timemodified', $now, ['id' => $discussionid]);
        $DB->set_field('forum_discussions', 'usermodified', $botid, ['id' => $discussionid]);
        $event = \mod_forum\event\post_created::create([
            'context' => $community['context'],
            'objectid' => $post->id,
            'other' => ['discussionid' => $discussionid, 'forumid' => $forum->id, 'forumtype' => $forum->type],
        ]);
        $event->trigger();
        self::set_meta($parentid, null, true);
        return (int)$post->id;
    }

    /**
     * Forum of the group without checks for the current user (FredKI also posts from scheduled tasks).
     *
     * @param \stdClass $course Course.
     * @return array ['forum' => stdClass, 'cm' => \cm_info, 'context' => \context_module]
     */
    public static function require_forum_record(\stdClass $course): array {
        global $CFG, $DB;
        if (isset(self::$override[(int)$course->id])) {
            return self::$override[(int)$course->id];
        }
        require_once($CFG->dirroot . '/course/format/lib.php');
        $options = course_get_format($course)->get_format_options();
        $modinfo = get_fast_modinfo($course);
        $forumid = (int)($options['communityforum'] ?? 0);
        $instances = $modinfo->get_instances_of('forum');
        $forum = $forumid > 0 ? $DB->get_record('forum', ['id' => $forumid, 'course' => $course->id]) : null;
        if (!$forum) {
            [$insql, $params] = $DB->get_in_or_equal(self::FORUM_TYPES, SQL_PARAMS_NAMED);
            $params['course'] = $course->id;
            $candidates = $DB->get_records_select('forum', "course = :course AND type $insql", $params);
            foreach ($instances as $instanceid => $cm) {
                if (isset($candidates[$instanceid]) && $cm->visible) {
                    $forum = $candidates[$instanceid];
                    break;
                }
            }
        }
        if (!$forum || empty($instances[$forum->id])) {
            throw new \moodle_exception('nocommunity', 'format_fredki');
        }
        $cm = $instances[$forum->id];
        return ['forum' => $forum, 'cm' => $cm, 'context' => \context_module::instance($cm->id)];
    }

    /**
     * FredKI's question of today, if FredKI asked one.
     *
     * @param array $community Result of get_forum().
     * @return \stdClass|null Discussion record.
     */
    public static function get_today_question(array $community): ?\stdClass {
        global $DB;
        $botid = (int)get_config('format_fredki', 'botuserid');
        if ($botid <= 0) {
            return null;
        }
        $records = $DB->get_records_select(
            'forum_discussions',
            'forum = :forum AND userid = :bot AND timemodified >= :since',
            ['forum' => $community['forum']->id, 'bot' => $botid, 'since' => usergetmidnight(time())],
            'id DESC',
            '*',
            0,
            1
        );
        return $records ? reset($records) : null;
    }

    /**
     * Questions of learners that nobody answered for some hours.
     *
     * @param array $community Result of require_forum_record().
     * @param int $hours Hours without an answer.
     * @return \stdClass[] First posts with their discussion id.
     */
    public static function find_open_questions(array $community, int $hours): array {
        global $DB;
        $botid = botuser::get_id();
        $sql = "SELECT p.id, p.discussion, p.message, p.messageformat, p.subject, m.type
                  FROM {forum_discussions} d
                  JOIN {forum_posts} p ON p.id = d.firstpost
             LEFT JOIN {format_fredki_meta} m ON m.postid = p.id
                 WHERE d.forum = :forum AND p.userid <> :bot AND p.created < :before AND p.created > :after
                       AND COALESCE(m.fredkianswered, 0) = 0
                       AND NOT EXISTS (SELECT 1 FROM {forum_posts} r WHERE r.discussion = d.id AND r.parent <> 0)
              ORDER BY p.created ASC";
        $records = $DB->get_records_sql($sql, [
            'forum' => $community['forum']->id,
            'bot' => $botid,
            'before' => time() - $hours * HOURSECS,
            'after' => time() - 14 * DAYSECS,
        ], 0, 5);
        return array_filter($records, function ($record) {
            return $record->type === 'question'
                || ($record->type === null && strpos(self::plain($record->message, (int)$record->messageformat), '?') !== false);
        });
    }
}
