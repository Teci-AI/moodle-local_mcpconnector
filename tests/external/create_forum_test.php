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
 * Tests for local_mcpconnector_create_forum.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(create_forum::class)]
final class create_forum_test extends \advanced_testcase {
    /** @var \stdClass */
    private \stdClass $course;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course(['numsections' => 1]);
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
        return external_api::clean_returnvalue(create_forum::execute_returns(), create_forum::execute(...$args));
    }

    /**
     * A teacher creates a general forum with the site's default settings.
     */
    public function test_execute(): void {
        global $CFG, $DB;
        $this->login_as('editingteacher');

        $result = $this->call([
            'courseid' => $this->course->id, 'section' => 1, 'name' => 'Q&A', 'intro' => '<p>Ask here</p>',
        ]);

        $forum = $DB->get_record('forum', ['id' => $result['instanceid']], '*', MUST_EXIST);
        $this->assertSame('Q&A', $forum->name);
        $this->assertSame('general', $forum->type);
        $this->assertSame('<p>Ask here</p>', $forum->intro);
        $this->assertEquals($CFG->forum_maxattachments, $forum->maxattachments);
        $this->assertEquals(1, get_fast_modinfo($this->course)->get_cm($result['cmid'])->sectionnum);
    }

    /**
     * A single simple discussion forum gets its discussion, with the description as the first post.
     */
    public function test_execute_single(): void {
        global $DB;
        $this->login_as('editingteacher');

        $result = $this->call([
            'courseid' => $this->course->id, 'section' => 0, 'name' => 'Debate', 'intro' => '<p>Discuss</p>', 'type' => 'single',
        ]);

        $discussion = $DB->get_record('forum_discussions', ['forum' => $result['instanceid']], '*', MUST_EXIST);
        $this->assertSame('<p>Discuss</p>', $DB->get_field('forum_posts', 'message', ['id' => $discussion->firstpost]));
    }

    /**
     * Only the forum types of the settings form are accepted.
     */
    public function test_execute_invalid_type(): void {
        $this->login_as('editingteacher');

        $this->expectException(invalid_parameter_exception::class);
        $this->call(['courseid' => $this->course->id, 'section' => 0, 'name' => 'F', 'type' => 'news']);
    }

    /**
     * A student cannot create forums.
     */
    public function test_execute_requires_capability(): void {
        $this->login_as('student');

        $this->expectException(\required_capability_exception::class);
        $this->call(['courseid' => $this->course->id, 'section' => 0, 'name' => 'F']);
    }
}
