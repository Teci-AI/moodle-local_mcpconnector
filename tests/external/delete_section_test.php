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

/**
 * Tests for local_mcpconnector_delete_section.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(delete_section::class)]
final class delete_section_test extends \advanced_testcase {
    /** @var \stdClass */
    private \stdClass $course;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course(['numsections' => 3, 'format' => 'topics']);
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
     * @param int $sectionnum
     * @return array
     */
    private function call(int $sectionnum): array {
        return external_api::clean_returnvalue(
            delete_section::execute_returns(),
            delete_section::execute($this->course->id, $sectionnum)
        );
    }

    /**
     * Deleting a section removes it and its activities, and renumbers later sections.
     */
    public function test_execute(): void {
        global $DB;
        $page = $this->getDataGenerator()->create_module('page', ['course' => $this->course->id, 'section' => 2]);
        $section3 = $DB->get_field('course_sections', 'id', ['course' => $this->course->id, 'section' => 3]);
        $this->login_as('editingteacher');

        $this->assertSame(['success' => true], $this->call(2));

        $this->assertFalse($DB->record_exists('course_modules', ['id' => $page->cmid]));
        $this->assertFalse($DB->record_exists('page', ['id' => $page->id]));
        $this->assertEquals(2, $DB->get_field('course_sections', 'section', ['id' => $section3]));
        $this->assertCount(3, get_fast_modinfo($this->course)->get_section_info_all());
    }

    /**
     * The general section cannot be deleted.
     */
    public function test_execute_section_zero(): void {
        $this->login_as('editingteacher');

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('cannotdeletesectionzero', 'local_mcpconnector'));
        $this->call(0);
    }

    /**
     * A section that does not exist is rejected.
     */
    public function test_execute_missing_section(): void {
        $this->login_as('editingteacher');

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('sectionnotfound', 'local_mcpconnector', 7));
        $this->call(7);
    }

    /**
     * The user must be able to delete every activity in the section.
     */
    public function test_execute_requires_manageactivities_on_each_activity(): void {
        global $DB;
        $page = $this->getDataGenerator()->create_module('page', ['course' => $this->course->id, 'section' => 1]);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        assign_capability('moodle/course:manageactivities', CAP_PROHIBIT, $roleid, \context_module::instance($page->cmid));
        $this->login_as('editingteacher');

        $this->expectException(\required_capability_exception::class);
        $this->call(1);
    }

    /**
     * A non-editing teacher cannot delete sections.
     */
    public function test_execute_requires_capability(): void {
        $this->login_as('teacher');

        $this->expectException(\required_capability_exception::class);
        $this->call(1);
    }
}
