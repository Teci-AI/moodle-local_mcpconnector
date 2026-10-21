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
 * Web service function local_mcpconnector_create_label.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_label extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'section' => new external_value(PARAM_INT, 'Section number within the course', VALUE_DEFAULT, 0),
            'content' => new external_value(PARAM_RAW, 'Label text (HTML)'),
            'name' => new external_value(PARAM_TEXT, 'Label name; empty to build it from the content', VALUE_DEFAULT, ''),
            'visible' => new external_value(PARAM_INT, '1 to show the label, 0 to hide it', VALUE_DEFAULT, 1),
        ]);
    }

    /**
     * Creates a Text and media area (label).
     *
     * @param int $courseid
     * @param int $section
     * @param string $content
     * @param string $name
     * @param int $visible
     * @return array cmid and instanceid of the new label
     */
    public static function execute(int $courseid, int $section, string $content, string $name = '', int $visible = 1): array {
        [
            'courseid' => $courseid,
            'section' => $section,
            'content' => $content,
            'name' => $name,
            'visible' => $visible,
        ] = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'section' => $section,
            'content' => $content,
            'name' => $name,
            'visible' => $visible,
        ]);

        $course = get_course($courseid);
        self::validate_context(\context_course::instance($course->id));
        course_helper::require_can_add_module($course, 'label', $visible);
        course_helper::require_not_empty('content', $content);
        course_helper::validate_visible($visible);
        course_helper::get_section($course, $section);

        // The label's text is its intro; an empty name makes Moodle build one from the text.
        $moduleinfo = course_helper::add_module($course, 'label', $section, $visible, [
            'name' => trim($name),
            'intro' => $content,
            'introformat' => FORMAT_HTML,
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
            'cmid' => new external_value(PARAM_INT, 'Course module id of the new label'),
            'instanceid' => new external_value(PARAM_INT, 'Label instance id'),
        ]);
    }
}
