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

namespace local_mcpconnector;

use core_external\external_api;

/**
 * Shared setup for the add_*_question tests: a course with a quiz, and helpers to check the result.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class question_testcase extends \advanced_testcase {
    /** @var \stdClass */
    protected \stdClass $course;

    /** @var \stdClass the quiz, as the generator returns it (with cmid) */
    protected \stdClass $quiz;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
        $this->quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $this->course->id]);
    }

    /**
     * Enrols a new user in the test course with a role and logs in as them.
     *
     * @param string $role role shortname
     * @return \stdClass the user
     */
    protected function login_as(string $role): \stdClass {
        $user = $this->getDataGenerator()->create_and_enrol($this->course, $role);
        $this->setUser($user);
        return $user;
    }

    /**
     * Calls a function class's execute() and cleans the result as the web service layer does.
     *
     * @param string $classname e.g. add_truefalse_question::class
     * @param array $args named arguments
     * @return array
     */
    protected function call(string $classname, array $args): array {
        return external_api::clean_returnvalue($classname::execute_returns(), $classname::execute(...$args));
    }

    /**
     * Checks the question exists in the quiz's own question bank, at the given slot, and that the quiz grade total includes it.
     *
     * @param array $result the function's return value
     * @param int $slot expected slot
     * @param string $qtype expected question type
     * @return \stdClass the question record
     */
    protected function assert_question_in_quiz(array $result, int $slot, string $qtype): \stdClass {
        global $DB;

        $this->assertSame($slot, $result['slot']);
        $question = $DB->get_record('question', ['id' => $result['questionid']], '*', MUST_EXIST);
        $this->assertSame($qtype, $question->qtype);

        $categorycontextid = $DB->get_field_sql(
            "SELECT qc.contextid
               FROM {question_versions} qv
               JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
               JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
              WHERE qv.questionid = ?",
            [$question->id]
        );
        $this->assertEquals(\context_module::instance($this->quiz->cmid)->id, $categorycontextid);

        $structure = \mod_quiz\quiz_settings::create_for_cmid($this->quiz->cmid)->get_structure();
        $this->assertEquals($question->id, $structure->get_question_in_slot($slot)->questionid);

        $sumgrades = $DB->get_field('quiz', 'sumgrades', ['id' => $this->quiz->id]);
        $maxmarks = $DB->get_field('quiz_slots', 'SUM(maxmark)', ['quizid' => $this->quiz->id]);
        $this->assertEqualsWithDelta((float) $maxmarks, (float) $sumgrades, 0.0000001);

        return $question;
    }
}
