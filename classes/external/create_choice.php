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
use local_mcpconnector\local\course_helper;
use local_mcpconnector\local\invalid_parameter_exception;

/**
 * Web service function local_mcpconnector_create_choice.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_choice extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'section' => new external_value(PARAM_INT, 'Section number within the course', VALUE_DEFAULT, 0),
            'name' => new external_value(PARAM_TEXT, 'Choice name'),
            'question' => new external_value(PARAM_RAW, 'The question students answer (HTML), shown as the description'),
            'options' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'An option students can pick'),
                'At least two options'
            ),
            'allowupdate' => new external_value(PARAM_INT, '1 to let students change their choice, 0 not to', VALUE_DEFAULT, 1),
            'visible' => new external_value(PARAM_INT, '1 to show the choice, 0 to hide it', VALUE_DEFAULT, 1),
        ]);
    }

    /**
     * Creates a Choice (poll) activity, with the choice settings form's defaults for everything not given.
     *
     * @param int $courseid
     * @param int $section
     * @param string $name
     * @param string $question
     * @param array $options
     * @param int $allowupdate
     * @param int $visible
     * @return array cmid and instanceid of the new choice
     */
    public static function execute(
        int $courseid,
        int $section,
        string $name,
        string $question,
        array $options,
        int $allowupdate = 1,
        int $visible = 1
    ): array {
        global $CFG;
        require_once($CFG->dirroot . '/mod/choice/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'section' => $section,
            'name' => $name,
            'question' => $question,
            'options' => $options,
            'allowupdate' => $allowupdate,
            'visible' => $visible,
        ]);

        $course = get_course($params['courseid']);
        self::validate_context(\context_course::instance($course->id));
        course_helper::require_can_add_module($course, 'choice', $params['visible']);
        course_helper::require_not_empty('name', $params['name']);
        course_helper::require_not_empty('question', $params['question']);
        course_helper::validate_visible($params['visible']);
        if ($params['allowupdate'] !== 0 && $params['allowupdate'] !== 1) {
            throw new invalid_parameter_exception('allowupdate must be 0 or 1');
        }
        $choiceoptions = [];
        foreach (array_values($params['options']) as $i => $option) {
            if (trim($option) === '') {
                throw new invalid_parameter_exception("options[{$i}] must not be empty");
            }
            $choiceoptions[] = trim($option);
        }
        if (count($choiceoptions) < 2) {
            throw new invalid_parameter_exception('At least two options are required');
        }
        course_helper::get_section($course, $params['section']);

        // Unset settings take the first choice of each list in the choice settings form.
        $moduleinfo = course_helper::add_module($course, 'choice', $params['section'], $params['visible'], [
            'name' => $params['name'],
            'intro' => $params['question'],
            'introformat' => FORMAT_HTML,
            'option' => $choiceoptions,
            'limit' => array_fill(0, count($choiceoptions), 0),
            'allowupdate' => $params['allowupdate'],
            'allowmultiple' => 0,
            'limitanswers' => 0,
            'display' => CHOICE_DISPLAY_HORIZONTAL,
            'showresults' => CHOICE_SHOWRESULTS_NOT,
            'publish' => CHOICE_PUBLISH_ANONYMOUS,
            'showunanswered' => 0,
            'includeinactive' => 0,
            'showpreview' => 0,
            'showavailable' => 0,
            'timeopen' => 0,
            'timeclose' => 0,
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
            'cmid' => new external_value(PARAM_INT, 'Course module id of the new choice'),
            'instanceid' => new external_value(PARAM_INT, 'Choice instance id'),
        ]);
    }
}
