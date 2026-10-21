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

use invalid_parameter_exception;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/mcpconnector/tests/question_testcase.php');

/**
 * Tests for local_mcpconnector_add_matching_question.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(add_matching_question::class)]
final class add_matching_question_test extends \local_mcpconnector\question_testcase {
    /**
     * The pairs are saved as subquestions.
     */
    public function test_execute(): void {
        global $DB;
        $this->login_as('editingteacher');

        $result = $this->call(add_matching_question::class, [
            'quizcmid' => $this->quiz->cmid,
            'name' => 'Capitals',
            'questiontext' => 'Match each country to its capital.',
            'pairs' => [
                ['question' => 'France', 'answer' => 'Paris'],
                ['question' => 'Italy', 'answer' => 'Rome'],
                ['question' => 'Spain', 'answer' => 'Madrid'],
            ],
            'shuffleanswers' => 0,
        ]);

        $question = $this->assert_question_in_quiz($result, 1, 'match');
        $pairs = $DB->get_records_menu(
            'qtype_match_subquestions',
            ['questionid' => $question->id],
            'id',
            'questiontext, answertext'
        );
        $this->assertEquals(['France' => 'Paris', 'Italy' => 'Rome', 'Spain' => 'Madrid'], $pairs);
        $this->assertEquals(0, $DB->get_field('qtype_match_options', 'shuffleanswers', ['questionid' => $question->id]));
    }

    /**
     * Two pairs are not enough: Moodle needs two questions and three answers.
     */
    public function test_execute_two_pairs(): void {
        $this->login_as('editingteacher');

        $this->expectException(invalid_parameter_exception::class);
        $this->call(add_matching_question::class, [
            'quizcmid' => $this->quiz->cmid, 'name' => 'Capitals', 'questiontext' => 'Match.',
            'pairs' => [['question' => 'France', 'answer' => 'Paris'], ['question' => 'Italy', 'answer' => 'Rome']],
        ]);
    }
}
