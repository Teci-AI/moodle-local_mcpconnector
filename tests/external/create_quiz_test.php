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
use invalid_parameter_exception;
use mod_quiz\question\display_options;

/**
 * Tests for local_mcpconnector_create_quiz.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(create_quiz::class)]
final class create_quiz_test extends \advanced_testcase {
    /** @var \stdClass */
    private \stdClass $course;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course(['numsections' => 2]);
    }

    /**
     * Enrols a new user in the test course with a role and logs in as them.
     *
     * @param string $role role shortname
     */
    private function login_as(string $role): void {
        $this->setUser($this->getDataGenerator()->create_and_enrol($this->course, $role));
    }

    /**
     * Calls the function and cleans its return value as the web service layer does.
     *
     * @param array $args
     * @return array
     */
    private function call(array $args): array {
        return external_api::clean_returnvalue(create_quiz::execute_returns(), create_quiz::execute(...$args));
    }

    /**
     * A teacher creates a quiz with the given settings; the rest come from the site's quiz defaults.
     */
    public function test_execute(): void {
        global $DB;
        $this->login_as('editingteacher');
        set_config('navmethod', 'sequential', 'quiz');

        $result = $this->call([
            'courseid' => $this->course->id,
            'section' => 1,
            'name' => 'Checkpoint',
            'intro' => '<p>Five questions</p>',
            'timeopen' => 1900000000,
            'timeclose' => 1900003600,
            'timelimit' => 1200,
            'grade' => 20,
            'attempts' => 2,
        ]);

        $quiz = $DB->get_record('quiz', ['id' => $result['instanceid']], '*', MUST_EXIST);
        $this->assertSame('Checkpoint', $quiz->name);
        $this->assertSame('<p>Five questions</p>', $quiz->intro);
        $this->assertEquals(1900000000, $quiz->timeopen);
        $this->assertEquals(1900003600, $quiz->timeclose);
        $this->assertEquals(1200, $quiz->timelimit);
        $this->assertEquals(20, $quiz->grade);
        $this->assertEquals(2, $quiz->attempts);
        $this->assertEquals(0, $quiz->sumgrades);
        $this->assertSame('sequential', $quiz->navmethod);
        $this->assertSame(get_config('quiz', 'preferredbehaviour'), $quiz->preferredbehaviour);
        // The settings form always allows reviewing the attempt during it, and never the overall feedback.
        $this->assertEquals(get_config('quiz', 'reviewattempt') | display_options::DURING, $quiz->reviewattempt);
        $this->assertEquals(get_config('quiz', 'reviewmarks'), $quiz->reviewmarks);

        $cm = get_fast_modinfo($this->course)->get_cm($result['cmid']);
        $this->assertSame('quiz', $cm->modname);
        $this->assertEquals(1, $cm->sectionnum);
    }

    /**
     * Only name and course are required.
     */
    public function test_execute_defaults(): void {
        global $DB;
        $this->login_as('editingteacher');

        $result = $this->call(['courseid' => $this->course->id, 'section' => 0, 'name' => 'Quiz']);

        $quiz = $DB->get_record('quiz', ['id' => $result['instanceid']], '*', MUST_EXIST);
        $this->assertEquals(0, $quiz->timeopen);
        $this->assertEquals(0, $quiz->timelimit);
        $this->assertEquals(100, $quiz->grade);
        $this->assertEquals(0, $quiz->attempts);
    }

    /**
     * A closing time before the opening time is rejected, as in the settings form.
     */
    public function test_execute_close_before_open(): void {
        $this->login_as('editingteacher');

        $this->expectException(invalid_parameter_exception::class);
        $this->call([
            'courseid' => $this->course->id, 'section' => 0, 'name' => 'Quiz', 'timeopen' => 2000, 'timeclose' => 1000,
        ]);
    }

    /**
     * A student cannot create quizzes.
     */
    public function test_execute_requires_capability(): void {
        $this->login_as('student');

        $this->expectException(\required_capability_exception::class);
        $this->call(['courseid' => $this->course->id, 'section' => 0, 'name' => 'Quiz']);
    }
}
