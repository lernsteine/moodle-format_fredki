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
 * Calm photos that come with the format, so every course shows pictures from the start.
 *
 * All photos are Unsplash photos published under CC0 (public domain).
 *
 * @package    format_fredki
 * @copyright  2026 lernsteine.com
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class images {
    /** @var string[] Photos in the order they are given to learning steps. */
    const PHOTOS = ['forest', 'mountains', 'coast', 'jetty', 'peaks', 'branch', 'lake'];

    /** @var string Photo of the opening image. */
    const HERO = 'lake';

    /**
     * URL of a bundled photo.
     *
     * @param string $key Photo key.
     * @return string
     */
    public static function url(string $key): string {
        global $OUTPUT;
        if (!in_array($key, self::PHOTOS, true)) {
            $key = self::HERO;
        }
        return $OUTPUT->image_url('photo_' . $key, 'format_fredki')->out(false);
    }

    /** @var string[] Photos that topic fields get one after another (not the course image, not the question of the day). */
    const TOPIC_PHOTOS = ['coast', 'forest', 'mountains', 'jetty', 'branch'];

    /**
     * Calm, blurred picture for conversations without a topic field: it suggests no connection to anything.
     *
     * @return string URL.
     */
    public static function neutral(): string {
        global $OUTPUT;
        return $OUTPUT->image_url('photo_neutral', 'format_fredki')->out(false);
    }

    /**
     * Key of the photo of a topic field: the one chosen by a trainer, otherwise a fixed one by its id.
     *
     * @param \stdClass $topic Topic field with id and image.
     * @return string Photo key.
     */
    public static function topic_key(\stdClass $topic): string {
        $chosen = (string)($topic->image ?? '');
        if (in_array($chosen, self::PHOTOS, true)) {
            return $chosen;
        }
        return self::TOPIC_PHOTOS[abs((int)$topic->id) % count(self::TOPIC_PHOTOS)];
    }

    /**
     * Photo of a topic field.
     *
     * @param \stdClass $topic Topic field.
     * @return string URL.
     */
    public static function for_topic(\stdClass $topic): string {
        return self::url(self::topic_key($topic));
    }

    /** @var array Calm sections of a photo: zoom (negative = mirrored), focus x %, focus y %. A mirrored section keeps
     * its focus near the middle (x between 100/(1+z) and 100z/(1+z)), so the photo still fills the whole card. */
    const SECTIONS = [
        [1.0, 50, 50], [1.6, 15, 75], [-1.5, 50, 80], [1.8, 85, 30],
        [-1.3, 50, 40], [1.7, 70, 85], [-1.8, 40, 25], [1.4, 60, 15],
    ];

    /**
     * Style that shows another calm section of a shared photo, fixed per conversation, so cards of one topic field
     * look related but not the same.
     *
     * @param int $seed Conversation id.
     * @return string CSS for the img element.
     */
    public static function section_style(int $seed): string {
        [$zoom, $x, $y] = self::SECTIONS[abs($seed) % count(self::SECTIONS)];
        if ($zoom === 1.0) {
            return '';
        }
        $scale = $zoom < 0 ? sprintf('scale(%.2f, %.2f)', $zoom, -$zoom) : sprintf('scale(%.2f)', $zoom);
        return 'transform: ' . $scale . '; transform-origin: ' . $x . '% ' . $y . '%;';
    }

    /** @var string File area of pictures trainers upload for a topic field (item = topic id, 0 = without topic field). */
    const FILEAREA = 'topicimages';

    /** @var string File area of the own picture of a learning step (item = section id). */
    const SECTIONAREA = 'sectionimage';

    /**
     * Options of the file manager for the picture of a learning step.
     *
     * @return array
     */
    public static function section_upload_options(): array {
        return ['subdirs' => 0, 'maxfiles' => 1, 'maxbytes' => 5 * 1024 * 1024, 'accepted_types' => ['web_image']];
    }

    /**
     * Own picture of a learning step.
     *
     * @param int $courseid Course.
     * @param int $sectionid Section id.
     * @return string URL, '' when there is none.
     */
    public static function section_upload(int $courseid, int $sectionid): string {
        static $cache = [];
        if (!isset($cache[$courseid])) {
            $context = \context_course::instance($courseid);
            $cache[$courseid] = [];
            foreach (
                get_file_storage()->get_area_files(
                    $context->id,
                    'format_fredki',
                    self::SECTIONAREA,
                    false,
                    'itemid, filename',
                    false
                ) as $file
            ) {
                if (strpos((string)$file->get_mimetype(), 'image/') === 0) {
                    $cache[$courseid][(int)$file->get_itemid()] = \moodle_url::make_pluginfile_url(
                        $context->id,
                        'format_fredki',
                        self::SECTIONAREA,
                        $file->get_itemid(),
                        $file->get_filepath(),
                        $file->get_filename()
                    )->out(false);
                }
            }
        }
        return $cache[$courseid][$sectionid] ?? '';
    }

    /**
     * Picture of a learning step without looking into its description: own upload, chosen photo or the default.
     *
     * @param int $courseid Course.
     * @param int $sectionnum Section number.
     * @return string URL.
     */
    public static function step_picture(int $courseid, int $sectionnum): string {
        $section = get_fast_modinfo($courseid)->get_section_info($sectionnum);
        if ($section) {
            $own = self::section_upload($courseid, (int)$section->id);
            if ($own !== '') {
                return $own;
            }
            $choice = (string)(course_get_format($courseid)->get_format_options($section)['fredkiimage'] ?? '');
            if (in_array($choice, self::PHOTOS, true)) {
                return self::url($choice);
            }
        }
        return self::for_index($sectionnum - 1);
    }

    /** @var int Pictures per topic field. */
    const MAXUPLOADS = 12;

    /** @var array Uploaded pictures per course and topic field, read once per request. */
    protected static $uploads = [];

    /**
     * Pictures trainers uploaded for a topic field.
     *
     * @param int $courseid Course.
     * @param int $topicid Topic field, 0 for conversations without one.
     * @return string[] URLs, in a fixed order.
     */
    public static function uploads(int $courseid, int $topicid): array {
        if (!isset(self::$uploads[$courseid])) {
            $context = \context_course::instance($courseid);
            $list = [];
            $files = get_file_storage()->get_area_files(
                $context->id,
                'format_fredki',
                self::FILEAREA,
                false,
                'itemid, filename',
                false
            );
            foreach ($files as $file) {
                if (strpos((string)$file->get_mimetype(), 'image/') !== 0) {
                    continue;
                }
                $list[(int)$file->get_itemid()][] = \moodle_url::make_pluginfile_url(
                    $context->id,
                    'format_fredki',
                    self::FILEAREA,
                    $file->get_itemid(),
                    $file->get_filepath(),
                    $file->get_filename()
                )->out(false);
            }
            self::$uploads[$courseid] = $list;
        }
        return self::$uploads[$courseid][$topicid] ?? [];
    }

    /**
     * Options of the file manager for these pictures.
     *
     * @return array
     */
    public static function upload_options(): array {
        return ['subdirs' => 0, 'maxfiles' => self::MAXUPLOADS, 'maxbytes' => 5 * 1024 * 1024,
            'accepted_types' => ['web_image']];
    }

    /**
     * Photo for a position, cycling through the set.
     *
     * @param int $index Position, for example a section number.
     * @return string URL.
     */
    public static function for_index(int $index): string {
        return self::url(self::PHOTOS[abs($index) % count(self::PHOTOS)]);
    }

    /**
     * All bundled photos.
     *
     * @return string[] URLs.
     */
    public static function all(): array {
        return array_map([self::class, 'url'], self::PHOTOS);
    }

    /**
     * Choices for a select element.
     *
     * @return array key => label, '' => automatic
     */
    public static function choices(): array {
        $choices = ['' => get_string('photo_auto', 'format_fredki')];
        foreach (self::PHOTOS as $key) {
            $choices[$key] = get_string('photo_' . $key, 'format_fredki');
        }
        return $choices;
    }
}
