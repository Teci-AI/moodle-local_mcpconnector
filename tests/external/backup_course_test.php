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
 * Tests for local_mcpconnector_backup_course.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(backup_course::class)]
final class backup_course_test extends \advanced_testcase {
    /** @var \stdClass */
    private \stdClass $course;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course(['fullname' => 'Biology 101']);
        $this->getDataGenerator()->create_module('page', ['course' => $this->course->id, 'name' => 'Welcome']);
    }

    /**
     * A teacher gets the course's .mbz file, and no copy is left in their backup area.
     */
    public function test_execute(): void {
        $user = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->setUser($user);

        $result = external_api::clean_returnvalue(
            backup_course::execute_returns(),
            backup_course::execute($this->course->id)
        );

        $this->assertStringStartsWith('backup-moodle2-course-' . $this->course->id . '-', $result['filename']);
        $this->assertStringEndsWith('-nu.mbz', $result['filename']);
        $content = base64_decode($result['content_base64'], true);
        $this->assertSame($result['size'], strlen($content));
        $this->assertGreaterThan(0, $result['size']);

        $fs = get_file_storage();
        $this->assertTrue($fs->is_area_empty(\context_user::instance($user->id)->id, 'user', 'backup'));
    }

    /**
     * Including user data needs moodle/backup:userinfo, which teachers do not have by default.
     */
    public function test_execute_users_requires_capability(): void {
        $this->setUser($this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher'));

        $this->expectException(\required_capability_exception::class);
        backup_course::execute($this->course->id, 1);
    }

    /**
     * A student cannot back up the course.
     */
    public function test_execute_requires_capability(): void {
        $this->setUser($this->getDataGenerator()->create_and_enrol($this->course, 'student'));

        $this->expectException(\required_capability_exception::class);
        backup_course::execute($this->course->id);
    }
}
