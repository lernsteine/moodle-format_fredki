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
 * English strings of format_fredki.
 *
 * @package    format_fredki
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['activecount'] = '{$a} active';
$string['addforum'] = 'Add a forum';
$string['agentbusy'] = '{$a} is still writing. The answer will appear here shortly.';
$string['agenterror'] = '{$a} could not answer right now. Please try again later.';
$string['agentlimit'] = '{$a} has reached today\'s limit of answers.';
$string['agentnoanswer'] = 'No answer yet. The agent may be paused or have reached its daily limit.';
$string['agentpause'] = '{$a}: pause';
$string['agentpaused'] = '{$a} is paused at the moment.';
$string['agentresume'] = '{$a}: bring back';
$string['agentthinking'] = '{$a} is thinking …';
$string['aichip'] = 'AI';
$string['aimode'] = 'AI in the course';
$string['aimode_auto'] = 'Automatic';
$string['aimode_fredki'] = 'Learning companion FredKI';
$string['aimode_help'] = 'Learning companion: FredKI accompanies each person privately on the large image ("Ask FredKI …"), asks a question of the day and answers open questions. Add the learning companion in the block FredKI Team Agents with "+", like the agents; put its knowledge into its folder "Knowledge: Learning companion". Expert round: the agents of FredKI Team Agents answer in the conversations of the course. Automatic: expert round as soon as the course has an agent, otherwise learning companion.';
$string['aimode_team'] = 'Expert round (team agents)';
$string['allconversations'] = 'All conversations in the forum (moderation)';
$string['anonymise'] = 'Anonymise contributions for FredKI';
$string['anonymise_desc'] = 'When FredKI summarises or responds to conversations in the course, people are replaced by neutral labels (Person A, Person B).';
$string['answer'] = 'Reply';
$string['answerexpired'] = 'This answer is no longer available. Please ask FredKI again.';
$string['answers'] = 'answers';
$string['askagent'] = 'Ask AI';
$string['askagentplaceholder'] = 'Your question to {$a} …';
$string['askbutton'] = 'Ask';
$string['askcouncil'] = 'Ask {$a} …';
$string['askcouncilall'] = 'Your question to everyone in the course …';
$string['askcouncilchoose'] = 'Who should answer?';
$string['askcouncilnone'] = 'Choose at least one agent.';
$string['askfredki'] = 'Ask {$a} …';
$string['askround'] = 'Ask everyone in the course – FredKI answers';
$string['aspect_connection'] = 'Building on each other';
$string['aspect_depth'] = 'Depth';
$string['aspect_outcome'] = 'Outcome';
$string['aspect_perspectives'] = 'Perspectives';
$string['aspect_reasons'] = 'Reasons';
$string['backtospace'] = 'Learning space';
$string['bothsides'] = 'Both sides';
$string['botlastname'] = 'Learning companion';
$string['cardstyle'] = 'Look of the conversations';
$string['cardstyle_calm'] = 'Calm – photo only as a hint';
$string['cardstyle_clear'] = 'Clear – no photos';
$string['cardstyle_help'] = 'Four looks for the conversation cards. Light: the photo is clearly visible and fades to white, the text is black. Image-led: the photo fills the card, the text is white. Calm: white cards, the photo only as a soft hint. Clear: white cards without photos; a thin colour line shows the kind of post.';
$string['cardstyle_image'] = 'Image-led – full photo, white text';
$string['cardstyle_light'] = 'Light – photo visible, black text';
$string['closeconversation'] = 'Close conversation';
$string['autoforum_intro'] = 'Everyone in the course talks here. The posts appear in the learning space on the course page.';
$string['autoforum_name'] = 'Course conversations';
$string['communityforum'] = 'Forum for the course conversations';
$string['communityforum_auto'] = 'Automatic: first open forum of the course';
$string['communityforum_help'] = 'The conversations of the learning space are real posts in this forum. They also appear in the forum itself, in notifications and in reports.';
$string['convene'] = 'Ask the council';
$string['conveneempty'] = 'No agent can answer right now.';
$string['convenewait'] = 'The agents are answering. Their posts appear here in a moment.';
$string['council'] = 'The council';
$string['councilnote'] = 'The voices of the council are AI agents with simulated perspectives.';
$string['currentsection'] = 'Current step';
$string['daysago'] = '{$a} d ago';
$string['discard'] = 'Discard';
$string['done'] = 'Done';
$string['editcourse'] = 'Edit course';
$string['emptymessage'] = 'Please write something first.';
$string['filter'] = 'Show';
$string['filter_all'] = 'All';
$string['filter_experience'] = 'Experiences';
$string['filter_idea'] = 'Ideas';
$string['filter_question'] = 'Questions';
$string['fredki:usefredki'] = 'Talk with FredKI in the learning space';
$string['fredkianswer_hours'] = 'after {$a} hours without an answer';
$string['fredkianswer_never'] = 'Never';
$string['fredkianswerafter'] = 'FredKI answers open questions';
$string['fredkianswerafter_help'] = 'When nobody answered a question in the course for this time, FredKI answers in public and invites the others to add their view.';
$string['fallbackquestion1'] = 'What has stayed with you most from the last few days?';
$string['fallbackquestion2'] = 'Where did you notice something new for yourself recently?';
$string['fallbackquestion3'] = 'What would you like to try out in the coming days?';
$string['fallbackquestion4'] = 'Which question is still on your mind at the moment?';
$string['fallbackquestion5'] = 'What surprised you recently?';
$string['fallbackquestion6'] = 'What helps you most when you get stuck?';
$string['fredkiasks'] = 'FredKI asks';
$string['fredkibot'] = 'Alternative: agent of FredKI Chat';
$string['fredkibot_help'] = 'Only needed if there is no learning companion of FredKI Team Agents in the course: then this agent of FredKI Chat (local_fredkichat) accompanies the course. The FredKI folder of the course decides which agents can be chosen.';
$string['fredkibot_none'] = 'No FredKI';
$string['fredkierror'] = 'FredKI cannot answer right now. Please try again later.';
$string['fredkiimage'] = 'Bundled photo';
$string['fredkiimage_help'] = 'Used when no own picture is uploaded above and the section description contains no image.';
$string['fredkiposted'] = 'FredKI answered in the course';
$string['fredkipublic'] = 'FredKI answers in public';
$string['fredkiquestions'] = 'FredKI asks a question every day';
$string['fredkiquestions_help'] = 'FredKI starts one open conversation per day, based on the course and the current conversations.';
$string['fredkirole'] = 'Learning companion';
$string['fredkistarters'] = 'Starter questions for FredKI';
$string['fredkistarters_help'] = 'One question per line. They appear as buttons in the FredKI bar of this learning step.';
$string['helpful'] = 'Helpful';
$string['heroimage'] = 'Opening photo';
$string['heroimage_help'] = 'Used when the first section has no image of its own and no course image is set.';
$string['heroquestion'] = 'Question of the learning space';
$string['heroquestion_help'] = 'An open question shown large on the opening image, for example "Where does AI really help you?". Without it, the name of the first section or the course name is used.';
$string['hidefromothers'] = 'Hide';
$string['hoursago'] = '{$a} h ago';
$string['immersive'] = 'Calm course page';
$string['immersive_help'] = 'Hides the course header and secondary navigation for learners, so the learning space fills the page. Teachers see everything while editing.';
$string['impulse'] = 'Question of the day for everyone in the course';
$string['impulse_help'] = 'Shown as the first card of the conversations. Leave empty to let FredKI ask a new open question every day, based on the current conversations.';
$string['impulseanswering'] = 'Your answer to FredKI\'s question …';
$string['invalidmode'] = 'This kind of FredKI request is not available.';
$string['iwillanswer'] = 'I\'ll answer';
$string['justnow'] = 'just now';
$string['kind'] = 'Kind of contribution';
$string['legacysectionname'] = 'Step';
$string['less'] = 'less';
$string['loadmore'] = 'More conversations';
$string['materials'] = 'Materials';
$string['maturity'] = 'Maturity of the discussion';
$string['maturity_col_conversation'] = 'Conversation';
$string['maturity_col_people'] = 'People';
$string['maturity_col_posts'] = 'Contributions';
$string['maturity_course'] = 'Maturity in the course';
$string['maturity_deepening'] = 'Deepening';
$string['maturity_developed'] = 'Well developed';
$string['maturity_exchange'] = 'Exchanging views';
$string['maturity_help'] = 'An AI estimates how far the participants have developed a thought together: perspectives, reasons, building on each other, depth and outcome. It always rates the conversation as a whole, never single persons. Posts of the AI agents and of FredKI do not count; names are replaced by placeholders first.';
$string['maturity_next'] = 'What would develop the conversation:';
$string['maturity_none'] = 'No conversations rated yet.';
$string['maturity_note'] = 'The conversation is rated as a whole, never single persons. Posts of the AI do not count.';
$string['maturity_notyet'] = 'not rated yet';
$string['maturity_off'] = 'Maturity is switched off in this course or FredKI is not available.';
$string['maturity_overview'] = 'Overview of all conversations';
$string['maturity_queued'] = 'All conversations will be rated again in the next minutes.';
$string['maturity_rateall'] = 'Rate all conversations again';
$string['maturity_rated'] = '{$a} conversations rated';
$string['maturity_report'] = 'Maturity of the discussions';
$string['maturity_report_intro'] = 'How far the participants develop their thoughts – per conversation, with no values for single persons.';
$string['maturity_start'] = 'Getting started';
$string['maturity_title'] = 'How far the participants have developed this thought';
$string['maturity_tooearly'] = 'too few contributions';
$string['maxposts'] = 'Conversation cards in the learning space';
$string['maxposts_desc'] = 'Number of recent conversations shown on the learning space.';
$string['minutesago'] = '{$a} min ago';
$string['more'] = 'more';
$string['newsection'] = 'Learning step';
$string['nextstep'] = 'Next step';
$string['nextview'] = 'Another view';
$string['nextviewfrom'] = '{$a}: ask for a view';
$string['nocards'] = 'No contributions yet. Share the first thought.';
$string['nocommunity'] = 'This course has no forum for the conversations yet.';
$string['nocommunity_learner'] = 'The conversations are not open yet.';
$string['nofredki'] = 'FredKI is not available in this course.';
$string['nomention'] = 'This post does not address FredKI.';
$string['noposts_forfredki'] = '(No contributions yet.)';
$string['noresults'] = 'No conversation about this yet.';
$string['notyet'] = 'not here yet';
$string['online'] = 'online';
$string['onlinecount'] = '{$a} online';
$string['open'] = 'Open';
$string['openinforum'] = 'Edit in the forum';
$string['page-course-view-fredki'] = 'Any course main page in the FredKI format';
$string['page-course-view-fredki-x'] = 'Any course page in the FredKI format';
$string['path'] = 'Learning path';
$string['person'] = 'Person {$a}';
$string['photo_auto'] = 'Automatic';
$string['photo_branch'] = 'Branches';
$string['photo_coast'] = 'Coast';
$string['photo_forest'] = 'Misty forest';
$string['photo_jetty'] = 'Jetty';
$string['photo_lake'] = 'Lake at dusk';
$string['photo_mountains'] = 'Mountains';
$string['photo_peaks'] = 'Peaks';
$string['pluginname'] = 'FredKI learning space';
$string['previousstep'] = 'Previous step';
$string['privacy:metadata:aipost'] = 'Forum posts in which a user shared a contribution of FredKI with everyone in the course.';
$string['privacy:metadata:aipost:postid'] = 'The forum post.';
$string['privacy:metadata:aipost:timecreated'] = 'When the contribution was shared.';
$string['privacy:metadata:aipost:userid'] = 'The user who shared it.';
$string['privacy:metadata:fredki'] = 'Questions to FredKI are answered by the hosted FredKI service.';
$string['privacy:metadata:fredki:contributions'] = 'For summaries and new perspectives, recent contributions from the course conversations are sent, anonymised by default. No Moodle user identity is sent.';
$string['privacy:metadata:fredki:message'] = 'The question of the learner.';
$string['privacy:metadata:preference:view'] = 'The view of the course conversations the user chose last (by topic, cards or list).';
$string['privacy:metadata:like'] = 'Forum posts in the course that a user marked as helpful.';
$string['privacy:metadata:like:postid'] = 'The forum post.';
$string['privacy:metadata:like:timecreated'] = 'When the mark was set.';
$string['privacy:metadata:like:userid'] = 'The user who set the mark.';
$string['privacylink'] = 'Privacy & AI';
$string['privacynote'] = 'Privacy notice "Privacy & AI"';
$string['privacynote_contactfallback'] = 'the administrators of this Moodle site';
$string['privacynote_default'] = 'AI takes part in this course. AI agents such as the Expert or the Critic, and in some courses the learning companion FredKI, answer questions and join conversations. **They are not people.** Each of their answers carries the label "AI". AI can be wrong: check important statements and decide for yourself.

