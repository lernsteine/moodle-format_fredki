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
 * External functions of format_fredki.
 *
 * @package    format_fredki
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'format_fredki_create_post' => [
        'classname' => 'format_fredki\external\create_post',
        'description' => 'Share a thought, optionally with a FredKI answer, with the learning group.',
        'type' => 'write',
        'ajax' => true,
    ],
    'format_fredki_add_reply' => [
        'classname' => 'format_fredki\external\add_reply',
        'description' => 'Reply to a contribution of the learning group, optionally with a FredKI perspective.',
        'type' => 'write',
        'ajax' => true,
    ],
    'format_fredki_delete_post' => [
        'classname' => 'format_fredki\\external\\delete_post',
        'description' => 'Take back an own post of the learning group shortly after writing it.',
        'type' => 'write',
        'ajax' => true,
    ],
    'format_fredki_edit_post' => [
        'classname' => 'format_fredki\\external\\edit_post',
        'description' => 'Correct an own post of the learning group.',
        'type' => 'write',
        'ajax' => true,
    ],
    'format_fredki_toggle_like' => [
        'classname' => 'format_fredki\external\toggle_like',
        'description' => 'Mark a contribution of the learning group as helpful or remove the mark.',
        'type' => 'write',
        'ajax' => true,
    ],
    'format_fredki_fredki_reply' => [
        'classname' => 'format_fredki\external\fredki_reply',
        'description' => 'FredKI replies publicly in a conversation of the group.',
        'type' => 'write',
        'ajax' => true,
    ],
    'format_fredki_get_card' => [
        'classname' => 'format_fredki\external\get_card',
        'description' => 'One card of the learning wall.',
        'type' => 'read',
        'ajax' => true,
    ],
    'format_fredki_get_conversation' => [
        'classname' => 'format_fredki\external\get_conversation',
        'description' => 'A whole conversation of the learning group, for the course page.',
        'type' => 'read',
        'ajax' => true,
    ],
    'format_fredki_set_topic' => [
        'classname' => 'format_fredki\\external\\set_topic',
        'description' => 'Put a conversation of the group into a topic field.',
        'type' => 'write',
        'ajax' => true,
    ],
    'format_fredki_get_cards' => [
        'classname' => 'format_fredki\external\get_cards',
        'description' => 'More cards for the learning wall, or the results of a search.',
        'type' => 'read',
        'ajax' => true,
    ],
    'format_fredki_related' => [
        'classname' => 'format_fredki\external\related',
        'description' => 'Find contributions of the learning group that relate to a question.',
        'type' => 'read',
        'ajax' => true,
    ],
];
