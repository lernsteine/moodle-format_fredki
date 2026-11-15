# FredKI learning space (format_fredki)

A Moodle course format that turns a course into a calm, image-led learning space. Learners, trainers and the
AI companion **FredKI** talk with each other in the course, in the tradition of constructivist learning:
the course is a place for conversation, not only a place for files and quizzes.

FredKI is a hosted service by lernsteine.com. The format works without it as a learning space for people only.

## What learners see

- **Opening image** with an open question of the course and the FredKI bar ("Ask FredKI …").
- **Learning path:** every section is a learning step with a large image. The current step is marked.
- **Course conversations:** the conversations of the course forum as image cards, sorted into topic fields.
  Learners switch between the views *topics*, *cards* and *list*, filter by kind (question, idea, experience)
  and search.
- **A learning step:** its image, starter questions for FredKI, the materials, the conversation of this step
  (optional own forum in the section) and, smaller, the course conversations.

## How FredKI takes part

FredKI writes real forum posts under its own Moodle account, which cannot log in.

| Place | What happens |
|---|---|
| FredKI bar | Private conversation with FredKI; an answer can be shared with the course |
| `@FredKI` in a post | FredKI answers in public in that conversation |
| Spark on a card | FredKI adds a perspective privately; it can be posted to the conversation |
| Question of the day | FredKI starts one open conversation per day (can be switched off) |
| Open questions | When nobody answered a question for some hours (set per course), FredKI answers and invites others |
| "What do the others think?", "Both sides" | FredKI summarises or contrasts the views in the course, anonymised |
| Definitions | A question for a definition gets a short answer that can be kept in the course glossary, filed under the learning step |

Text published under FredKI's name is always created on the server, never sent by a browser.

Where FredKI's answers come from:

- **Learning companion** of FredKI Team Agents (`block_fredkiteamagents`), with its own knowledge folder in
  the course, or
- the **expert round** of FredKI Team Agents: several AI agents with different roles answer on a card, or
- alternatively a chatbot of **FredKI Chat** (`local_fredkichat`), chosen in the course settings.

## For trainers

- The course forum "Course conversations" is created automatically when a course uses the format.
- **Topic fields:** FredKI suggests a topic field for each new conversation; trainers rename, merge, sort and
  give each topic field its own photos.
- **Maturity of conversations:** each conversation is rated from start to deepening; the demand level is set
  per course.
- **Images:** calm nature photos come with the format. Trainers upload their own image per learning step and per
  topic field, or choose a photo for the opening image.
- **Learning step conversations:** a forum inside a section becomes the conversation of that learning step;
  this can be switched off per section.

## Requirements

- Moodle 4.5 to 5.3
- Optional: `block_fredkiteamagents` (learning companion and expert round; needs `block_fredkitheagent` for
  the activation of the site)
- Optional: `local_fredkichat` (FredKI Chat) as an alternative source for FredKI's answers

## Privacy

- Questions are sent to FredKI without a Moodle identity.
- For summaries and perspectives, recent contributions in the course are sent, anonymised by default.
- Moodle stores helpful marks, which posts share a FredKI contribution and the chosen view of the
  conversations. The Privacy API is implemented.

## Images

The photos in `pix/photo_*.jpg` are Unsplash photos published before June 2017 under CC0 (public domain),
taken from the elementary OS wallpaper collection: "Sunset by the Pier", "Canazei Granite Ridges" and photos
by Carmine De Fazio, Rob Bye, Ryan Schroeder, Jonas Nilsson Lee and Pablo Garcia Saldaña.
`pix/photo_neutral.jpg` is a blurred version of the lake photo.

## License

2026 lernsteine.com – GNU GPL v3 or later.
