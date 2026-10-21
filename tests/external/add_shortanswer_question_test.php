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
 * Tests for local_mcpconnector_add_shortanswer_question.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(add_shortanswer_question::class)]
final class add_shortanswer_question_test extends \local_mcpconnector\question_testcase {
    /**
     * Every accepted answer is worth full marks.
     */
    public function test_execute(): void {
        global $DB;
        $this->login_as('editingteacher');

        $result = $this->call(add_shortanswer_question::class, [
            'quizcmid' => $this->quiz->cmid,
            'name' => 'Capital',
            'questiontext' => 'Capital of France?',
            'answers' => ['Paris', 'paris '],
            'usecase' => 1,
        ]);

        $question = $this->assert_question_in_quiz($result, 1, 'shortanswer');
        $answers = $DB->get_records_menu('question_answers', ['question' => $question->id], 'id', 'answer, fraction');
        $this->assertEquals(['Paris' => 1, 'paris' => 1], $answers);
        $this->assertEquals(1, $DB->get_field('qtype_shortanswer_options', 'usecase', ['questionid' => $question->id]));
    }

    /**
     * Empty answers are rejected.
     */
    public function test_execute_empty_answer(): void {
        $this->login_as('editingteacher');

        $this->expectException(invalid_parameter_exception::class);
        $this->call(add_shortanswer_question::class, [
            'quizcmid' => $this->quiz->cmid, 'name' => 'Q', 'questiontext' => 'Text', 'answers' => ['Paris', ' '],
        ]);
    }
}
