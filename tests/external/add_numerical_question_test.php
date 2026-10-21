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
 * Tests for local_mcpconnector_add_numerical_question.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(add_numerical_question::class)]
final class add_numerical_question_test extends \local_mcpconnector\question_testcase {
    /**
     * Answers keep their tolerance and fraction; omitted ones default to full marks and no tolerance.
     */
    public function test_execute(): void {
        global $DB;
        $this->login_as('editingteacher');

        $result = $this->call(add_numerical_question::class, [
            'quizcmid' => $this->quiz->cmid,
            'name' => 'Pi',
            'questiontext' => 'Pi to two decimals?',
            'answers' => [
                ['answer' => 3.14],
                ['answer' => 3.1, 'tolerance' => 0.05, 'fraction' => 0.5, 'feedback' => 'Close'],
            ],
        ]);

        $question = $this->assert_question_in_quiz($result, 1, 'numerical');
        $answers = array_values($DB->get_records_sql(
            "SELECT qa.answer, qa.fraction, qa.feedback, qn.tolerance
               FROM {question_answers} qa
               JOIN {question_numerical} qn ON qn.answer = qa.id
              WHERE qa.question = ?
           ORDER BY qa.id",
            [$question->id]
        ));
        $this->assertEquals(3.14, $answers[0]->answer);
        $this->assertEquals(1, $answers[0]->fraction);
        $this->assertEquals(0, $answers[0]->tolerance);
        $this->assertEquals(0.5, $answers[1]->fraction);
        $this->assertEquals(0.05, $answers[1]->tolerance);
        $this->assertSame('Close', $answers[1]->feedback);
    }

    /**
     * One answer must be worth full marks.
     */
    public function test_execute_no_full_marks(): void {
        $this->login_as('editingteacher');

        $this->expectException(invalid_parameter_exception::class);
        $this->call(add_numerical_question::class, [
            'quizcmid' => $this->quiz->cmid, 'name' => 'Pi', 'questiontext' => 'Pi?',
            'answers' => [['answer' => 3.14, 'fraction' => 0.5]],
        ]);
    }
}
