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
use invalid_parameter_exception;
use local_mcpconnector\local\course_helper;

/**
 * Web service function local_mcpconnector_create_section.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_section extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'name' => new external_value(PARAM_TEXT, 'Section name'),
            'summary' => new external_value(PARAM_RAW, 'Section summary (HTML)', VALUE_DEFAULT, ''),
            'position' => new external_value(
                PARAM_INT,
                'Section number the new section takes, moving later sections down; 0 adds it at the end',
                VALUE_DEFAULT,
                0
            ),
        ]);
    }

    /**
     * Creates a course section.
     *
     * @param int $courseid
     * @param string $name
     * @param string $summary
     * @param int $position
     * @return array sectionid and sectionnum of the new section
     */
    public static function execute(int $courseid, string $name, string $summary = '', int $position = 0): array {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        [
            'courseid' => $courseid,
            'name' => $name,
            'summary' => $summary,
            'position' => $position,
        ] = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'name' => $name,
            'summary' => $summary,
            'position' => $position,
        ]);

        $course = get_course($courseid);
        $context = \context_course::instance($course->id);
        self::validate_context($context);
        require_capability('moodle/course:update', $context);
        if ($position < 0) {
            throw new invalid_parameter_exception('position must be 0 or more');
        }
        // As on the course page, inserting anywhere but at the end needs the capability to move sections.
        $lastsectionnum = max(array_keys(get_fast_modinfo($course)->get_section_info_all()));
        if ($position > 0 && $position <= $lastsectionnum) {
            require_capability('moodle/course:movesections', $context);
        }
        course_helper::require_not_empty('name', $name);

        $section = course_create_section($course, $position);
        course_update_section($course, $section, [
            'name' => $name,
            'summary' => $summary,
            'summaryformat' => FORMAT_HTML,
        ]);

        return [
            'sectionid' => (int) $section->id,
            'sectionnum' => (int) $section->section,
        ];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'sectionid' => new external_value(PARAM_INT, 'Section id'),
            'sectionnum' => new external_value(PARAM_INT, 'Section number within the course'),
        ]);
    }
}
