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
 * Upgrade steps of format_fredki.
 *
 * @package    format_fredki
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade format_fredki.
 *
 * @param int $oldversion Installed version.
 * @return bool
 */
function xmldb_format_fredki_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026110300) {
        $table = new xmldb_table('format_fredki_meta');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('postid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('type', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('fredkianswered', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('postid', XMLDB_KEY_FOREIGN_UNIQUE, ['postid'], 'forum_posts', ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026110300, 'format', 'fredki');
    }
    if ($oldversion < 2026110505) {
        // Option "Ruhige Kursseite" for every FredKI course, so all courses look alike (the option stays for exceptions).
        $courses = $DB->get_fieldset_select('course', 'id', 'format = ?', ['fredki']);
        foreach ($courses as $courseid) {
            $existing = $DB->get_record(
                'course_format_options',
                ['courseid' => $courseid, 'format' => 'fredki', 'sectionid' => 0, 'name' => 'immersive']
            );
            if ($existing) {
                if ((string)$existing->value !== '1') {
                    $DB->set_field('course_format_options', 'value', '1', ['id' => $existing->id]);
                }
            } else {
                $DB->insert_record('course_format_options', (object)['courseid' => $courseid, 'format' => 'fredki',
                    'sectionid' => 0, 'name' => 'immersive', 'value' => '1']);
            }
            rebuild_course_cache((int)$courseid, true);
        }
        upgrade_plugin_savepoint(true, 2026110505, 'format', 'fredki');
    }

    if ($oldversion < 2026110521) {
        // Glossary name "Definitions of the group" became "Definitions for the group":
        // rename glossaries still carrying the old name.
        $renames = ['Definitions of the group' => 'Definitions for the group',
            'Definitionen der Gruppe' => 'Definitionen für die Gruppe'];
        $options = $DB->get_records('course_format_options', ['format' => 'fredki', 'name' => 'definitionsglossary']);
        foreach ($options as $option) {
            $cm = $DB->get_record('course_modules', ['id' => (int)$option->value]);
            if (!$cm) {
                continue;
            }
            $glossary = $DB->get_record('glossary', ['id' => $cm->instance], 'id, name');
            if ($glossary && isset($renames[$glossary->name])) {
                $DB->set_field('glossary', 'name', $renames[$glossary->name], ['id' => $glossary->id]);
                rebuild_course_cache((int)$option->courseid, true);
            }
        }
        upgrade_plugin_savepoint(true, 2026110521, 'format', 'fredki');
    }

    if ($oldversion < 2026110533) {
        // Maturity of the conversations of the group.
        $table = new xmldb_table('format_fredki_quality');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('discussionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('score', XMLDB_TYPE_INTEGER, '3', null, null, null, null);
        $table->add_field('aspects', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('hint', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('people', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('posts', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('discussionid', XMLDB_KEY_FOREIGN_UNIQUE, ['discussionid'], 'forum_discussions', ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026110533, 'format', 'fredki');
    }

    if ($oldversion < 2026110537) {
        // Topic fields that bundle the conversations in the learning space.
        $table = new xmldb_table('format_fredki_topic');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        $table = new xmldb_table('format_fredki_topicmap');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('discussionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('topicid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('source', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'ai');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('discussionid', XMLDB_KEY_FOREIGN_UNIQUE, ['discussionid'], 'forum_discussions', ['id']);
        $table->add_key('topicid', XMLDB_KEY_FOREIGN, ['topicid'], 'format_fredki_topic', ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026110537, 'format', 'fredki');
    }

    if ($oldversion < 2026110547) {
        // Each topic field has a photo; conversations without an own picture show it.
        $table = new xmldb_table('format_fredki_topic');
        $field = new xmldb_field('image', XMLDB_TYPE_CHAR, '20', null, null, null, null, 'sortorder');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026110547, 'format', 'fredki');
    }

    if ($oldversion < 2026111200) {
        // The course agent of FredKI The Agent is no longer built by this format: remove waiting tasks
        // and the options that belonged to it.
        $DB->delete_records('task_adhoc', ['classname' => '\\format_fredki\\task\\setup_course_agent']);
        $DB->delete_records_select(
            'course_format_options',
            "format = 'fredki' AND name IN ('fredkisource', 'autoagent')"
        );
        upgrade_plugin_savepoint(true, 2026111200, 'format', 'fredki');
    }

    if ($oldversion < 2026111401) {
        // The "learn more" page of the trainer hint is now fredki.com.
        if (get_config('format_fredki', 'infourl') === 'https://lernsteine.com/') {
            set_config('infourl', 'https://fredki.com/', 'format_fredki');
        }
        upgrade_plugin_savepoint(true, 2026111401, 'format', 'fredki');
    }

    return true;
}
