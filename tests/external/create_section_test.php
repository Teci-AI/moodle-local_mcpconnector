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
 * Tests for local_mcpconnector_create_section.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(create_section::class)]
final class create_section_test extends \advanced_testcase {
    /** @var \stdClass */
    private \stdClass $course;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course(['numsections' => 2, 'format' => 'topics']);
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
        return external_api::clean_returnvalue(create_section::execute_returns(), create_section::execute(...$args));
    }

    /**
     * Without a position the section is added at the end, with its name and summary.
     */
    public function test_execute_append(): void {
        global $DB;
        $this->login_as('editingteacher');

        $result = $this->call(['courseid' => $this->course->id, 'name' => 'Week 3', 'summary' => '<p>Third</p>']);

        $this->assertSame(3, $result['sectionnum']);
        $section = $DB->get_record('course_sections', ['id' => $result['sectionid']], '*', MUST_EXIST);
        $this->assertEquals($this->course->id, $section->course);
        $this->assertEquals(3, $section->section);
        $this->assertSame('Week 3', $section->name);
        $this->assertSame('<p>Third</p>', $section->summary);
        $this->assertEquals(FORMAT_HTML, $section->summaryformat);
    }

    /**
     * With a position the section takes that number and later sections move down.
     */
    public function test_execute_position(): void {
        global $DB;
        $this->login_as('editingteacher');
        $oldsection1 = $DB->get_field('course_sections', 'id', ['course' => $this->course->id, 'section' => 1]);

        $result = $this->call(['courseid' => $this->course->id, 'name' => 'Intro', 'position' => 1]);

        $this->assertSame(1, $result['sectionnum']);
        $this->assertEquals(2, $DB->get_field('course_sections', 'section', ['id' => $oldsection1]));
        $this->assertSame('Intro', get_fast_modinfo($this->course)->get_section_info(1)->name);
    }

    /**
     * Inserting a section before others needs moodle/course:movesections, as on the course page.
     */
    public function test_execute_position_requires_movesections(): void {
        global $DB;
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        assign_capability('moodle/course:movesections', CAP_PROHIBIT, $roleid, \context_course::instance($this->course->id));
        $this->login_as('editingteacher');

        $this->assertSame(3, $this->call(['courseid' => $this->course->id, 'name' => 'End'])['sectionnum']);

        $this->expectException(\required_capability_exception::class);
        $this->call(['courseid' => $this->course->id, 'name' => 'Intro', 'position' => 1]);
    }

    /**
     * A non-editing teacher cannot create sections.
     */
    public function test_execute_requires_capability(): void {
        $this->login_as('teacher');

        $this->expectException(\required_capability_exception::class);
        $this->call(['courseid' => $this->course->id, 'name' => 'Week 3']);
    }
}
