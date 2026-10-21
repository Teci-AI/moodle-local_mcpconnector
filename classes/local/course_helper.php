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

namespace local_mcpconnector\local;

use invalid_parameter_exception;

/**
 * Course and activity helpers shared by the web service functions.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_helper {
    /**
     * Returns an existing section of a course by its number.
     *
     * @param \stdClass $course
     * @param int $sectionnum section number within the course
     * @return \section_info
     * @throws \moodle_exception if the course has no such section
     */
    public static function get_section(\stdClass $course, int $sectionnum): \section_info {
        $section = $sectionnum >= 0 ? get_fast_modinfo($course)->get_section_info($sectionnum) : null;
        if (!$section) {
            throw new \moodle_exception('sectionnotfound', 'local_mcpconnector', '', $sectionnum);
        }
        return $section;
    }

    /**
     * Checks a visible flag is 0 or 1.
     *
     * @param int $visible
     * @throws invalid_parameter_exception
     */
    public static function validate_visible(int $visible): void {
        if ($visible !== 0 && $visible !== 1) {
            throw new invalid_parameter_exception('visible must be 0 or 1');
        }
    }

    /**
     * Checks a required text parameter is not empty.
     *
     * @param string $name parameter name, for the error message
     * @param string $value
     * @throws invalid_parameter_exception
     */
    public static function require_not_empty(string $name, string $value): void {
        if (trim($value) === '') {
            throw new invalid_parameter_exception("{$name} must not be empty");
        }
    }

    /**
     * Requires the capabilities Moodle's "Add an activity" page requires.
     *
     * @param \stdClass $course
     * @param string $modname module name, e.g. 'page'
     * @param int $visible whether the new activity is visible; hiding it needs moodle/course:activityvisibility
     * @throws \moodle_exception if the module is disabled on the site
     */
    public static function require_can_add_module(\stdClass $course, string $modname, int $visible): void {
        global $DB;

        $context = \context_course::instance($course->id);
        require_capability('moodle/course:manageactivities', $context);
        require_capability("mod/{$modname}:addinstance", $context);
        if (!$visible) {
            require_capability('moodle/course:activityvisibility', $context);
        }
        if (!$DB->get_field('modules', 'visible', ['name' => $modname])) {
            throw new \moodle_exception('moduledisable', 'error', '', $modname);
        }
    }

    /**
     * Adds an activity to a course through add_moduleinfo().
     *
     * @param \stdClass $course
     * @param string $modname module name, e.g. 'page'
     * @param int $sectionnum section number the activity goes into
     * @param int $visible
     * @param array $fields module-specific fields (name, intro, content…)
     * @return \stdClass the module info returned by add_moduleinfo(), with coursemodule and instance set
     */
    public static function add_module(\stdClass $course, string $modname, int $sectionnum, int $visible, array $fields): \stdClass {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/modlib.php');

        $moduleinfo = (object) $fields;
        $moduleinfo->modulename = $modname;
        $moduleinfo->module = $DB->get_field('modules', 'id', ['name' => $modname], MUST_EXIST);
        $moduleinfo->section = $sectionnum;
        $moduleinfo->visible = $visible;
        $moduleinfo->visibleoncoursepage = 1;

        return add_moduleinfo($moduleinfo, $course);
    }

    /**
     * Moves an activity to the end of a section.
     *
     * Uses the course format actions from Moodle 5.2, where moveto_module() is deprecated.
     *
     * @param \cm_info $cm
     * @param \section_info $section
     */
    public static function move_module_to_section(\cm_info $cm, \section_info $section): void {
        global $CFG;

        if (method_exists(\core_courseformat\local\cmactions::class, 'move_end_section')) {
            \core_courseformat\formatactions::cm($cm->course)->move_end_section($cm->id, $section->id);
        } else {
            require_once($CFG->dirroot . '/course/lib.php');
            moveto_module($cm, $section);
        }
    }

    /**
     * Deletes an activity.
     *
     * Uses the course format actions from Moodle 5.2, where course_delete_module() is deprecated.
     *
     * @param \cm_info $cm
     */
    public static function delete_module(\cm_info $cm): void {
        global $CFG;

        if (method_exists(\core_courseformat\local\cmactions::class, 'delete')) {
            \core_courseformat\formatactions::cm($cm->course)->delete($cm->id);
        } else {
            require_once($CFG->dirroot . '/course/lib.php');
            course_delete_module($cm->id);
        }
    }
}
