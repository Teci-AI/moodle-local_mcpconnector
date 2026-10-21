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

/**
 * Web service functions and the MCP Connector service.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_mcpconnector_get_info' => [
        'classname' => 'local_mcpconnector\external\get_info',
        'description' => 'Returns the installed local_mcpconnector version, for client compatibility checks.',
        'type' => 'read',
    ],
    'local_mcpconnector_create_page' => [
        'classname' => 'local_mcpconnector\external\create_page',
        'description' => 'Creates a Page activity in a course section.',
        'type' => 'write',
        'capabilities' => 'moodle/course:manageactivities, mod/page:addinstance',
    ],
    'local_mcpconnector_update_page' => [
        'classname' => 'local_mcpconnector\external\update_page',
        'description' => 'Replaces the content, and optionally the description, of a Page activity.',
        'type' => 'write',
        'capabilities' => 'moodle/course:manageactivities',
    ],
    'local_mcpconnector_create_book' => [
        'classname' => 'local_mcpconnector\external\create_book',
        'description' => 'Creates a Book activity with its first chapter.',
        'type' => 'write',
        'capabilities' => 'moodle/course:manageactivities, mod/book:addinstance, mod/book:edit',
    ],
    'local_mcpconnector_create_label' => [
        'classname' => 'local_mcpconnector\external\create_label',
        'description' => 'Creates a Text and media area (label) in a course section.',
        'type' => 'write',
        'capabilities' => 'moodle/course:manageactivities, mod/label:addinstance',
    ],
    'local_mcpconnector_create_url' => [
        'classname' => 'local_mcpconnector\external\create_url',
        'description' => 'Creates a URL resource in a course section.',
        'type' => 'write',
        'capabilities' => 'moodle/course:manageactivities, mod/url:addinstance',
    ],
    'local_mcpconnector_create_section' => [
        'classname' => 'local_mcpconnector\external\create_section',
        'description' => 'Creates a course section, at the end or at a given position.',
        'type' => 'write',
        'capabilities' => 'moodle/course:update, moodle/course:movesections',
    ],
    'local_mcpconnector_delete_section' => [
        'classname' => 'local_mcpconnector\external\delete_section',
        'description' => 'Deletes a course section and every activity in it.',
        'type' => 'write',
        'capabilities' => 'moodle/course:update, moodle/course:movesections, moodle/course:manageactivities',
    ],
    'local_mcpconnector_update_activity' => [
        'classname' => 'local_mcpconnector\external\update_activity',
        'description' => 'Renames and/or shows or hides an activity.',
        'type' => 'write',
        'capabilities' => 'moodle/course:manageactivities, moodle/course:activityvisibility',
    ],
    'local_mcpconnector_move_activity' => [
        'classname' => 'local_mcpconnector\external\move_activity',
        'description' => 'Moves an activity to the end of another section of its course.',
        'type' => 'write',
        'capabilities' => 'moodle/course:manageactivities',
    ],
    'local_mcpconnector_delete_activity' => [
        'classname' => 'local_mcpconnector\external\delete_activity',
        'description' => 'Deletes an activity and all its data.',
        'type' => 'write',
        'capabilities' => 'moodle/course:manageactivities',
    ],
    'local_mcpconnector_create_quiz' => [
        'classname' => 'local_mcpconnector\external\create_quiz',
        'description' => 'Creates an empty Quiz activity with the default quiz settings of the site.',
        'type' => 'write',
        'capabilities' => 'moodle/course:manageactivities, mod/quiz:addinstance',
    ],
    'local_mcpconnector_add_quiz_question' => [
        'classname' => 'local_mcpconnector\external\add_quiz_question',
        'description' => 'Adds a new multiple-choice question to a quiz.',
        'type' => 'write',
        'capabilities' => 'mod/quiz:manage, moodle/question:add',
    ],
    'local_mcpconnector_add_truefalse_question' => [
        'classname' => 'local_mcpconnector\external\add_truefalse_question',
        'description' => 'Adds a new true/false question to a quiz.',
        'type' => 'write',
        'capabilities' => 'mod/quiz:manage, moodle/question:add',
    ],
    'local_mcpconnector_add_shortanswer_question' => [
        'classname' => 'local_mcpconnector\external\add_shortanswer_question',
        'description' => 'Adds a new short-answer question to a quiz.',
        'type' => 'write',
        'capabilities' => 'mod/quiz:manage, moodle/question:add',
    ],
    'local_mcpconnector_add_essay_question' => [
        'classname' => 'local_mcpconnector\external\add_essay_question',
        'description' => 'Adds a new essay question to a quiz.',
        'type' => 'write',
        'capabilities' => 'mod/quiz:manage, moodle/question:add',
    ],
];

$services = [
    'MCP Connector Service' => [
        'functions' => [
            // Core read functions used by the moodle-mcp-connector content tool set.
            'core_webservice_get_site_info',
            'core_course_get_courses',
            'core_course_get_courses_by_field',
            'core_course_get_contents',
            'core_enrol_get_users_courses',
            'mod_quiz_get_quizzes_by_courses',
            'mod_lesson_get_lessons_by_courses',
            'mod_lesson_get_pages',
            // This plugin's functions.
            'local_mcpconnector_get_info',
            'local_mcpconnector_create_page',
            'local_mcpconnector_update_page',
            'local_mcpconnector_create_book',
            'local_mcpconnector_create_label',
            'local_mcpconnector_create_url',
            'local_mcpconnector_create_section',
            'local_mcpconnector_delete_section',
            'local_mcpconnector_update_activity',
            'local_mcpconnector_move_activity',
            'local_mcpconnector_delete_activity',
            'local_mcpconnector_create_quiz',
            'local_mcpconnector_add_quiz_question',
            'local_mcpconnector_add_truefalse_question',
            'local_mcpconnector_add_shortanswer_question',
            'local_mcpconnector_add_essay_question',
        ],
        'shortname' => 'mcpconnector',
        'enabled' => 0,
        'restrictedusers' => 1,
        'downloadfiles' => 1,
        'uploadfiles' => 0,
    ],
];