### What happens with your posts

* **When you ask or mention an agent**, Moodle sends your post and the conversation so far to FredKI. Names are replaced with "Person A", "Person B" beforehand. Your e-mail address and Moodle account are never transmitted.
* **When you ask FredKI privately**, your question and at most 20 previous messages of that conversation go to FredKI, also without names.
* **The moderator and supervisor agents** also see other conversations of this course forum for summaries, also without names.
* **Your forum posts** stay in Moodle as usual and are visible to everyone in the course.

### Where FredKI works

FredKI is operated by LERNSTEINE® on our behalf (data processing under Art. 28 GDPR). The AI models run on their own servers in Germany. Your posts go to no other AI provider and are not used to train AI. FredKI keeps conversations for at most 30 days for troubleshooting and then deletes them.

### Please note

Do not post sensitive information in the course, such as health details, addresses or bank details. This applies to posts for people and for agents alike. Agents only answer when someone asks or mentions them.

### Controller and your rights

The controller is the operator of this Moodle site: {$a->operator}. Processing serves to run this course (Art. 6(1)(b) GDPR) or our legitimate interest in a lively course exchange (Art. 6(1)(f) GDPR). You have the right of access, rectification, erasure, restriction, data portability and objection, and the right to lodge a complaint with a supervisory authority. Contact: {$a->contact}';
$string['privacynote_help'] = 'Text learners see via the link "Privacy & AI" (Markdown). Leave empty for the default text, which inserts the name of this Moodle site and its support address. The default text is not legal advice; align it with your own privacy policy.';
$string['privatehint'] = 'Only you see this conversation. Share good thoughts with everyone in the course in one click.';
$string['prompt_answer'] = 'Prompt: open question of the group';
$string['prompt_answer_default'] = 'Please answer this open question of our learning group in the course "{course}". It has been without an answer for some hours:
{posts}
Please keep it short (four sentences at most) and base it on the course material. You are welcome to invite the others to add their experience.';
$string['prompt_ask'] = 'Prompt: question of a learner';
$string['prompt_ask_default'] = '{message}

