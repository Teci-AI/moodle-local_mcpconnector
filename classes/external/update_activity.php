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
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_mcpconnector\local\invalid_parameter_exception;

/**
 * Web service function local_mcpconnector_update_activity.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_activity extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'name' => new external_value(PARAM_TEXT, 'New name; empty keeps the current one', VALUE_DEFAULT, ''),
            'visible' => new external_value(PARAM_INT, '1 show, 0 hide, -1 leave unchanged', VALUE_DEFAULT, -1),
        ]);
    }

    /**
     * Renames and/or shows or hides an activity.
     *
     * @param int $cmid
     * @param string $name
     * @param int $visible
     * @return array the activity's name and visibility after the update, and what changed
     */
    public static function execute(int $cmid, string $name = '', int $visible = -1): array {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        [
            'cmid' => $cmid,
            'name' => $name,
            'visible' => $visible,
        ] = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'name' => $name,
            'visible' => $visible,
        ]);

        [$course, $cm] = get_course_and_cm_from_cmid($cmid);
        self::validate_context(\context_course::instance($course->id));
        require_capability('moodle/course:manageactivities', $cm->context);
        if (!in_array($visible, [-1, 0, 1], true)) {
            throw new invalid_parameter_exception('visible must be 1, 0 or -1');
        }
        if ($visible !== -1) {
            require_capability('moodle/course:activityvisibility', $cm->context);
        }

        $changed = [];
        $name = trim($name);
        if ($name !== '' && set_coursemodule_name($cm->id, $name)) {
            $changed[] = 'name';
        }
        if ($visible !== -1 && set_coursemodule_visible($cm->id, $visible)) {
            // Like core_course_edit_module, which triggers this event after a visibility change.
            \core\event\course_module_updated::create_from_cm($cm, $cm->context)->trigger();
            $changed[] = 'visible';
        }

        $cm = get_fast_modinfo($course->id)->get_cm($cm->id);
        return [
            'cmid' => (int) $cm->id,
            'name' => $cm->name,
            'visible' => (int) $cm->visible,
            'changed' => $changed,
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
            'name' => new external_value(PARAM_TEXT, 'Activity name after the update'),
            'visible' => new external_value(PARAM_INT, 'Visibility after the update: 1 shown, 0 hidden'),
            'changed' => new external_multiple_structure(
                new external_value(PARAM_ALPHA, 'A changed field: name or visible'),
                'The fields that were changed'
            ),
        ]);
    }
}
