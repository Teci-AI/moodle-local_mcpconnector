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
 * Tests for local_mcpconnector_add_truefalse_question.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(add_truefalse_question::class)]
final class add_truefalse_question_test extends \local_mcpconnector\question_testcase {
    /**
     * A false statement is saved with False as the correct answer.
     */
    public function test_execute(): void {
        global $DB;
        $this->login_as('editingteacher');

        $result = $this->call(add_truefalse_question::class, [
            'quizcmid' => $this->quiz->cmid, 'name' => 'TF', 'questiontext' => 'The sun is cold.', 'correctanswer' => 0,
        ]);

        $question = $this->assert_question_in_quiz($result, 1, 'truefalse');
        $options = $DB->get_record('question_truefalse', ['question' => $question->id], '*', MUST_EXIST);
        $this->assertEquals(0, $DB->get_field('question_answers', 'fraction', ['id' => $options->trueanswer]));
        $this->assertEquals(1, $DB->get_field('question_answers', 'fraction', ['id' => $options->falseanswer]));
    }

    /**
     * correctanswer must be 0 or 1.
     */
    public function test_execute_invalid_answer(): void {
        $this->login_as('editingteacher');

        $this->expectException(invalid_parameter_exception::class);
        $this->call(add_truefalse_question::class, [
            'quizcmid' => $this->quiz->cmid, 'name' => 'TF', 'questiontext' => 'Text', 'correctanswer' => 2,
        ]);
    }
}