Please answer briefly (three sentences at most) and concretely, using the material of the course "{course}"{step}. Please end with one open question for me.';
$string['prompt_audit'] = 'You are an impartial auditor of a learning discussion in a company course. Judge the conversation as a whole, never single persons, and never name or compare persons. Only the contributions of people below count.\n\nRate five aspects from 0 (not at all) to 4 (strongly):\n- perspectives: how many different points of view appear\n- reasons: whether statements are backed by reasons, examples or experience\n- connection: whether people refer to each other and build on each other\n- depth: whether ideas are questioned, deepened or developed further\n- outcome: whether there is a shared conclusion, result or next step\n\nAnswer ONLY with this JSON, nothing else:\n{"perspectives":0,"reasons":0,"connection":0,"depth":0,"outcome":0,"hint":"..."}\nThe hint is one short, encouraging sentence in {language} for the whole group: what would develop the discussion further.\n\nTopic: {topic}\n\nContributions:\n{posts}';
$string['prompt_contrast'] = 'Prompt: two sides';
$string['prompt_contrast_default'] = 'Please show two different points of view from these recent contributions of our learning group in the course "{course}", in two short sentences and without names:
{posts}
Then please ask me where I stand.';
$string['prompt_desc'] = 'Leave empty to use the default. Placeholders: {course}, {step}, {posts}, {message}. Default:<br><pre>{$a}</pre>';
$string['prompt_impulse'] = 'Prompt: question of the day';
$string['prompt_impulse_default'] = 'Please ask our learning group in the course "{course}" one short, open question (twelve words at most) that everyone can answer from their own experience. Recent contributions for orientation:
{posts}
Reply with this one question only: no heading, no introduction, no explanation, no formatting.';
$string['prompt_mention'] = 'Prompt: FredKI is addressed directly';
$string['prompt_mention_default'] = '{message}

