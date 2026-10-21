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
 * Tests for local_mcpconnector_delete_activity.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(delete_activity::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_mcpconnector\local\course_helper::class)]
final class delete_activity_test extends \advanced_testcase {
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
     * The activity and its instance are deleted.
     */
    public function test_execute(): void {
        global $DB;
        $page = $this->getDataGenerator()->create_module('page', ['course' => $this->course->id]);
        $this->login_as('editingteacher');
        $sink = $this->redirectEvents();

        $result = external_api::clean_returnvalue(delete_activity::execute_returns(), delete_activity::execute($page->cmid));

        $this->assertSame(['cmid' => (int) $page->cmid, 'success' => true], $result);
        $this->assertFalse($DB->record_exists('course_modules', ['id' => $page->cmid]));
        $this->assertFalse($DB->record_exists('page', ['id' => $page->id]));
        $events = array_filter($sink->get_events(), fn($e) => $e instanceof \core\event\course_module_deleted);
        $this->assertCount(1, $events);
    }

    /**
     * A non-editing teacher cannot delete activities.
     */
    public function test_execute_requires_capability(): void {
        $page = $this->getDataGenerator()->create_module('page', ['course' => $this->course->id]);
        $this->login_as('teacher');

        $this->expectException(\required_capability_exception::class);
        delete_activity::execute($page->cmid);
    }
}
