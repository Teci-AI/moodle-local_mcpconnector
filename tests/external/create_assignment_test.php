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
 * Tests for local_mcpconnector_create_assignment.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(create_assignment::class)]
final class create_assignment_test extends \advanced_testcase {
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
        return external_api::clean_returnvalue(create_assignment::execute_returns(), create_assignment::execute(...$args));
    }

    /**
     * The assignment accepts online text and files, has feedback comments and a grade item.
     */
    public function test_execute(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        require_once($CFG->libdir . '/gradelib.php');
        $this->login_as('editingteacher');

        $result = $this->call([
            'courseid' => $this->course->id,
            'section' => 0,
            'name' => 'Report',
            'intro' => '<p>Write a report</p>',
            'duedate' => 1900000000,
            'grade' => 50,
        ]);

        $assign = $DB->get_record('assign', ['id' => $result['instanceid']], '*', MUST_EXIST);
        $this->assertSame('Report', $assign->name);
        $this->assertEquals(1900000000, $assign->duedate);
        $this->assertEquals(50, $assign->grade);

        $cm = get_fast_modinfo($this->course)->get_cm($result['cmid']);
        $assignment = new \assign($cm->context, $cm, $this->course);
        $this->assertEquals(1, $assignment->get_submission_plugin_by_type('onlinetext')->is_enabled());
        $this->assertEquals(1, $assignment->get_submission_plugin_by_type('file')->is_enabled());
        $this->assertEquals(1, $assignment->get_feedback_plugin_by_type('comments')->is_enabled());

        $gradeitem = \grade_item::fetch(['itemtype' => 'mod', 'itemmodule' => 'assign', 'iteminstance' => $assign->id]);
        $this->assertEquals(50, $gradeitem->grademax);
    }

    /**
     * A grade above the site's maximum is rejected.
     */
    public function test_execute_grade_too_high(): void {
        $this->login_as('editingteacher');

        $this->expectException(invalid_parameter_exception::class);
        $this->call(['courseid' => $this->course->id, 'section' => 0, 'name' => 'Report', 'grade' => 1000]);
    }

    /**
     * A student cannot create assignments.
     */
    public function test_execute_requires_capability(): void {
        $this->login_as('student');

        $this->expectException(\required_capability_exception::class);
        $this->call(['courseid' => $this->course->id, 'section' => 0, 'name' => 'Report']);
    }
}
