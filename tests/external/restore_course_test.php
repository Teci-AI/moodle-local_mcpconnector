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
 * Tests for local_mcpconnector_restore_course.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(restore_course::class)]
final class restore_course_test extends \advanced_testcase {
    /** @var \stdClass the course that is backed up */
    private \stdClass $course;

    /** @var \core_course_category the category restored into */
    private \core_course_category $category;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course(['fullname' => 'Biology 101', 'shortname' => 'bio101']);
        $this->getDataGenerator()->create_module('page', ['course' => $this->course->id, 'name' => 'Welcome']);
        $this->category = $this->getDataGenerator()->create_category();
    }

    /**
     * Backs the test course up as the admin and returns the base64 content.
     *
     * @return string
     */
    private function backup(): string {
        $this->setAdminUser();
        return backup_course::execute($this->course->id)['content_base64'];
    }

    /**
     * Logs in as a manager of the target category.
     */
    private function login_as_category_manager(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'manager']);
        role_assign($roleid, $user->id, $this->category->get_context());
        $this->setUser($user);
    }

    /**
     * The backup is restored as a new course with the given names.
     */
    public function test_execute(): void {
        $content = $this->backup();
        $this->login_as_category_manager();

        $result = external_api::clean_returnvalue(
            restore_course::execute_returns(),
            restore_course::execute($content, $this->category->id, 'Biology 102', 'bio102')
        );

        $this->assertNotEquals($this->course->id, $result['courseid']);
        $this->assertSame('Biology 102', $result['fullname']);
        $this->assertSame('bio102', $result['shortname']);
        $course = get_course($result['courseid']);
        $this->assertEquals($this->category->id, $course->category);
        $names = array_map(fn($cm) => $cm->name, get_fast_modinfo($course)->get_instances_of('page'));
        $this->assertSame(['Welcome'], array_values($names));
    }

    /**
     * Without names, the restored course gets unique ones derived from the backup.
     */
    public function test_execute_keeps_names(): void {
        $content = $this->backup();

        $result = restore_course::execute($content, $this->category->id);

        $this->assertStringStartsWith('Biology 101', $result['fullname']);
        $this->assertNotSame('bio101', $result['shortname']);
    }

    /**
     * Names already in use are made unique, as on the restore page, and the result reports them.
     */
    public function test_execute_names_taken(): void {
        $content = $this->backup();

        $result = restore_course::execute($content, $this->category->id, 'Biology 101', 'bio101');

        $this->assertSame('Biology 101 copy 1', $result['fullname']);
        $this->assertSame('bio101_1', $result['shortname']);
    }

    /**
     * Content that is not a backup is rejected, and no course is left behind.
     */
    public function test_execute_invalid_backup(): void {
        global $DB;
        $this->setAdminUser();
        $courses = $DB->count_records('course');

        try {
            restore_course::execute(base64_encode('not a backup'), $this->category->id);
            $this->fail('Expected a moodle_exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('invalidbackupfile', $e->errorcode);
        }
        // Moodle's zip packer reports the bad archive through debugging().
        $this->assertDebuggingCalled();
        $this->assertSame($courses, $DB->count_records('course'));
    }

    /**
     * Content that is not base64 is rejected.
     */
    public function test_execute_invalid_base64(): void {
        $this->setAdminUser();

        $this->expectException(invalid_parameter_exception::class);
        restore_course::execute('***', $this->category->id);
    }

    /**
     * A teacher cannot create courses, so cannot restore into a new one.
     */
    public function test_execute_requires_capability(): void {
        $content = $this->backup();
        $this->setUser($this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher'));

        $this->expectException(\required_capability_exception::class);
        restore_course::execute($content, $this->category->id);
    }
}
