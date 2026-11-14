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
 * Event observers of format_fredki.
 *
 * @package    format_fredki
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    ['eventname' => '\core\event\course_created', 'callback' => '\format_fredki\observer::course_changed'],
    ['eventname' => '\core\event\course_updated', 'callback' => '\format_fredki\observer::course_changed'],
    ['eventname' => '\mod_forum\event\discussion_created', 'callback' => '\format_fredki\observer::post_created'],
    ['eventname' => '\mod_forum\event\post_created', 'callback' => '\format_fredki\observer::post_created'],
    ['eventname' => '\mod_forum\event\post_updated', 'callback' => '\format_fredki\observer::post_changed'],
    ['eventname' => '\mod_forum\event\post_deleted', 'callback' => '\format_fredki\observer::post_changed'],
    ['eventname' => '\mod_forum\event\discussion_deleted', 'callback' => '\format_fredki\observer::discussion_deleted'],
];
