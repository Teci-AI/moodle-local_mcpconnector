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
use local_mcpconnector\local\question_helper;

/**
 * Web service function local_mcpconnector_add_numerical_question.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_numerical_question extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'quizcmid' => new external_value(PARAM_INT, 'Course module id of the quiz'),
            'name' => new external_value(PARAM_TEXT, 'Question name'),
            'questiontext' => new external_value(PARAM_RAW, 'Question text (HTML)'),
            'answers' => new external_multiple_structure(new external_single_structure([
                'answer' => new external_value(PARAM_FLOAT, 'The numeric answer'),
                'tolerance' => new external_value(PARAM_FLOAT, 'Accepted error (plus or minus)', VALUE_DEFAULT, 0.0),
                'fraction' => new external_value(PARAM_FLOAT, 'Grade for this answer, 1.0 full marks', VALUE_DEFAULT, 1.0),
                'feedback' => new external_value(PARAM_RAW, 'Feedback for this answer (HTML)', VALUE_DEFAULT, ''),
            ]), 'At least one answer; one must have fraction 1.0'),
            'defaultmark' => new external_value(PARAM_FLOAT, 'Default mark', VALUE_DEFAULT, 1.0),
        ]);
    }

    /**
     * Creates a numerical question (without units) in the quiz's question bank and adds it to the quiz.
     *
     * @param int $quizcmid
     * @param string $name
     * @param string $questiontext
     * @param array $answers
     * @param float $defaultmark
     * @return array questionid and slot
     */
    public static function execute(
        int $quizcmid,
        string $name,
        string $questiontext,
        array $answers,
        float $defaultmark = 1.0
    ): array {
        global $CFG;
        require_once($CFG->dirroot . '/question/type/numerical/questiontype.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'quizcmid' => $quizcmid,
            'name' => $name,
            'questiontext' => $questiontext,
            'answers' => $answers,
            'defaultmark' => $defaultmark,
        ]);

        [$course, $cm] = get_course_and_cm_from_cmid($params['quizcmid'], 'quiz');
        self::validate_context(\context_course::instance($course->id));
        $quizobj = question_helper::get_editable_quiz($cm);

        if (!$params['answers']) {
            throw new invalid_parameter_exception('At least one answer is required');
        }
        $form = question_helper::base_form($params['name'], $params['questiontext'], $params['defaultmark'], 0.3333333);
        $maxfraction = -1;
        foreach (array_values($params['answers']) as $i => $answer) {
            if ($answer['tolerance'] < 0) {
                throw new invalid_parameter_exception("answers[{$i}].tolerance must not be negative");
            }
            $fraction = question_helper::grade_fraction($answer['fraction'], "answers[{$i}].fraction");
            $form->answer[$i] = (string) $answer['answer'];
            $form->tolerance[$i] = $answer['tolerance'];
            $form->fraction[$i] = $fraction;
            $form->feedback[$i] = question_helper::editor($answer['feedback']);
            $maxfraction = max($maxfraction, $fraction);
        }
        if (abs($maxfraction - 1) > 0.0001) {
            throw new invalid_parameter_exception('One answer must have fraction 1.0');
        }

        // No units, as the numerical question form's default.
        $form->unitrole = \qtype_numerical::UNITNONE;
        $form->unitpenalty = 0.1;
        $form->unitgradingtypes = \qtype_numerical::UNITGRADEDOUTOFMARK;
        $form->unitsleft = 0;
        $form->multichoicedisplay = \qtype_numerical::UNITINPUT;

        return question_helper::add_to_quiz($quizobj, 'numerical', $form);
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return question_helper::returns();
    }
}
