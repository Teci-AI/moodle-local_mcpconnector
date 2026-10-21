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
 * Tests for local_mcpconnector_create_page.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(create_page::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_mcpconnector\local\course_helper::class)]
final class create_page_test extends \advanced_testcase {
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
        $user = $this->getDataGenerator()->create_and_enrol($this->course, $role);
        $this->setUser($user);
    }

    /**
     * Calls the function and cleans its return value as the web service layer does.
     *
     * @param array $args
     * @return array
     */
    private function call(array $args): array {
        return external_api::clean_returnvalue(create_page::execute_returns(), create_page::execute(...$args));
    }

    /**
     * A teacher creates a page in a given section.
     */
    public function test_execute(): void {
        global $DB;
        $this->login_as('editingteacher');
        $sink = $this->redirectEvents();

        $result = $this->call([
            'courseid' => $this->course->id,
            'section' => 2,
            'name' => 'Welcome',
            'content' => '<p>Hello</p>',
            'intro' => '<p>About</p>',
        ]);

        $page = $DB->get_record('page', ['id' => $result['instanceid']], '*', MUST_EXIST);
        $this->assertSame('Welcome', $page->name);
        $this->assertSame('<p>Hello</p>', $page->content);
        $this->assertEquals(FORMAT_HTML, $page->contentformat);
        $this->assertSame('<p>About</p>', $page->intro);

        $cm = get_fast_modinfo($this->course)->get_cm($result['cmid']);
        $this->assertSame('page', $cm->modname);
        $this->assertEquals($page->id, $cm->instance);
        $this->assertEquals(2, $cm->sectionnum);
        $this->assertEquals(1, $cm->visible);

        $events = array_filter($sink->get_events(), fn($e) => $e instanceof \core\event\course_module_created);
        $this->assertCount(1, $events);
    }

    /**
     * Optional parameters default to section 0, no description and visible.
     */
    public function test_execute_defaults(): void {
        $this->login_as('editingteacher');

        $result = $this->call(['courseid' => $this->course->id, 'section' => 0, 'name' => 'P', 'content' => 'C']);

        $cm = get_fast_modinfo($this->course)->get_cm($result['cmid']);
        $this->assertEquals(0, $cm->sectionnum);
        $this->assertEquals(1, $cm->visible);
    }

    /**
     * A page can be created hidden.
     */
    public function test_execute_hidden(): void {
        $this->login_as('editingteacher');

        $result = $this->call([
            'courseid' => $this->course->id, 'section' => 1, 'name' => 'P', 'content' => 'C', 'visible' => 0,
        ]);

        $this->assertEquals(0, get_fast_modinfo($this->course)->get_cm($result['cmid'])->visible);
    }

    /**
     * A student cannot create pages.
     */
    public function test_execute_requires_capability(): void {
        $this->login_as('student');

        $this->expectException(\required_capability_exception::class);
        $this->call(['courseid' => $this->course->id, 'section' => 0, 'name' => 'P', 'content' => 'C']);
    }

    /**
     * A section that does not exist is rejected.
     */
    public function test_execute_missing_section(): void {
        $this->login_as('editingteacher');

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('sectionnotfound', 'local_mcpconnector', 9));
        $this->call(['courseid' => $this->course->id, 'section' => 9, 'name' => 'P', 'content' => 'C']);
    }

    /**
     * An empty name is rejected.
     */
    public function test_execute_empty_name(): void {
        $this->login_as('editingteacher');

        $this->expectException(invalid_parameter_exception::class);
        $this->call(['courseid' => $this->course->id, 'section' => 0, 'name' => '  ', 'content' => 'C']);
    }
}
