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
 * Tests for local_mcpconnector_create_url.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(create_url::class)]
final class create_url_test extends \advanced_testcase {
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
     * @param array $args
     * @return array
     */
    private function call(array $args): array {
        return external_api::clean_returnvalue(create_url::execute_returns(), create_url::execute(...$args));
    }

    /**
     * A teacher creates a URL resource.
     */
    public function test_execute(): void {
        global $DB;
        $this->login_as('editingteacher');

        $result = $this->call([
            'courseid' => $this->course->id,
            'section' => 2,
            'name' => 'Syllabus',
            'externalurl' => 'https://example.com/syllabus',
            'intro' => '<p>Read it</p>',
            'display' => RESOURCELIB_DISPLAY_OPEN,
        ]);

        $url = $DB->get_record('url', ['id' => $result['instanceid']], '*', MUST_EXIST);
        $this->assertSame('Syllabus', $url->name);
        $this->assertSame('https://example.com/syllabus', $url->externalurl);
        $this->assertEquals(RESOURCELIB_DISPLAY_OPEN, $url->display);
        $this->assertSame('<p>Read it</p>', $url->intro);

        $cm = get_fast_modinfo($this->course)->get_cm($result['cmid']);
        $this->assertSame('url', $cm->modname);
        $this->assertEquals(2, $cm->sectionnum);
    }

    /**
     * A URL without a scheme gets http:// added, as in the settings form.
     */
    public function test_execute_adds_scheme(): void {
        global $DB;
        $this->login_as('editingteacher');

        $result = $this->call([
            'courseid' => $this->course->id, 'section' => 0, 'name' => 'Site', 'externalurl' => 'example.com',
        ]);

        $this->assertSame('http://example.com', $DB->get_field('url', 'externalurl', ['id' => $result['instanceid']]));
    }

    /**
     * A malformed URL is rejected.
     */
    public function test_execute_invalid_url(): void {
        $this->login_as('editingteacher');

        $this->expectException(invalid_parameter_exception::class);
        $this->call(['courseid' => $this->course->id, 'section' => 0, 'name' => 'Bad', 'externalurl' => 'https://exa mple']);
    }

    /**
     * A display mode disabled in the URL module settings is rejected.
     */
    public function test_execute_disabled_display(): void {
        $this->login_as('editingteacher');
        set_config('displayoptions', RESOURCELIB_DISPLAY_AUTO, 'url');

        $this->expectException(invalid_parameter_exception::class);
        $this->call([
            'courseid' => $this->course->id, 'section' => 0, 'name' => 'U', 'externalurl' => 'https://example.com',
            'display' => RESOURCELIB_DISPLAY_POPUP,
        ]);
    }

    /**
     * A student cannot create URL resources.
     */
    public function test_execute_requires_capability(): void {
        $this->login_as('student');

        $this->expectException(\required_capability_exception::class);
        $this->call(['courseid' => $this->course->id, 'section' => 0, 'name' => 'U', 'externalurl' => 'https://example.com']);
    }
}
