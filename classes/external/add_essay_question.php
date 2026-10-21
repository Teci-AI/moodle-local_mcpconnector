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
use local_mcpconnector\local\question_helper;

/**
 * Web service function local_mcpconnector_add_essay_question.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_essay_question extends external_api {
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
            'graderinfo' => new external_value(PARAM_RAW, 'Information for graders (HTML)', VALUE_DEFAULT, ''),
            'responsetemplate' => new external_value(PARAM_RAW, 'Text the response box starts with (HTML)', VALUE_DEFAULT, ''),
            'responselines' => new external_value(PARAM_INT, 'Response box size in lines: 2, 3, 5, 10, 15… 40', VALUE_DEFAULT, 15),
            'attachments' => new external_value(PARAM_INT, 'Files allowed: 0 none, 1 to 3, -1 unlimited', VALUE_DEFAULT, 0),
            'defaultmark' => new external_value(PARAM_FLOAT, 'Default mark', VALUE_DEFAULT, 1.0),
        ]);
    }

    /**
     * Creates an essay question in the quiz's question bank and adds it to the quiz.
     *
     * @param int $quizcmid
     * @param string $name
     * @param string $questiontext
     * @param string $graderinfo
     * @param string $responsetemplate
     * @param int $responselines
     * @param int $attachments
     * @param float $defaultmark
     * @return array questionid and slot
     */
    public static function execute(
        int $quizcmid,
        string $name,
        string $questiontext,
        string $graderinfo = '',
        string $responsetemplate = '',
        int $responselines = 15,
        int $attachments = 0,
        float $defaultmark = 1.0
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'quizcmid' => $quizcmid,
            'name' => $name,
            'questiontext' => $questiontext,
            'graderinfo' => $graderinfo,
            'responsetemplate' => $responsetemplate,
            'responselines' => $responselines,
            'attachments' => $attachments,
            'defaultmark' => $defaultmark,
        ]);

        [$course, $cm] = get_course_and_cm_from_cmid($params['quizcmid'], 'quiz');
        self::validate_context(\context_course::instance($course->id));
        $quizobj = question_helper::get_editable_quiz($cm);

        // The essay form only offers these choices.
        $qtype = \question_bank::get_qtype('essay');
        if (!array_key_exists($params['responselines'], $qtype->response_sizes())) {
            throw new invalid_parameter_exception('responselines must be one of ' .
                implode(', ', array_keys($qtype->response_sizes())));
        }
        if (!array_key_exists($params['attachments'], $qtype->attachment_options())) {
            throw new invalid_parameter_exception('attachments must be 0, 1, 2, 3 or -1');
        }

        $form = question_helper::base_form($params['name'], $params['questiontext'], $params['defaultmark'], 0);
        $form->responseformat = 'editor';
        $form->responserequired = 1;
        $form->responsefieldlines = $params['responselines'];
        $form->attachments = $params['attachments'];
        $form->attachmentsrequired = 0;
        $form->maxbytes = 0;
        $form->filetypeslist = '';
        $form->graderinfo = question_helper::editor($params['graderinfo']);
        $form->responsetemplate = question_helper::editor($params['responsetemplate']);

        return question_helper::add_to_quiz($quizobj, 'essay', $form);
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
