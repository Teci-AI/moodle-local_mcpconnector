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
        ],
        'shortname' => 'mcpconnector',
        'enabled' => 0,
        'restrictedusers' => 1,
        'downloadfiles' => 1,
        'uploadfiles' => 0,
    ],
];
