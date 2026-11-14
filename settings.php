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
 * Site settings of format_fredki.
 *
 * @package    format_fredki
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configtext(
        'format_fredki/infourl',
        get_string('infourl', 'format_fredki'),
        get_string('infourl_desc', 'format_fredki'),
        'https://fredki.com/',
        PARAM_URL
    ));
    $settings->add(new admin_setting_heading(
        'format_fredki/privacyheading',
        get_string('settings_privacy', 'format_fredki'),
        get_string('settings_privacy_desc', 'format_fredki')
    ));
    $settings->add(new admin_setting_configcheckbox(
        'format_fredki/anonymise',
        get_string('anonymise', 'format_fredki'),
        get_string('anonymise_desc', 'format_fredki'),
        1
    ));
    $settings->add(new admin_setting_configtext(
        'format_fredki/maxposts',
        get_string('maxposts', 'format_fredki'),
        get_string('maxposts_desc', 'format_fredki'),
        12,
        PARAM_INT
    ));

    $settings->add(new admin_setting_heading(
        'format_fredki/promptheading',
        get_string('settings_prompts', 'format_fredki'),
        get_string('settings_prompts_desc', 'format_fredki')
    ));
    foreach (\format_fredki\local\fredki::MODES as $mode) {
        $settings->add(new admin_setting_configtextarea(
            'format_fredki/prompt_' . $mode,
            get_string('prompt_' . $mode, 'format_fredki'),
            get_string('prompt_desc', 'format_fredki', get_string('prompt_' . $mode . '_default', 'format_fredki')),
            '',
            PARAM_RAW,
            60,
            5
        ));
    }
}
