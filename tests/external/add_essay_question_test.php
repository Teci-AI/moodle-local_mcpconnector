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

use invalid_parameter_exception;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/mcpconnector/tests/question_testcase.php');

/**
 * Tests for local_mcpconnector_add_essay_question.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(add_essay_question::class)]
final class add_essay_question_test extends \local_mcpconnector\question_testcase {
    /**
     * The essay options are saved.
     */
    public function test_execute(): void {
        global $DB;
        $this->login_as('editingteacher');

        $result = $this->call(add_essay_question::class, [
            'quizcmid' => $this->quiz->cmid,
            'name' => 'Essay',
            'questiontext' => 'Discuss.',
            'graderinfo' => '<p>Look for structure</p>',
            'responsetemplate' => '<p>Start here</p>',
            'responselines' => 20,
            'attachments' => -1,
            'defaultmark' => 5,
        ]);

        $question = $this->assert_question_in_quiz($result, 1, 'essay');
        $options = $DB->get_record('qtype_essay_options', ['questionid' => $question->id], '*', MUST_EXIST);
        $this->assertSame('editor', $options->responseformat);
        $this->assertEquals(20, $options->responsefieldlines);
        $this->assertEquals(-1, $options->attachments);
        $this->assertSame('<p>Look for structure</p>', $options->graderinfo);
        $this->assertSame('<p>Start here</p>', $options->responsetemplate);
    }

    /**
     * Only the response box sizes the essay form offers are accepted.
     */
    public function test_execute_invalid_responselines(): void {
        $this->login_as('editingteacher');

        $this->expectException(invalid_parameter_exception::class);
        $this->call(add_essay_question::class, [
            'quizcmid' => $this->quiz->cmid, 'name' => 'Essay', 'questiontext' => 'Discuss.', 'responselines' => 12,
        ]);
    }
}
