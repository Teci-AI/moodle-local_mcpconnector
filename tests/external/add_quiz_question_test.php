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
 * Tests for local_mcpconnector_add_quiz_question (multiple choice).
 *
 * @package    local_mcpconnector
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(add_quiz_question::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_mcpconnector\local\question_helper::class)]
final class add_quiz_question_test extends \local_mcpconnector\question_testcase {
    /**
     * Calls the function with test answers.
     *
     * @param array $answers
     * @param int $single
     * @return array
     */
    private function add(array $answers, int $single = 1): array {
        return $this->call(add_quiz_question::class, [
            'quizcmid' => $this->quiz->cmid,
            'name' => 'Q1',
            'questiontext' => '<p>Pick one</p>',
            'answers' => $answers,
            'single' => $single,
            'defaultmark' => 2,
        ]);
    }

    /**
     * A single-answer question is saved in the quiz's bank and added to the quiz.
     */
    public function test_execute_single(): void {
        global $DB;
        $this->login_as('editingteacher');
        $sink = $this->redirectEvents();

        $result = $this->add([
            ['text' => 'A', 'fraction' => 1.0, 'feedback' => '<p>Yes</p>'],
            ['text' => 'B', 'fraction' => 0.0],
            ['text' => 'C'],
        ]);

        $question = $this->assert_question_in_quiz($result, 1, 'multichoice');
        $this->assertSame('Q1', $question->name);
        $this->assertSame('<p>Pick one</p>', $question->questiontext);
        $this->assertEquals(2, $question->defaultmark);
        $answers = array_values($DB->get_records('question_answers', ['question' => $question->id], 'id'));
        $this->assertCount(3, $answers);
        $this->assertSame('A', $answers[0]->answer);
        $this->assertEquals(1, $answers[0]->fraction);
        $this->assertSame('<p>Yes</p>', $answers[0]->feedback);
        $this->assertEquals(0, $answers[2]->fraction);
        $this->assertEquals(1, $DB->get_field('qtype_multichoice_options', 'single', ['questionid' => $question->id]));

        $events = array_filter($sink->get_events(), fn($e) => $e instanceof \core\event\question_created);
        $this->assertCount(1, $events);
    }

    /**
     * A multiple-answer question accepts thirds written approximately, and the next question takes the next slot.
     */
    public function test_execute_multiple(): void {
        global $DB;
        $this->login_as('editingteacher');
        $this->add([['text' => 'A', 'fraction' => 1.0], ['text' => 'B']]);

        $result = $this->add([
            ['text' => 'A', 'fraction' => 0.33],
            ['text' => 'B', 'fraction' => 0.3333333],
            ['text' => 'C', 'fraction' => 0.333],
            ['text' => 'D', 'fraction' => -1.0],
        ], 0);

        $question = $this->assert_question_in_quiz($result, 2, 'multichoice');
        $fractions = $DB->get_fieldset_select('question_answers', 'fraction', 'question = ? ORDER BY id', [$question->id]);
        $this->assertEqualsWithDelta([0.3333333, 0.3333333, 0.3333333, -1.0], array_map('floatval', $fractions), 0.0000001);
    }

    /**
     * A fraction that is not a Moodle grade value is rejected.
     */
    public function test_execute_invalid_fraction(): void {
        $this->login_as('editingteacher');

        $this->expectException(invalid_parameter_exception::class);
        $this->add([['text' => 'A', 'fraction' => 1.0], ['text' => 'B', 'fraction' => 0.37]]);
    }

    /**
     * A single-answer question needs an answer worth full marks.
     */
    public function test_execute_single_without_full_marks(): void {
        $this->login_as('editingteacher');

        $this->expectException(invalid_parameter_exception::class);
        $this->expectExceptionMessage('one answer must have fraction 1.0');
        $this->add([['text' => 'A', 'fraction' => 0.5], ['text' => 'B']]);
    }

    /**
     * A multiple-answer question's positive fractions must add up to 1.
     */
    public function test_execute_multiple_wrong_total(): void {
        $this->login_as('editingteacher');

        $this->expectException(invalid_parameter_exception::class);
        $this->expectExceptionMessage('add up to 1.0');
        $this->add([['text' => 'A', 'fraction' => 0.5], ['text' => 'B', 'fraction' => 0.25]], 0);
    }

    /**
     * Questions cannot be added once the quiz has been attempted.
     */
    public function test_execute_quiz_with_attempts(): void {
        $teacher = $this->login_as('editingteacher');
        $this->add([['text' => 'A', 'fraction' => 1.0], ['text' => 'B']]);
        // A real attempt, not a teacher's preview, locks the quiz.
        $student = $this->login_as('student');
        $this->getDataGenerator()->get_plugin_generator('mod_quiz')->create_attempt($this->quiz->id, $student->id);
        $this->setUser($teacher);

        try {
            $this->add([['text' => 'A', 'fraction' => 1.0], ['text' => 'B']]);
            $this->fail('Expected a moodle_exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('cannoteditafterattempts', $e->errorcode);
        }
    }

    /**
     * Adding questions needs moodle/question:add on the quiz.
     */
    public function test_execute_requires_question_add(): void {
        global $DB;
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        assign_capability('moodle/question:add', CAP_PROHIBIT, $roleid, \context_module::instance($this->quiz->cmid));
        $this->login_as('editingteacher');

        $this->expectException(\required_capability_exception::class);
        $this->add([['text' => 'A', 'fraction' => 1.0], ['text' => 'B']]);
    }

    /**
     * A student cannot add questions.
     */
    public function test_execute_requires_capability(): void {
        $this->login_as('student');

        $this->expectException(\required_capability_exception::class);
        $this->add([['text' => 'A', 'fraction' => 1.0], ['text' => 'B']]);
    }
}