This question is addressed to you in a conversation of our learning group in the course "{course}":
{posts}
Please answer for the whole group: briefly (four sentences at most), friendly and factual, using the course material where it fits. You are welcome to end with a question that helps the group think further.';
$string['prompt_perspective'] = 'Prompt: new perspective on a conversation';
$string['prompt_perspective_default'] = 'Please add a complementary perspective (two sentences at most) to this conversation of our learning group in the course "{course}":
{posts}
Please end with one open question to the group and do not judge individual people.';
$string['prompt_language'] = 'Answer language: {$a}';
$string['prompt_step'] = ', learning step "{$a}"';
$string['prompt_style'] = 'Style: Begin with one sentence that answers directly. No introduction, no praise for the question, do not start with a name.';
$string['prompt_summary'] = 'Prompt: what the group thinks';
$string['prompt_summary_default'] = 'Please summarise which points of view exist in these recent contributions of our learning group in the course "{course}", in three short sentences at most and without names:
{posts}
Please end with a question that invites me to join the conversation.';
$string['relatedintro'] = 'Others in the course talk about this:';
$string['reply'] = 'Reply';
$string['replyagentplaceholder'] = 'Question for {$a} – or leave empty and send';
$string['replyall'] = 'Reply to the whole conversation';
$string['replyfredkiplaceholder'] = 'Question for FredKI – or leave empty and send';
$string['replyplaceholder'] = 'Write a reply …';
$string['replyto'] = 'Reply to {$a}';
$string['replytoagent'] = 'Reply to {$a}';
$string['round'] = 'Course conversations';
$string['searchconversations'] = 'Search conversations …';
$string['section0name'] = 'Start';
$string['sectionname'] = 'Learning step';
$string['send'] = 'Send';
$string['settings_privacy'] = 'Privacy';
$string['settings_privacy_desc'] = 'FredKI only receives what is needed to answer. Answers shared with everyone in the course are posted by the learner who shared them.';
$string['settings_prompts'] = 'How FredKI takes part';
$string['settings_prompts_desc'] = 'These texts are added to the message sent to the FredKI agent. The agent keeps its own instructions in FredKI.';
$string['share'] = 'Share';
$string['shared'] = 'Shared';
$string['sharedby'] = 'shared by {$a}';
$string['shareplaceholder'] = 'Share your ideas with everyone in the course …';
$string['shareplaceholder_fredki'] = 'Tip: write @FredKI to bring FredKI into the conversation.';
$string['sharewithround'] = 'Share with everyone';
$string['showfromothers'] = 'Show';
$string['showpresence'] = 'Show who is here';
$string['showpresence_help'] = 'Shows people who were active in the course during the last ten minutes. Only shown to users who may see the participants.';
$string['starter_askme'] = 'Ask me a question';
$string['starter_exercise'] = 'Give me a small exercise';
$string['starter_prior'] = 'What do I already know about this?';
$string['stepof'] = 'Step {$a->pos} of {$a->total}';
$string['stopwords'] = 'this,that,with,from,have,what,when,where,which,there,their,about,would,could,should,does,into,than,then,them,they,your,yours,will,just,also,very,more,some,much,many,like,how';
$string['task_mention'] = 'FredKI answers posts that mention @FredKI';
$string['task_openquestions'] = 'FredKI answers open questions in the course';
$string['task_rate'] = 'Rate the maturity of a conversation';
$string['team'] = 'Team';
$string['thinkfurther'] = 'Think further';
$string['thinkfurtherprompt'] = 'Can you take this further?';
$string['thinking'] = 'FredKI is thinking';
$string['toolong'] = 'The text is too long.';
$string['type_experience'] = 'Experience';
$string['type_idea'] = 'Idea';
$string['type_question'] = 'Question';
$string['viewshared'] = 'View';
$string['whoanswers'] = 'Who should answer?';
$string['whoishere'] = 'Who is here';
$string['withfredki'] = 'with FredKI';
$string['writemessage'] = 'Send a message';
$string['edit'] = 'Edit';
$string['editsave'] = 'Save';
$string['editcancel'] = 'Cancel';
$string['edittitle'] = 'Correct your post';
$string['pendingone'] = '{$a} answers in';
$string['pendingmany'] = '{$a} answer in';
$string['pendingnow'] = 'Looks good – answer now';
$string['pendingedit'] = 'Correct first';
$string['delete'] = 'Delete';
$string['deleteconfirm'] = 'Really delete?';
$string['deletewhole'] = 'Really delete? The whole conversation disappears.';
$string['companionmessages'] = 'Prefer to keep writing at your own pace? In the messages';
$string['definitions'] = 'Course definitions';
$string['definitions_help'] = 'When someone asks FredKI for a definition ("What is …?", "Define …"), FredKI answers concisely and keeps the definition in the glossary "Course definitions". The learning space shows the terms as chips and explains them on hover wherever they appear.';
$string['definitions_name'] = 'Course definitions';
$string['definitions_intro'] = 'Terms participants asked FredKI about, with a concise definition. Definitions marked "AI definition" were written by FredKI; please check them against the course material.';
$string['definitions_ailabel'] = 'AI definition (FredKI)';
$string['definitions_saved'] = 'Saved as a definition for everyone in the course: {$a}';
$string['definitions_exists'] = '"{$a}" is already among the course definitions.';
$string['definitions_title'] = 'Course definitions';
$string['definitions_open'] = 'Show all definitions';
$string['definitions_count'] = '{$a} terms';
$string['definitions_countone'] = '1 term';
$string['definitions_edit'] = 'Edit in the glossary';
$string['definitions_letters'] = 'By first letter';
$string['definitions_none'] = 'No matching terms.';
$string['definitions_search'] = 'Search terms …';
$string['termai'] = 'AI';
$string['prompt_define'] = 'Prompt: definition for the group';
$string['prompt_define_default'] = '{message}

