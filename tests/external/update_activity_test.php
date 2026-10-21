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
 * Tests for local_mcpconnector_update_activity.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(update_activity::class)]
final class update_activity_test extends \advanced_testcase {
    /** @var \stdClass */
    private \stdClass $course;

    /** @var \stdClass the activity under test */
    private \stdClass $page;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
        $this->page = $this->getDataGenerator()->create_module('page', ['course' => $this->course->id, 'name' => 'Old']);
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
        return external_api::clean_returnvalue(update_activity::execute_returns(), update_activity::execute(...$args));
    }

    /**
     * Renaming and hiding in one call reports both changes.
     */
    public function test_execute_rename_and_hide(): void {
        global $DB;
        $this->login_as('editingteacher');
        $sink = $this->redirectEvents();

        $result = $this->call(['cmid' => $this->page->cmid, 'name' => 'New', 'visible' => 0]);

        $this->assertSame(
            ['cmid' => (int) $this->page->cmid, 'name' => 'New', 'visible' => 0, 'changed' => ['name', 'visible']],
            $result
        );
        $this->assertSame('New', $DB->get_field('page', 'name', ['id' => $this->page->id]));
        $this->assertEquals(0, $DB->get_field('course_modules', 'visible', ['id' => $this->page->cmid]));

        $events = array_filter($sink->get_events(), fn($e) => $e instanceof \core\event\course_module_updated);
        $this->assertCount(2, $events);
    }

    /**
     * The defaults change nothing.
     */
    public function test_execute_no_change(): void {
        $this->login_as('editingteacher');

        $result = $this->call(['cmid' => $this->page->cmid]);

        $this->assertSame([], $result['changed']);
        $this->assertSame('Old', $result['name']);
        $this->assertSame(1, $result['visible']);
    }

    /**
     * Only 1, 0 and -1 are valid visibility values.
     */
    public function test_execute_invalid_visible(): void {
        $this->login_as('editingteacher');

        $this->expectException(invalid_parameter_exception::class);
        $this->call(['cmid' => $this->page->cmid, 'visible' => 2]);
    }

    /**
     * Changing visibility needs moodle/course:activityvisibility.
     */
    public function test_execute_visibility_requires_capability(): void {
        global $DB;
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        assign_capability('moodle/course:activityvisibility', CAP_PROHIBIT, $roleid, \context_course::instance($this->course->id));
        $this->login_as('editingteacher');

        $this->assertSame(['name'], $this->call(['cmid' => $this->page->cmid, 'name' => 'New'])['changed']);

        $this->expectException(\required_capability_exception::class);
        $this->call(['cmid' => $this->page->cmid, 'visible' => 0]);
    }

    /**
     * A non-editing teacher cannot edit activities.
     */
    public function test_execute_requires_capability(): void {
        $this->login_as('teacher');

        $this->expectException(\required_capability_exception::class);
        $this->call(['cmid' => $this->page->cmid, 'name' => 'New']);
    }
}
