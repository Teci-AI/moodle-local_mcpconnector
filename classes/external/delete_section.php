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
 * Web service function local_mcpconnector_delete_section.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_section extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'sectionnum' => new external_value(PARAM_INT, 'Section number within the course (1 or more)'),
        ]);
    }

    /**
     * Deletes a course section and every activity in it.
     *
     * @param int $courseid
     * @param int $sectionnum
     * @return array success flag
     */
    public static function execute(int $courseid, int $sectionnum): array {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        [
            'courseid' => $courseid,
            'sectionnum' => $sectionnum,
        ] = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'sectionnum' => $sectionnum,
        ]);

        $course = get_course($courseid);
        $context = \context_course::instance($course->id);
        self::validate_context($context);
        // The capabilities course_can_delete_section() checks, required one by one for a clear error.
        require_capability('moodle/course:update', $context);
        require_capability('moodle/course:movesections', $context);
        if ($sectionnum === 0) {
            throw new \moodle_exception('cannotdeletesectionzero', 'local_mcpconnector');
        }
        $section = course_helper::get_section($course, $sectionnum);
        foreach (get_fast_modinfo($course)->sections[$sectionnum] ?? [] as $cmid) {
            require_capability('moodle/course:manageactivities', \context_module::instance($cmid));
        }
        if (!course_get_format($course)->can_delete_section($section)) {
            throw new \moodle_exception('cannotdeletesection', 'local_mcpconnector', '', $sectionnum);
        }

        return [
            'success' => (bool) course_delete_section($course, $section, true, false),
        ];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'True when the section was deleted'),
        ]);
    }
}
