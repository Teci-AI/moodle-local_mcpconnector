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
 * Tests for local_mcpconnector_update_page.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(update_page::class)]
final class update_page_test extends \advanced_testcase {
    /** @var \stdClass */
    private \stdClass $course;

    /** @var \stdClass the page under test */
    private \stdClass $page;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
        $this->page = $this->getDataGenerator()->create_module('page', [
            'course' => $this->course->id,
            'content' => '<p>Old</p>',
            'intro' => '<p>Old intro</p>',
            'printintro' => 1,
            'printlastmodified' => 0,
        ]);
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
        return external_api::clean_returnvalue(update_page::execute_returns(), update_page::execute(...$args));
    }

    /**
     * New content replaces the old; an empty intro keeps the description and display options.
     */
    public function test_execute_keeps_intro(): void {
        global $DB;
        $this->login_as('editingteacher');
        $sink = $this->redirectEvents();

        $result = $this->call(['cmid' => $this->page->cmid, 'content' => '<p>New</p>']);

        $this->assertSame(['cmid' => (int) $this->page->cmid, 'success' => true], $result);
        $page = $DB->get_record('page', ['id' => $this->page->id], '*', MUST_EXIST);
        $this->assertSame('<p>New</p>', $page->content);
        $this->assertEquals(FORMAT_HTML, $page->contentformat);
        $this->assertSame('<p>Old intro</p>', $page->intro);
        $this->assertEquals($this->page->revision + 1, $page->revision);
        $options = unserialize_array($page->displayoptions);
        $this->assertEquals(1, $options['printintro']);
        $this->assertEquals(0, $options['printlastmodified']);

        $events = array_filter($sink->get_events(), fn($e) => $e instanceof \core\event\course_module_updated);
        $this->assertCount(1, $events);
    }

    /**
     * A non-empty intro replaces the description.
     */
    public function test_execute_replaces_intro(): void {
        global $DB;
        $this->login_as('editingteacher');

        $this->call(['cmid' => $this->page->cmid, 'content' => '<p>New</p>', 'intro' => '<p>New intro</p>']);

        $this->assertSame('<p>New intro</p>', $DB->get_field('page', 'intro', ['id' => $this->page->id]));
    }

    /**
     * Only Page activities can be updated.
     */
    public function test_execute_not_a_page(): void {
        $label = $this->getDataGenerator()->create_module('label', ['course' => $this->course->id]);
        $this->login_as('editingteacher');

        $this->expectException(\moodle_exception::class);
        $this->call(['cmid' => $label->cmid, 'content' => 'X']);
    }

    /**
     * A non-editing teacher cannot update pages.
     */
    public function test_execute_requires_capability(): void {
        $this->login_as('teacher');

        $this->expectException(\required_capability_exception::class);
        $this->call(['cmid' => $this->page->cmid, 'content' => 'X']);
    }
}
