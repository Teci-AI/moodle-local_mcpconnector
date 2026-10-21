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
 * Tests for local_mcpconnector_create_label.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(create_label::class)]
final class create_label_test extends \advanced_testcase {
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
        return external_api::clean_returnvalue(create_label::execute_returns(), create_label::execute(...$args));
    }

    /**
     * Without a name, the label is named after its text, as in the course editor.
     */
    public function test_execute_generated_name(): void {
        global $DB;
        $this->login_as('editingteacher');

        $result = $this->call(['courseid' => $this->course->id, 'section' => 1, 'content' => '<b>Read this first</b>']);

        $label = $DB->get_record('label', ['id' => $result['instanceid']], '*', MUST_EXIST);
        $this->assertSame('<b>Read this first</b>', $label->intro);
        $this->assertEquals(FORMAT_HTML, $label->introformat);
        $this->assertSame('Read this first', $label->name);

        $cm = get_fast_modinfo($this->course)->get_cm($result['cmid']);
        $this->assertSame('label', $cm->modname);
        $this->assertEquals(1, $cm->sectionnum);
    }

    /**
     * An explicit name is kept, and the label can be created hidden.
     */
    public function test_execute_named_hidden(): void {
        global $DB;
        $this->login_as('editingteacher');

        $result = $this->call([
            'courseid' => $this->course->id, 'section' => 0, 'content' => 'Text', 'name' => 'Intro', 'visible' => 0,
        ]);

        $this->assertSame('Intro', $DB->get_field('label', 'name', ['id' => $result['instanceid']]));
        $this->assertEquals(0, get_fast_modinfo($this->course)->get_cm($result['cmid'])->visible);
    }

    /**
     * A student cannot create labels.
     */
    public function test_execute_requires_capability(): void {
        $this->login_as('student');

        $this->expectException(\required_capability_exception::class);
        $this->call(['courseid' => $this->course->id, 'section' => 0, 'content' => 'Text']);
    }

    /**
     * Empty content is rejected.
     */
    public function test_execute_empty_content(): void {
        $this->login_as('editingteacher');

        $this->expectException(invalid_parameter_exception::class);
        $this->call(['courseid' => $this->course->id, 'section' => 0, 'content' => '']);
    }
}
