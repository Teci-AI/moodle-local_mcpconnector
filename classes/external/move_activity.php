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
 * Web service function local_mcpconnector_move_activity.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class move_activity extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'section' => new external_value(PARAM_INT, 'Target section number within the course'),
        ]);
    }

    /**
     * Moves an activity to the end of another section of its course.
     *
     * @param int $cmid
     * @param int $section
     * @return array cmid and the section number it is now in
     */
    public static function execute(int $cmid, int $section): array {
        [
            'cmid' => $cmid,
            'section' => $section,
        ] = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'section' => $section,
        ]);

        [$course, $cm] = get_course_and_cm_from_cmid($cmid);
        self::validate_context(\context_course::instance($course->id));
        require_capability('moodle/course:manageactivities', $cm->context);
        $target = course_helper::get_section($course, $section);

        course_helper::move_module_to_section($cm, $target);

        $cm = get_fast_modinfo($course->id)->get_cm($cm->id);
        return [
            'cmid' => (int) $cm->id,
            'section' => (int) $cm->sectionnum,
        ];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'section' => new external_value(PARAM_INT, 'Section number the activity is now in'),
        ]);
    }
}
