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
 * Web service function local_mcpconnector_add_quiz_question: adds a multiple-choice question to a quiz.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_quiz_question extends external_api {
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
                'text' => new external_value(PARAM_RAW, 'Answer text (HTML)'),
                'fraction' => new external_value(
                    PARAM_FLOAT,
                    'Grade for this answer: 1.0 full marks, 0 wrong, negative penalises',
                    VALUE_DEFAULT,
                    0.0
                ),
                'feedback' => new external_value(PARAM_RAW, 'Feedback for this answer (HTML)', VALUE_DEFAULT, ''),
            ]), 'At least two answers'),
            'single' => new external_value(PARAM_INT, '1 for one correct answer, 0 for several', VALUE_DEFAULT, 1),
            'defaultmark' => new external_value(PARAM_FLOAT, 'Default mark', VALUE_DEFAULT, 1.0),
        ]);
    }

    /**
     * Creates a multiple-choice question in the quiz's question bank and adds it to the quiz.
     *
     * @param int $quizcmid
     * @param string $name
     * @param string $questiontext
     * @param array $answers
     * @param int $single
     * @param float $defaultmark
     * @return array questionid and slot
     */
    public static function execute(
        int $quizcmid,
        string $name,
        string $questiontext,
        array $answers,
        int $single = 1,
        float $defaultmark = 1.0
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'quizcmid' => $quizcmid,
            'name' => $name,
            'questiontext' => $questiontext,
            'answers' => $answers,
            'single' => $single,
            'defaultmark' => $defaultmark,
        ]);

        [$course, $cm] = get_course_and_cm_from_cmid($params['quizcmid'], 'quiz');
        self::validate_context(\context_course::instance($course->id));
        $quizobj = question_helper::get_editable_quiz($cm);

        $form = question_helper::base_form($params['name'], $params['questiontext'], $params['defaultmark'], 0.3333333);
        $config = get_config('qtype_multichoice');
        $form->single = $params['single'];
        $form->shuffleanswers = $config->shuffleanswers;
        $form->answernumbering = $config->answernumbering;
        $form->showstandardinstruction = $config->showstandardinstruction;
        question_helper::add_combined_feedback($form);
        self::add_answers($form, $params['answers'], $params['single']);

        return question_helper::add_to_quiz($quizobj, 'multichoice', $form);
    }

    /**
     * Validates the answers as the multiple-choice question form does and adds them to the form data.
     *
     * @param \stdClass $form
     * @param array $answers
     * @param int $single
     * @throws invalid_parameter_exception
     */
    private static function add_answers(\stdClass $form, array $answers, int $single): void {
        if ($single !== 0 && $single !== 1) {
            throw new invalid_parameter_exception('single must be 0 or 1');
        }
        if (count($answers) < 2) {
            throw new invalid_parameter_exception('At least two answers are required');
        }

        $maxfraction = -1;
        $totalfraction = 0;
        foreach (array_values($answers) as $i => $answer) {
            if (trim($answer['text']) === '') {
                throw new invalid_parameter_exception("answers[{$i}].text must not be empty");
            }
            $fraction = question_helper::grade_fraction($answer['fraction'], "answers[{$i}].fraction");
            $form->answer[$i] = question_helper::editor($answer['text']);
            $form->fraction[$i] = $fraction;
            $form->feedback[$i] = question_helper::editor($answer['feedback']);
            $maxfraction = max($maxfraction, $fraction);
            $totalfraction += max($fraction, 0);
        }

        if ($single && abs($maxfraction - 1) > 0.0001) {
            throw new invalid_parameter_exception('With single = 1, one answer must have fraction 1.0');
        }
        if (!$single && abs($totalfraction - 1) > 0.0001) {
            throw new invalid_parameter_exception('With single = 0, the positive fractions must add up to 1.0');
        }
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
