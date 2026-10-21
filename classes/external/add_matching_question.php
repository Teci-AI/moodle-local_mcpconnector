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
 * Web service function local_mcpconnector_add_matching_question.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_matching_question extends external_api {
    /** @var int pairs the matching question form requires (two questions and three answers) */
    private const MIN_PAIRS = 3;

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
            'pairs' => new external_multiple_structure(new external_single_structure([
                'question' => new external_value(PARAM_RAW, 'Item to match (HTML)'),
                'answer' => new external_value(PARAM_TEXT, 'Its matching answer'),
            ]), 'At least three question and answer pairs'),
            'shuffleanswers' => new external_value(PARAM_INT, '1 to shuffle the items, 0 to keep their order', VALUE_DEFAULT, 1),
            'defaultmark' => new external_value(PARAM_FLOAT, 'Default mark', VALUE_DEFAULT, 1.0),
        ]);
    }

    /**
     * Creates a matching question in the quiz's question bank and adds it to the quiz.
     *
     * @param int $quizcmid
     * @param string $name
     * @param string $questiontext
     * @param array $pairs
     * @param int $shuffleanswers
     * @param float $defaultmark
     * @return array questionid and slot
     */
    public static function execute(
        int $quizcmid,
        string $name,
        string $questiontext,
        array $pairs,
        int $shuffleanswers = 1,
        float $defaultmark = 1.0
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'quizcmid' => $quizcmid,
            'name' => $name,
            'questiontext' => $questiontext,
            'pairs' => $pairs,
            'shuffleanswers' => $shuffleanswers,
            'defaultmark' => $defaultmark,
        ]);

        [$course, $cm] = get_course_and_cm_from_cmid($params['quizcmid'], 'quiz');
        self::validate_context(\context_course::instance($course->id));
        $quizobj = question_helper::get_editable_quiz($cm);

        if ($params['shuffleanswers'] !== 0 && $params['shuffleanswers'] !== 1) {
            throw new invalid_parameter_exception('shuffleanswers must be 0 or 1');
        }
        if (count($params['pairs']) < self::MIN_PAIRS) {
            throw new invalid_parameter_exception(
                'At least ' . self::MIN_PAIRS . ' pairs are required: Moodle needs two questions and three answers'
            );
        }
        $form = question_helper::base_form($params['name'], $params['questiontext'], $params['defaultmark'], 0.3333333);
        $form->shuffleanswers = $params['shuffleanswers'];
        question_helper::add_combined_feedback($form);
        foreach (array_values($params['pairs']) as $i => $pair) {
            if (trim($pair['question']) === '' || trim($pair['answer']) === '') {
                throw new invalid_parameter_exception("pairs[{$i}] needs both a question and an answer");
            }
            $form->subquestions[$i] = question_helper::editor($pair['question']);
            $form->subanswers[$i] = trim($pair['answer']);
        }
        $form->noanswers = count($form->subquestions);

        return question_helper::add_to_quiz($quizobj, 'match', $form);
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