Please give a concise definition (at most two sentences) of the term asked for, as it fits the course "{course}". Reply exactly in this form and nothing else:
TERM: <the term>
DEFINITION: <the definition>';
$string['companionmessages_history'] = 'Your conversations with FredKI are also kept in your messages.';
$string['askprivate'] = 'FredKI · private';
$string['askprivateplaceholder'] = 'Your question to FredKI (only for you) …';
$string['prompt_topic'] = 'Sort a conversation of a learning group into a topic field. Topic fields bundle conversations about the same theme so the group keeps an overview.\n\nExisting topic fields:\n{topics}\n\n{rule}\nA topic field name has 1 to 3 words in {language}, no punctuation, no names of persons.\n\nAnswer ONLY with this JSON, nothing else:\n{"topic":"..."}\n\nTitle of the conversation: {title}\nOpening post: {text}';
$string['prompt_topic_existing'] = 'Choose exactly one of the existing topic fields, the one that fits best. Write its name exactly as above.';
$string['prompt_topic_new'] = 'Prefer an existing topic field if it fits even roughly. Only if none fits, name a new, general topic field (not the title of the conversation).';
$string['task_topic'] = 'Sort a conversation into a topic field';
$string['topic'] = 'Topic field';
$string['topic_add'] = 'Add topic field';
$string['topic_all'] = 'All topics';
$string['topic_assign'] = 'Assign';
$string['topic_change'] = 'Change topic field';
$string['topic_count'] = '{$a} conversations';
$string['topic_countone'] = '1 conversation';
$string['topic_create'] = 'New topic field';
$string['topic_delete_hint'] = 'The conversations stay, without a topic field.';
$string['topic_menu'] = 'Topic field of this conversation';
$string['topic_merge'] = 'Merge into';
$string['topic_new'] = 'Create';
$string['topic_none'] = 'No topic field';
$string['topic_rename'] = 'Rename';
$string['topic_showall'] = 'Show all';
$string['topic_showall_label'] = 'Show all conversations in {$a}';
$string['topic_sorting'] = 'FredKI is sorting it in …';
$string['topic_suggest'] = 'Let FredKI suggest';
$string['topic_without'] = 'Further conversations';
$string['topics'] = 'Topic fields';
$string['topics_col_conversations'] = 'Conversations';
$string['topics_col_image'] = 'Picture';
$string['topics_col_merge'] = 'Merge';
$string['topics_col_order'] = 'Order';
$string['topics_manage'] = 'Manage topic fields';
$string['topics_queued'] = 'FredKI sorts {$a} conversations in. This happens in the background and takes a few minutes.';
$string['topics_report'] = 'Topic fields';
$string['topics_report_intro'] = 'Topic fields bundle the conversations in the learning space. FredKI suggests one when someone shares a conversation; the author can change it. Here you rename, order, merge and create topic fields. The forum itself stays as it is. Each topic field has a picture; conversations without a picture of their own show it, so the same picture means the same theme.';
$string['topics_sortin'] = 'Let FredKI sort them in';
$string['view'] = 'View';
$string['view_cards'] = 'Cards';
$string['view_list'] = 'List';
$string['view_topics'] = 'Topics';
$string['topicimages_files'] = 'Pictures';
$string['topicimages_files_help'] = 'Calm pictures with room for white text work best, e.g. landscapes, water, sky. Up to 12 pictures, 5 MB each. Conversations take them one after another; a post with its own picture keeps that one.';
$string['topicimages_intro'] = 'Cards of these conversations show your pictures instead of the bundled photo, one after another, so the cards of one topic field differ but belong together. Without pictures here, the bundled photo of the topic field is used.';
$string['topicimages_link'] = 'Own pictures';
$string['topicimages_link_count'] = 'Own pictures ({$a})';
$string['topicimages_title'] = 'Pictures: {$a}';
$string['topicimages_without_hint'] = 'otherwise they show bundled photos.';
$string['sectionimage'] = 'Own picture of this step';
$string['sectionimage_help'] = 'Upload one picture for the tile and the page of this learning step. It replaces the bundled photo below. Calm pictures with room for white text work best, about 1600 pixels wide.';
$string['maturitydemand'] = 'Demand on maturity';
$string['maturitydemand_help'] = 'Sets from how many points (0–100) a conversation reaches the next level. Low: exchange from 20, deepening from 40, developed from 60 – good for introductory courses. Normal: 25 / 50 / 75. High: 35 / 60 / 85 – for experienced groups. The rating itself does not change, only the level; it applies at once to all conversations of the course.';
$string['maturitydemand_low'] = 'Low (20 / 40 / 60)';
$string['maturitydemand_normal'] = 'Normal (25 / 50 / 75)';
$string['maturitydemand_high'] = 'High (35 / 60 / 85)';
$string['step_allconversations'] = 'All course conversations →';
$string['stepforum'] = 'Conversation of this step';
$string['stepforum_help'] = 'Automatic: if this section contains a forum (other than the forum of the course conversations), it appears on the page of the learning step as its own conversation – large, with its own field to share. The course conversations are then shown small below. Off: no own conversation for this step.';
$string['stepforum_auto'] = 'Automatic (forum in this section)';
$string['stepforum_off'] = 'Off';
$string['stepforum_intro'] = 'Conversations only about this learning step.';
$string['stepforum_share'] = 'Share about this step';
$string['stepforum_placeholder'] = 'What do you think about this step? …';
$string['stepforum_empty'] = 'No posts about this step yet. Share the first thought.';
$string['stepforum_add'] = 'Add a conversation for this step';
$string['stepforum_add_hint'] = 'a forum in this section automatically becomes the conversation of the step.';
$string['agentsneedforum'] = 'The AI agents are ready but only appear once the course has a forum for its conversations: they answer in the course conversations.';
$string['aihint_addblock'] = 'Turn editing on, add the block "FredKI Team Agents" and choose the learning companion or the expert round there.';
$string['aihint_choose'] = 'Choose the learning companion or the expert round in the block "FredKI Team Agents". FredKI then appears here in the learning space.';
$string['aihint_editing'] = 'Turn editing on';
$string['aihint_hide'] = 'Do not show again';
$string['aihint_install'] = 'With FredKI Team Agents this learning space becomes a space for conversation with AI: a learning companion for every person or an expert round that joins the discussions in the course.';
$string['aihint_more'] = 'Learn more';
$string['aihint_title'] = 'Bring in FredKI:';
$string['infourl'] = '"Learn more" page';
$string['infourl_desc'] = 'The hint for trainers links to this page when FredKI Team Agents is not installed.';
$string['privacy:metadata:preference:aihint'] = 'Whether the person hid the hint how FredKI comes into the course.';
