# Changes

## 1.1.0 (2026-11-15)

- Moodle 5.0 to 5.3 supported (tested with Moodle 4.5 and 5.3).
- Moodle 5.2+: the highlight of a learning step uses the section actions of the course format API
  (`course_set_marker()` is deprecated); Moodle 5.3+: the FredKI account is created with `\core\user::create_user()`.

## 1.0.2 (2026-11-14)

- The "Learn more" link of the trainer hint leads to https://fredki.com/ (sites that kept the former default are
  updated).

## 1.0.1 (2026-11-14)

- Hint for trainers in the learning space when FredKI does not take part yet, with the next step: learn about
  FredKI Team Agents (link set in the plugin settings), add its block, or choose the learning companion or the
  expert round. Trainers can hide the hint.
- A course created without a logged-in user (e.g. by a script) gets its forum on the first visit of a trainer.

## 1.0.0 (2026-11-12) – first stable release

- Learning space with opening image, learning path and course conversations as image cards.
- Views of the course conversations: topics, cards and list; filters by kind and search.
- Topic fields: FredKI suggests a topic for each new conversation; trainers rename, merge, sort and add photos.
- Maturity rating of conversations with a demand level per course.
- Conversation of a learning step (forum inside a section) on the page of the step; course conversations below.
- Own images per learning step and per topic field; calm nature photos included.
- Definitions from questions are kept in the course glossary, filed under the learning step.
- The course forum "Course conversations" is created automatically when a course uses the format.
- FredKI answers through the learning companion or the expert round of FredKI Team Agents, or alternatively
  through a chatbot of FredKI Chat.
- Removed: the course agent of FredKI The Agent is no longer built by the format (waiting tasks and its course
  settings are removed on upgrade).
- Code style according to the Moodle coding style; minified JavaScript build; Privacy API covers the chosen view.
