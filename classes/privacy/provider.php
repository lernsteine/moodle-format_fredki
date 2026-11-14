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

namespace format_fredki\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider of format_fredki.
 *
 * Helpful marks and the note that a forum post shares a FredKI contribution are stored per user.
 * They belong to the context of the forum that holds the post.
 *
 * @package    format_fredki
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\user_preference_provider {
    /** @var string[] Tables with user data. */
    const TABLES = ['format_fredki_like', 'format_fredki_aipost'];

    /**
     * Describe stored and transmitted data.
     *
     * @param collection $collection Collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('format_fredki_like', [
            'postid' => 'privacy:metadata:like:postid',
            'userid' => 'privacy:metadata:like:userid',
            'timecreated' => 'privacy:metadata:like:timecreated',
        ], 'privacy:metadata:like');
        $collection->add_database_table('format_fredki_aipost', [
            'postid' => 'privacy:metadata:aipost:postid',
            'userid' => 'privacy:metadata:aipost:userid',
            'timecreated' => 'privacy:metadata:aipost:timecreated',
        ], 'privacy:metadata:aipost');
        $collection->add_external_location_link('fredki', [
            'message' => 'privacy:metadata:fredki:message',
            'contributions' => 'privacy:metadata:fredki:contributions',
        ], 'privacy:metadata:fredki');
        $collection->add_user_preference('format_fredki_view', 'privacy:metadata:preference:view');
        $collection->add_user_preference('format_fredki_aihint', 'privacy:metadata:preference:aihint');
        return $collection;
    }

    /**
     * Export the view of the course conversations that the user chose.
     *
     * @param int $userid User.
     */
    public static function export_user_preferences(int $userid) {
        if (get_user_preferences('format_fredki_aihint', null, $userid)) {
            writer::export_user_preference(
                'format_fredki',
                'format_fredki_aihint',
                1,
                get_string('privacy:metadata:preference:aihint', 'format_fredki')
            );
        }
        $view = get_user_preferences('format_fredki_view', null, $userid);
        if ($view !== null && $view !== '') {
            writer::export_user_preference(
                'format_fredki',
                'format_fredki_view',
                $view,
                get_string('privacy:metadata:preference:view', 'format_fredki')
            );
        }
    }

    /**
     * SQL joining a table of this plugin to the context of the forum.
     *
     * @param string $table Table.
     * @return string
     */
    protected static function join_sql(string $table): string {
        return "FROM {{$table}} t
                JOIN {forum_posts} p ON p.id = t.postid
                JOIN {forum_discussions} d ON d.id = p.discussion
                JOIN {modules} m ON m.name = 'forum'
                JOIN {course_modules} cm ON cm.instance = d.forum AND cm.module = m.id
                JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :modlevel";
    }

    /**
     * Contexts with data of a user.
     *
     * @param int $userid User id.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        foreach (self::TABLES as $table) {
            $contextlist->add_from_sql(
                "SELECT ctx.id " . self::join_sql($table) . " WHERE t.userid = :userid",
                ['modlevel' => CONTEXT_MODULE, 'userid' => $userid]
            );
        }
        return $contextlist;
    }

    /**
     * Users with data in a context.
     *
     * @param userlist $userlist User list.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        foreach (self::TABLES as $table) {
            $userlist->add_from_sql(
                'userid',
                "SELECT t.userid " . self::join_sql($table) . " WHERE ctx.id = :contextid",
                ['modlevel' => CONTEXT_MODULE, 'contextid' => $context->id]
            );
        }
    }

    /**
     * Export data of a user.
     *
     * @param approved_contextlist $contextlist Contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $data = [];
            foreach (self::TABLES as $table) {
                $records = $DB->get_records_sql(
                    "SELECT t.id, t.postid, t.timecreated " . self::join_sql($table) .
                    " WHERE ctx.id = :contextid AND t.userid = :userid",
                    ['modlevel' => CONTEXT_MODULE, 'contextid' => $context->id, 'userid' => $userid]
                );
                foreach ($records as $record) {
                    $data[$table][] = [
                        'postid' => $record->postid,
                        'timecreated' => transform::datetime($record->timecreated),
                    ];
                }
            }
            if ($data) {
                writer::with_context($context)->export_data([get_string('pluginname', 'format_fredki')], (object)$data);
            }
        }
    }

    /**
     * Ids of the records of a table in a context, optionally for some users.
     *
     * @param string $table Table.
     * @param \context_module $context Context.
     * @param int[] $userids Users, empty for all.
     * @return int[]
     */
    protected static function record_ids(string $table, \context_module $context, array $userids = []): array {
        global $DB;
        $params = ['modlevel' => CONTEXT_MODULE, 'contextid' => $context->id];
        $where = 'ctx.id = :contextid';
        if ($userids) {
            [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
            $where .= " AND t.userid $insql";
            $params += $inparams;
        }
        return $DB->get_fieldset_sql("SELECT t.id " . self::join_sql($table) . " WHERE $where", $params);
    }

    /**
     * Delete records by id.
     *
     * @param string $table Table.
     * @param int[] $ids Ids.
     */
    protected static function delete_ids(string $table, array $ids): void {
        global $DB;
        if ($ids) {
            $DB->delete_records_list($table, 'id', $ids);
        }
    }

    /**
     * Delete all data in a context.
     *
     * @param \context $context Context.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        if (!$context instanceof \context_module) {
            return;
        }
        foreach (self::TABLES as $table) {
            self::delete_ids($table, self::record_ids($table, $context));
        }
    }

    /**
     * Delete data of a user.
     *
     * @param approved_contextlist $contextlist Contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $userid = (int)$contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            foreach (self::TABLES as $table) {
                self::delete_ids($table, self::record_ids($table, $context, [$userid]));
            }
        }
    }

    /**
     * Delete data of several users in a context.
     *
     * @param approved_userlist $userlist Users.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module || !$userlist->get_userids()) {
            return;
        }
        foreach (self::TABLES as $table) {
            self::delete_ids($table, self::record_ids($table, $context, $userlist->get_userids()));
        }
    }
}
