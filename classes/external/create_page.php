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
 * Web service function local_mcpconnector_create_page.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_page extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'section' => new external_value(PARAM_INT, 'Section number within the course', VALUE_DEFAULT, 0),
            'name' => new external_value(PARAM_TEXT, 'Page name'),
            'content' => new external_value(PARAM_RAW, 'Page content (HTML)'),
            'intro' => new external_value(PARAM_RAW, 'Description (HTML)', VALUE_DEFAULT, ''),
            'visible' => new external_value(PARAM_INT, '1 to show the page, 0 to hide it', VALUE_DEFAULT, 1),
        ]);
    }

    /**
     * Creates a Page activity.
     *
     * @param int $courseid
     * @param int $section
     * @param string $name
     * @param string $content
     * @param string $intro
     * @param int $visible
     * @return array cmid and instanceid of the new page
     */
    public static function execute(
        int $courseid,
        int $section,
        string $name,
        string $content,
        string $intro = '',
        int $visible = 1
    ): array {
        [
            'courseid' => $courseid,
            'section' => $section,
            'name' => $name,
            'content' => $content,
            'intro' => $intro,
            'visible' => $visible,
        ] = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'section' => $section,
            'name' => $name,
            'content' => $content,
            'intro' => $intro,
            'visible' => $visible,
        ]);

        $course = get_course($courseid);
        self::validate_context(\context_course::instance($course->id));
        course_helper::require_can_add_module($course, 'page', $visible);
        course_helper::require_not_empty('name', $name);
        course_helper::validate_visible($visible);
        course_helper::get_section($course, $section);

        $config = get_config('page');
        $moduleinfo = course_helper::add_module($course, 'page', $section, $visible, [
            'name' => $name,
            'intro' => $intro,
            'introformat' => FORMAT_HTML,
            'content' => $content,
            'contentformat' => FORMAT_HTML,
            'display' => $config->display,
            'popupwidth' => $config->popupwidth,
            'popupheight' => $config->popupheight,
            'printintro' => $config->printintro,
            'printlastmodified' => $config->printlastmodified,
        ]);

        return [
            'cmid' => (int) $moduleinfo->coursemodule,
            'instanceid' => (int) $moduleinfo->instance,
        ];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'Course module id of the new page'),
            'instanceid' => new external_value(PARAM_INT, 'Page instance id'),
        ]);
    }
}
