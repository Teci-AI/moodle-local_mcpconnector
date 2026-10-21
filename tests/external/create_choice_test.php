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

/**
 * Tests for local_mcpconnector_create_choice.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(create_choice::class)]
final class create_choice_test extends \advanced_testcase {
    /** @var \stdClass */
    private \stdClass $course;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
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
        return external_api::clean_returnvalue(create_choice::execute_returns(), create_choice::execute(...$args));
    }

    /**
     * The question becomes the description and each option is saved.
     */
    public function test_execute(): void {
        global $DB;
        $this->login_as('editingteacher');

        $result = $this->call([
            'courseid' => $this->course->id,
            'section' => 0,
            'name' => 'Lunch',
            'question' => '<p>What should we eat?</p>',
            'options' => ['Pizza', 'Salad', 'Soup'],
            'allowupdate' => 0,
        ]);

        $choice = $DB->get_record('choice', ['id' => $result['instanceid']], '*', MUST_EXIST);
        $this->assertSame('Lunch', $choice->name);
        $this->assertSame('<p>What should we eat?</p>', $choice->intro);
        $this->assertEquals(0, $choice->allowupdate);
        $options = $DB->get_fieldset_select('choice_options', 'text', 'choiceid = ? ORDER BY id', [$choice->id]);
        $this->assertSame(['Pizza', 'Salad', 'Soup'], $options);
    }

    /**
     * Fewer than two options are rejected.
     */
    public function test_execute_one_option(): void {
        $this->login_as('editingteacher');

        $this->expectException(invalid_parameter_exception::class);
        $this->call([
            'courseid' => $this->course->id, 'section' => 0, 'name' => 'Lunch', 'question' => 'Eat?', 'options' => ['Pizza'],
        ]);
    }

    /**
     * A student cannot create choices.
     */
    public function test_execute_requires_capability(): void {
        $this->login_as('student');

        $this->expectException(\required_capability_exception::class);
        $this->call([
            'courseid' => $this->course->id, 'section' => 0, 'name' => 'Lunch', 'question' => 'Eat?', 'options' => ['A', 'B'],
        ]);
    }
}
