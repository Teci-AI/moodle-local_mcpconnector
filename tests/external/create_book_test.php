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
 * Tests for local_mcpconnector_create_book.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(create_book::class)]
final class create_book_test extends \advanced_testcase {
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
     * @return \stdClass the user
     */
    private function login_as(string $role): \stdClass {
        $user = $this->getDataGenerator()->create_and_enrol($this->course, $role);
        $this->setUser($user);
        return $user;
    }

    /**
     * Calls the function with test values and cleans its return value as the web service layer does.
     *
     * @return array
     */
    private function call(): array {
        $result = create_book::execute(
            courseid: $this->course->id,
            section: 1,
            name: 'Handbook',
            intro: '<p>About</p>',
            chaptertitle: 'Getting started',
            chaptercontent: '<p>Chapter one</p>',
        );
        return external_api::clean_returnvalue(create_book::execute_returns(), $result);
    }

    /**
     * A teacher creates a book with its first chapter.
     */
    public function test_execute(): void {
        global $DB;
        $this->login_as('editingteacher');
        $sink = $this->redirectEvents();

        $result = $this->call();

        $book = $DB->get_record('book', ['id' => $result['instanceid']], '*', MUST_EXIST);
        $this->assertSame('Handbook', $book->name);
        $this->assertSame('<p>About</p>', $book->intro);
        $this->assertEquals(1, $book->revision);

        $chapter = $DB->get_record('book_chapters', ['id' => $result['chapterid']], '*', MUST_EXIST);
        $this->assertEquals($book->id, $chapter->bookid);
        $this->assertSame('Getting started', $chapter->title);
        $this->assertSame('<p>Chapter one</p>', $chapter->content);
        $this->assertEquals(FORMAT_HTML, $chapter->contentformat);
        $this->assertEquals(1, $chapter->pagenum);

        $cm = get_fast_modinfo($this->course)->get_cm($result['cmid']);
        $this->assertSame('book', $cm->modname);
        $this->assertEquals(1, $cm->sectionnum);

        $events = array_filter($sink->get_events(), fn($e) => $e instanceof \mod_book\event\chapter_created);
        $this->assertCount(1, $events);
        $this->assertEquals($chapter->id, reset($events)->objectid);
    }

    /**
     * Without mod/book:edit nothing is created, so no empty book is left behind.
     */
    public function test_execute_requires_book_edit(): void {
        global $DB;
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        assign_capability('mod/book:edit', CAP_PROHIBIT, $roleid, \context_course::instance($this->course->id));
        $this->login_as('editingteacher');

        try {
            $this->call();
            $this->fail('Expected a required_capability_exception');
        } catch (\required_capability_exception $e) {
            $this->assertStringContainsString(get_capability_string('mod/book:edit'), $e->getMessage());
        }
        $this->assertSame(0, $DB->count_records('book', ['course' => $this->course->id]));
    }

    /**
     * A student cannot create books.
     */
    public function test_execute_requires_capability(): void {
        $this->login_as('student');

        $this->expectException(\required_capability_exception::class);
        $this->call();
    }
}
