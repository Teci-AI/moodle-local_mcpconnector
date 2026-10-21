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
 * Tests for local_mcpconnector_move_activity.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(move_activity::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_mcpconnector\local\course_helper::class)]
final class move_activity_test extends \advanced_testcase {
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
     * @param int $cmid
     * @param int $section
     * @return array
     */
    private function call(int $cmid, int $section): array {
        return external_api::clean_returnvalue(move_activity::execute_returns(), move_activity::execute($cmid, $section));
    }

    /**
     * The activity moves to the end of the target section.
     */
    public function test_execute(): void {
        $generator = $this->getDataGenerator();
        $page = $generator->create_module('page', ['course' => $this->course->id, 'section' => 0]);
        $existing = $generator->create_module('label', ['course' => $this->course->id, 'section' => 2]);
        $this->login_as('editingteacher');

        $this->assertSame(['cmid' => (int) $page->cmid, 'section' => 2], $this->call($page->cmid, 2));

        $modinfo = get_fast_modinfo($this->course);
        $this->assertEquals([$existing->cmid, $page->cmid], $modinfo->sections[2]);
        $this->assertArrayNotHasKey(0, $modinfo->sections);
    }

    /**
     * A section that does not exist is rejected.
     */
    public function test_execute_missing_section(): void {
        $page = $this->getDataGenerator()->create_module('page', ['course' => $this->course->id]);
        $this->login_as('editingteacher');

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('sectionnotfound', 'local_mcpconnector', 5));
        $this->call($page->cmid, 5);
    }

    /**
     * A non-editing teacher cannot move activities.
     */
    public function test_execute_requires_capability(): void {
        $page = $this->getDataGenerator()->create_module('page', ['course' => $this->course->id]);
        $this->login_as('teacher');

        $this->expectException(\required_capability_exception::class);
        $this->call($page->cmid, 1);
    }
}
