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
 * The Moodle account under which FredKI takes part in conversations of the group.
 *
 * The account cannot log in (authentication "nologin"). It exists so that FredKI's questions,
 * ideas and answers are real forum posts with a clear author.
 *
 * @package    format_fredki
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class botuser {
    /** @var string User name of the account. */
    const USERNAME = 'fredki_lernbegleiter';

    /**
     * Id of the FredKI account, created on first use.
     *
     * @return int
     */
    public static function get_id(): int {
        global $DB, $CFG;
        $id = (int)get_config('format_fredki', 'botuserid');
        if ($id > 0 && $DB->record_exists('user', ['id' => $id, 'deleted' => 0])) {
            return $id;
        }
        $existing = $DB->get_record('user', ['username' => self::USERNAME, 'mnethostid' => $CFG->mnet_localhost_id,
            'deleted' => 0]);
        if ($existing) {
            set_config('botuserid', $existing->id, 'format_fredki');
            return (int)$existing->id;
        }
        require_once($CFG->dirroot . '/user/lib.php');
        $user = (object)[
            'username' => self::USERNAME,
            'auth' => 'nologin',
            'firstname' => 'FredKI',
            'lastname' => get_string('botlastname', 'format_fredki'),
            'email' => 'noreply-fredki@' . (parse_url($CFG->wwwroot, PHP_URL_HOST) ?: 'localhost'),
            'emailstop' => 1,
            'confirmed' => 1,
            'mnethostid' => $CFG->mnet_localhost_id,
            'lang' => $CFG->lang,
        ];
        // Moodle 5.3 replaces user_create_user() by \core\user::create_user().
        $id = method_exists(\core\user::class, 'create_user')
            ? \core\user::create_user((object)$user, false, false) : user_create_user($user, false, false);
        set_user_preference('message_provider_mod_forum_posts_enabled', 'none', $id);
        self::set_picture($id);
        set_config('botuserid', $id, 'format_fredki');
        return (int)$id;
    }

    /**
     * Whether a user id belongs to FredKI.
     *
     * @param int $userid User id.
     * @return bool
     */
    public static function is_bot(int $userid): bool {
        return $userid > 0 && $userid === (int)get_config('format_fredki', 'botuserid');
    }

    /**
     * Give the account the FredKI picture.
     *
     * @param int $userid User id.
     */
    protected static function set_picture(int $userid): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/gdlib.php');
        $source = $CFG->dirroot . '/course/format/fredki/pix/fredki_avatar.png';
        if (!is_readable($source)) {
            return;
        }
        $temp = make_request_directory() . '/fredki.png';
        copy($source, $temp);
        $fileid = process_new_icon(\context_user::instance($userid), 'user', 'icon', 0, $temp);
        if ($fileid) {
            $DB->set_field('user', 'picture', $fileid, ['id' => $userid]);
        }
    }
}
