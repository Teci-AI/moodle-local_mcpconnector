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
use local_mcpconnector\local\invalid_parameter_exception;
use local_mcpconnector\local\course_helper;
use mod_quiz\question\display_options;

/**
 * Web service function local_mcpconnector_create_quiz.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_quiz extends external_api {
    /** @var string[] review option fields of the quiz settings form */
    private const REVIEW_FIELDS = [
        'attempt', 'correctness', 'maxmarks', 'marks', 'specificfeedback', 'generalfeedback', 'rightanswer', 'overallfeedback',
    ];

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'section' => new external_value(PARAM_INT, 'Section number within the course', VALUE_DEFAULT, 0),
            'name' => new external_value(PARAM_TEXT, 'Quiz name'),
            'intro' => new external_value(PARAM_RAW, 'Description (HTML)', VALUE_DEFAULT, ''),
            'timeopen' => new external_value(PARAM_INT, 'Opening time (Unix timestamp), 0 for none', VALUE_DEFAULT, 0),
            'timeclose' => new external_value(PARAM_INT, 'Closing time (Unix timestamp), 0 for none', VALUE_DEFAULT, 0),
            'timelimit' => new external_value(PARAM_INT, 'Time limit in seconds, 0 for none', VALUE_DEFAULT, 0),
            'grade' => new external_value(PARAM_FLOAT, 'Maximum grade', VALUE_DEFAULT, 100.0),
            'attempts' => new external_value(PARAM_INT, 'Attempts allowed, 0 for unlimited', VALUE_DEFAULT, 0),
            'visible' => new external_value(PARAM_INT, '1 to show the quiz, 0 to hide it', VALUE_DEFAULT, 1),
        ]);
    }

    /**
     * Creates an empty Quiz activity, with the site's default quiz settings for everything not given.
     *
     * @param int $courseid
     * @param int $section
     * @param string $name
     * @param string $intro
     * @param int $timeopen
     * @param int $timeclose
     * @param int $timelimit
     * @param float $grade
     * @param int $attempts
     * @param int $visible
     * @return array cmid and instanceid of the new quiz
     */
    public static function execute(
        int $courseid,
        int $section,
        string $name,
        string $intro = '',
        int $timeopen = 0,
        int $timeclose = 0,
        int $timelimit = 0,
        float $grade = 100.0,
        int $attempts = 0,
        int $visible = 1
    ): array {
        global $CFG;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'section' => $section,
            'name' => $name,
            'intro' => $intro,
            'timeopen' => $timeopen,
            'timeclose' => $timeclose,
            'timelimit' => $timelimit,
            'grade' => $grade,
            'attempts' => $attempts,
            'visible' => $visible,
        ]);

        $course = get_course($params['courseid']);
        self::validate_context(\context_course::instance($course->id));
        course_helper::require_can_add_module($course, 'quiz', $params['visible']);
        course_helper::require_not_empty('name', $params['name']);
        course_helper::validate_visible($params['visible']);
        self::validate_settings($params);
        course_helper::get_section($course, $params['section']);

        $moduleinfo = course_helper::add_module(
            $course,
            'quiz',
            $params['section'],
            $params['visible'],
            self::default_settings() + [
                'name' => $params['name'],
                'intro' => $params['intro'],
                'introformat' => FORMAT_HTML,
                'timeopen' => $params['timeopen'],
                'timeclose' => $params['timeclose'],
                'timelimit' => $params['timelimit'],
                'grade' => $params['grade'],
                'attempts' => $params['attempts'],
            ]
        );

        return [
            'cmid' => (int) $moduleinfo->coursemodule,
            'instanceid' => (int) $moduleinfo->instance,
        ];
    }

    /**
     * Applies the checks of the quiz settings form to the given values.
     *
     * @param array $params validated parameters
     * @throws invalid_parameter_exception
     */
    private static function validate_settings(array $params): void {
        foreach (['timeopen', 'timeclose', 'timelimit', 'attempts'] as $field) {
            if ($params[$field] < 0) {
                throw new invalid_parameter_exception("{$field} must not be negative");
            }
        }
        if ($params['grade'] < 0) {
            throw new invalid_parameter_exception('grade must not be negative');
        }
        if ($params['timeopen'] && $params['timeclose'] && $params['timeclose'] < $params['timeopen']) {
            throw new invalid_parameter_exception(get_string('closebeforeopen', 'quiz'));
        }
    }

    /**
     * Returns the defaults the quiz settings form starts from (Site administration > Plugins > Quiz).
     *
     * @return array quiz settings, in the form's field names
     */
    private static function default_settings(): array {
        $config = get_config('quiz');
        $settings = [
            'preferredbehaviour' => $config->preferredbehaviour,
            'canredoquestions' => $config->canredoquestions,
            'attemptonlast' => $config->attemptonlast,
            'grademethod' => $config->grademethod,
            'decimalpoints' => $config->decimalpoints,
            'questiondecimalpoints' => $config->questiondecimalpoints,
            'questionsperpage' => $config->questionsperpage,
            'navmethod' => $config->navmethod,
            'shuffleanswers' => $config->shuffleanswers,
            'overduehandling' => $config->overduehandling,
            'graceperiod' => $config->graceperiod,
            'showuserpicture' => $config->showuserpicture,
            'showblocks' => $config->showblocks,
            'quizpassword' => $config->quizpassword ?? '',
            'subnet' => $config->subnet,
            'browsersecurity' => $config->browsersecurity,
            'delay1' => $config->delay1,
            'delay2' => $config->delay2,
            'sumgrades' => 0,
        ];
        $whens = [
            'during' => display_options::DURING,
            'immediately' => display_options::IMMEDIATELY_AFTER,
            'open' => display_options::LATER_WHILE_OPEN,
            'closed' => display_options::AFTER_CLOSE,
        ];
        foreach (self::REVIEW_FIELDS as $field) {
            foreach ($whens as $whenname => $when) {
                $settings[$field . $whenname] = ($config->{'review' . $field} & $when) ? 1 : 0;
            }
        }
        return $settings;
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'Course module id of the new quiz'),
            'instanceid' => new external_value(PARAM_INT, 'Quiz instance id'),
        ]);
    }
}
