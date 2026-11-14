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

namespace format_fredki\external;

use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Shared return structures of the external functions.
 *
 * @package    format_fredki
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class structures {
    /**
     * A person as shown on an avatar.
     *
     * @param int $required VALUE_REQUIRED or VALUE_OPTIONAL
     * @return external_single_structure
     */
    public static function user(int $required = VALUE_REQUIRED): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'User id'),
            'firstname' => new external_value(PARAM_TEXT, 'First name'),
            'fullname' => new external_value(PARAM_TEXT, 'Full name'),
            'initials' => new external_value(PARAM_TEXT, 'Initials'),
            'hasavatar' => new external_value(PARAM_BOOL, 'Whether a picture was uploaded'),
            'avatarurl' => new external_value(PARAM_URL, 'Picture URL'),
            'colour' => new external_value(PARAM_ALPHANUM, 'Colour class of the initials'),
        ], 'A person', $required);
    }

    /**
     * A reply shown on a card.
     *
     * @return external_single_structure
     */
    public static function reply(): external_single_structure {
        return new external_single_structure([
            'postid' => new external_value(PARAM_INT, 'Post id', VALUE_DEFAULT, 0),
            'likes' => new external_value(PARAM_INT, 'Hearts of this post', VALUE_DEFAULT, 0),
            'liked' => new external_value(PARAM_BOOL, 'Whether the current user gave a heart', VALUE_DEFAULT, false),
            'isagent' => new external_value(PARAM_BOOL, 'Whether an AI agent of the course wrote it', VALUE_DEFAULT, false),
            'agent' => new external_single_structure([
                'name' => new external_value(PARAM_TEXT, 'Name of the agent'),
                'colour' => new external_value(PARAM_NOTAGS, 'Colour of the agent'),
                'image' => new external_value(PARAM_URL, 'Picture of the agent'),
            ], 'The agent', VALUE_OPTIONAL),
            'isfredki' => new external_value(PARAM_BOOL, 'Whether the reply is a contribution of FredKI'),
            'sharedby' => new external_value(
                PARAM_TEXT,
                'First name of the person who shared it, if not FredKI itself',
                VALUE_DEFAULT,
                ''
            ),
            'issharedby' => new external_value(PARAM_BOOL, 'Whether a person shared it', VALUE_DEFAULT, false),
            'text' => new external_value(PARAM_TEXT, 'Short text'),
            'fulltext' => new external_value(PARAM_TEXT, 'Whole text', VALUE_DEFAULT, ''),
            'truncated' => new external_value(PARAM_BOOL, 'Whether the short text is cut', VALUE_DEFAULT, false),
            'isreplyto' => new external_value(PARAM_BOOL, 'Whether it answers a single post', VALUE_DEFAULT, false),
            'canedit' => new external_value(PARAM_BOOL, 'Whether the user may correct this post', VALUE_DEFAULT, false),
            'edituntil' => new external_value(PARAM_INT, 'Until when the post can be corrected', VALUE_DEFAULT, 0),
            'replyto' => new external_value(PARAM_TEXT, 'Name of the author of the post answered', VALUE_DEFAULT, ''),
            'author' => self::user(),
        ]);
    }

    /**
     * A card of the learning wall.
     *
     * @return external_single_structure
     */
    public static function card(): external_single_structure {
        return new external_single_structure([
            'discussionid' => new external_value(PARAM_INT, 'Discussion id'),
            'postid' => new external_value(PARAM_INT, 'First post id'),
            'url' => new external_value(PARAM_URL, 'Discussion URL'),
            'subject' => new external_value(PARAM_TEXT, 'Subject'),
            'excerpt' => new external_value(PARAM_TEXT, 'Short text'),
            'image' => new external_value(PARAM_URL, 'Image URL'),
            'withfredki' => new external_value(PARAM_BOOL, 'Whether the post shares a FredKI answer'),
            'byfredki' => new external_value(PARAM_BOOL, 'Whether FredKI wrote the post'),
            'type' => new external_value(PARAM_ALPHA, 'Kind of contribution'),
            'typelabel' => new external_value(PARAM_TEXT, 'Label of the kind'),
            'hastype' => new external_value(PARAM_BOOL, 'Whether a kind is shown'),
            'author' => self::user(),
            'time' => new external_value(PARAM_TEXT, 'Relative time'),
            'likes' => new external_value(PARAM_INT, 'Helpful marks'),
            'liked' => new external_value(PARAM_BOOL, 'Whether the current user marked it'),
            'replycount' => new external_value(PARAM_INT, 'Number of replies'),
            'replies' => new external_multiple_structure(self::reply()),
            'hasreplies' => new external_value(PARAM_BOOL, 'Whether replies are shown'),
            'shape' => new external_value(PARAM_ALPHA, 'Card shape'),
            'canreply' => new external_value(PARAM_BOOL, 'Whether the user may reply'),
            'canconvene' => new external_value(PARAM_BOOL, 'Whether the user may ask the AI agents', VALUE_DEFAULT, false),
            'full' => new external_value(PARAM_BOOL, 'Whole conversation', VALUE_DEFAULT, false),
            'body' => new external_value(
                PARAM_TEXT,
                'Whole text of the opening post (whole conversation only)',
                VALUE_DEFAULT,
                ''
            ),
            'lastpersonpost' => new external_value(PARAM_INT, 'Last post of a person', VALUE_DEFAULT, 0),
            'nextagents' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Agent id'),
                'name' => new external_value(PARAM_TEXT, 'Name'),
                'image' => new external_value(PARAM_URL, 'Picture'),
                'colour' => new external_value(PARAM_NOTAGS, 'Colour'),
            ]), 'Agents that have not answered yet', VALUE_DEFAULT, []),
            'hasnextagents' => new external_value(PARAM_BOOL, 'Whether more agents can be asked', VALUE_DEFAULT, false),
            'canedit' => new external_value(
                PARAM_BOOL,
                'Whether the user may correct the opening post',
                VALUE_DEFAULT,
                false
            ),
            'edituntil' => new external_value(PARAM_INT, 'Until when the opening post can be corrected', VALUE_DEFAULT, 0),
            'canmoderate' => new external_value(
                PARAM_BOOL,
                'Whether the user may moderate in the forum',
                VALUE_DEFAULT,
                false
            ),
            'hasfredki' => new external_value(PARAM_BOOL, 'Whether FredKI takes part', VALUE_DEFAULT, false),
            'voices' => new external_multiple_structure(new external_single_structure([
                'name' => new external_value(PARAM_TEXT, 'Name'),
                'isagent' => new external_value(PARAM_BOOL, 'Whether an AI agent answered', VALUE_DEFAULT, false),
                'isfredki' => new external_value(PARAM_BOOL, 'Whether FredKI answered', VALUE_DEFAULT, false),
                'isperson' => new external_value(PARAM_BOOL, 'Whether a person answered', VALUE_DEFAULT, false),
                'image' => new external_value(PARAM_URL, 'Picture of the agent', VALUE_DEFAULT, ''),
                'colour' => new external_value(PARAM_NOTAGS, 'Colour of the agent', VALUE_DEFAULT, ''),
                'person' => self::user(VALUE_OPTIONAL),
            ]), 'Who has answered', VALUE_DEFAULT, []),
            'hasvoices' => new external_value(PARAM_BOOL, 'Whether someone has answered', VALUE_DEFAULT, false),
            'voicenames' => new external_value(PARAM_TEXT, 'Names of those who answered', VALUE_DEFAULT, ''),
            'morevoices' => new external_value(PARAM_INT, 'Further people not shown as faces', VALUE_DEFAULT, 0),
            'hasmorevoices' => new external_value(PARAM_BOOL, 'Whether more people answered', VALUE_DEFAULT, false),
            'imagestyle' => new external_value(PARAM_RAW, 'Section of a shared photo shown on the card', VALUE_DEFAULT, ''),
            'topicid' => new external_value(PARAM_INT, 'Topic field, 0 for none', VALUE_DEFAULT, 0),
            'topicname' => new external_value(PARAM_TEXT, 'Name of the topic field', VALUE_DEFAULT, ''),
            'hastopic' => new external_value(PARAM_BOOL, 'Whether the conversation has a topic field', VALUE_DEFAULT, false),
            'canchangetopic' => new external_value(PARAM_BOOL, 'Whether the user may change the topic field', VALUE_DEFAULT, false),
            'hasmaturity' => new external_value(PARAM_BOOL, 'Whether the conversation has a maturity rating', VALUE_DEFAULT, false),
            'maturityscore' => new external_value(PARAM_INT, 'Maturity 0-100', VALUE_DEFAULT, 0),
            'maturitylevel' => new external_value(PARAM_ALPHA, 'Level of maturity', VALUE_DEFAULT, ''),
            'maturitylabel' => new external_value(PARAM_TEXT, 'Name of the level', VALUE_DEFAULT, ''),
            'maturitysteps' => new external_multiple_structure(new external_single_structure([
                'on' => new external_value(PARAM_BOOL, 'Whether this step is reached'),
            ]), 'Four steps of the gauge', VALUE_DEFAULT, []),
            'maturityaspects' => new external_multiple_structure(new external_single_structure([
                'name' => new external_value(PARAM_TEXT, 'Aspect'),
                'value' => new external_value(PARAM_INT, '0-4'),
                'percent' => new external_value(PARAM_INT, '0-100'),
            ]), 'Five aspects of the rating', VALUE_DEFAULT, []),
            'maturityangle' => new external_value(PARAM_INT, 'Angle of the needle', VALUE_DEFAULT, -90),
            'maturityhint' => new external_value(PARAM_TEXT, 'What would develop the conversation', VALUE_DEFAULT, ''),
            'hasmaturityhint' => new external_value(PARAM_BOOL, 'Whether there is a hint', VALUE_DEFAULT, false),
        ]);
    }
}
