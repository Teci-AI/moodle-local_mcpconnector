<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_mcpconnector\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_mcpconnector\local\course_helper;

/**
 * Web service function local_mcpconnector_create_book.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_book extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'section' => new external_value(PARAM_INT, 'Section number within the course', VALUE_DEFAULT, 0),
            'name' => new external_value(PARAM_TEXT, 'Book name'),
            'intro' => new external_value(PARAM_RAW, 'Description (HTML)', VALUE_DEFAULT, ''),
            'chaptertitle' => new external_value(PARAM_TEXT, 'Title of the first chapter'),
            'chaptercontent' => new external_value(PARAM_RAW, 'Content of the first chapter (HTML)'),
            'visible' => new external_value(PARAM_INT, '1 to show the book, 0 to hide it', VALUE_DEFAULT, 1),
        ]);
    }

    /**
     * Creates a Book activity with its first chapter.
     *
     * @param int $courseid
     * @param int $section
     * @param string $name
     * @param string $intro
     * @param string $chaptertitle
     * @param string $chaptercontent
     * @param int $visible
     * @return array cmid, instanceid and chapterid
     */
    public static function execute(
        int $courseid,
        int $section,
        string $name,
        string $intro,
        string $chaptertitle,
        string $chaptercontent,
        int $visible = 1
    ): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/book/locallib.php');

        [
            'courseid' => $courseid,
            'section' => $section,
            'name' => $name,
            'intro' => $intro,
            'chaptertitle' => $chaptertitle,
            'chaptercontent' => $chaptercontent,
            'visible' => $visible,
        ] = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'section' => $section,
            'name' => $name,
            'intro' => $intro,
            'chaptertitle' => $chaptertitle,
            'chaptercontent' => $chaptercontent,
            'visible' => $visible,
        ]);

        $course = get_course($courseid);
        $coursecontext = \context_course::instance($course->id);
        self::validate_context($coursecontext);
        course_helper::require_can_add_module($course, 'book', $visible);
        // Adding a chapter needs mod/book:edit; checked here so no empty book is left behind.
        require_capability('mod/book:edit', $coursecontext);
        course_helper::require_not_empty('name', $name);
        course_helper::require_not_empty('chaptertitle', $chaptertitle);
        course_helper::validate_visible($visible);
        course_helper::get_section($course, $section);

        $transaction = $DB->start_delegated_transaction();

        $moduleinfo = course_helper::add_module($course, 'book', $section, $visible, [
            'name' => $name,
            'intro' => $intro,
            'introformat' => FORMAT_HTML,
            'numbering' => get_config('book', 'numbering'),
            'customtitles' => 0,
        ]);

        // Moodle has no API to add a chapter: do what mod/book/edit.php does.
        $book = $DB->get_record('book', ['id' => $moduleinfo->instance], '*', MUST_EXIST);
        $modcontext = \context_module::instance($moduleinfo->coursemodule);
        $now = time();
        $chapter = (object) [
            'bookid' => $book->id,
            'pagenum' => 1,
            'subchapter' => 0,
            'title' => $chaptertitle,
            'content' => $chaptercontent,
            'contentformat' => FORMAT_HTML,
            'hidden' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
            'importsrc' => '',
        ];
        $chapter->id = $DB->insert_record('book_chapters', $chapter);
        $DB->set_field('book', 'revision', $book->revision + 1, ['id' => $book->id]);
        $chapter = $DB->get_record('book_chapters', ['id' => $chapter->id], '*', MUST_EXIST);
        \mod_book\event\chapter_created::create_from_chapter($book, $modcontext, $chapter)->trigger();
        book_preload_chapters($book);

        $transaction->allow_commit();

        return [
            'cmid' => (int) $moduleinfo->coursemodule,
            'instanceid' => (int) $book->id,
            'chapterid' => (int) $chapter->id,
        ];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'Course module id of the new book'),
            'instanceid' => new external_value(PARAM_INT, 'Book instance id'),
            'chapterid' => new external_value(PARAM_INT, 'Id of the first chapter'),
        ]);
    }
}
