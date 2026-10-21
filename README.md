# MCP Connector (local_mcpconnector)

A Moodle local plugin that adds the web service functions Moodle core lacks for
course authoring: creating and editing activities and sections. Its client is
moodle-mcp-connector, an MCP server that lets an AI agent author Moodle
courses.

Every function uses Moodle's own course APIs (`add_moduleinfo`,
`update_moduleinfo`, course format actions…) and checks the same capabilities
as Moodle's own course editing pages, so the token user can do nothing through
this plugin that they could not do in the browser.

## Requirements

- Moodle 5.0 or later (5.x)
- Web services and the REST protocol enabled

## Installation

1. Copy this folder to `local/mcpconnector` (`public/local/mcpconnector` on
   Moodle 5.1 and later), or install the zip from *Site administration →
   Plugins → Install plugins*.
2. Run the upgrade (*Site administration → Notifications*, or
   `php admin/cli/upgrade.php`).

## Setup

1. *Site administration → General → Advanced features*: enable web services.
2. *Site administration → Server → Web services → Manage protocols*: enable REST.
3. Create a dedicated user for the agent. Give it a role (for example Teacher)
   only in the courses it should edit.
4. *Site administration → Server → Web services → External services*: enable
   **MCP Connector Service** and, under *Authorised users*, add the dedicated
   user. The service is disabled by default and only open to authorised users.
5. *Manage tokens*: create a token for that user and the MCP Connector Service.

The service also contains the core read functions the client's `content` tool
set uses (`core_course_get_contents` and others), so one token covers reading
and authoring course content. It allows file downloads (needed by the client's
`read_activity` tool).

## Functions

| Function | Parameters | Returns |
|---|---|---|
| `local_mcpconnector_get_info` | none | `component`, `version`, `release` |
| `local_mcpconnector_create_page` | courseid, section=0, name, content, intro="", visible=1 | `cmid`, `instanceid` |
| `local_mcpconnector_update_page` | cmid, content, intro="" (empty keeps the description) | `cmid`, `success` |
| `local_mcpconnector_create_book` | courseid, section=0, name, intro="", chaptertitle, chaptercontent, visible=1 | `cmid`, `instanceid`, `chapterid` |
| `local_mcpconnector_create_label` | courseid, section=0, content, name="" (empty builds it from the content), visible=1 | `cmid`, `instanceid` |
| `local_mcpconnector_create_url` | courseid, section=0, name, externalurl, intro="", display=0, visible=1 | `cmid`, `instanceid` |
| `local_mcpconnector_create_section` | courseid, name, summary="", position=0 (0 adds it at the end) | `sectionid`, `sectionnum` |
| `local_mcpconnector_delete_section` | courseid, sectionnum (1 or more) | `success` |
| `local_mcpconnector_update_activity` | cmid, name="" (empty keeps it), visible=-1 (1 show, 0 hide, -1 keep) | `cmid`, `name`, `visible`, `changed` |
| `local_mcpconnector_move_activity` | cmid, section | `cmid`, `section` |
| `local_mcpconnector_delete_activity` | cmid | `cmid`, `success` |
| `local_mcpconnector_create_quiz` | courseid, section=0, name, intro="", timeopen=0, timeclose=0, timelimit=0, grade=100, attempts=0, visible=1 | `cmid`, `instanceid` |
| `local_mcpconnector_add_quiz_question` | quizcmid, name, questiontext, answers [{text, fraction=0, feedback=""}] (2 or more), single=1, defaultmark=1 | `questionid`, `slot` |
| `local_mcpconnector_add_truefalse_question` | quizcmid, name, questiontext, correctanswer (1 true, 0 false), defaultmark=1 | `questionid`, `slot` |
| `local_mcpconnector_add_shortanswer_question` | quizcmid, name, questiontext, answers [string] (1 or more, all full marks), usecase=0, defaultmark=1 | `questionid`, `slot` |
| `local_mcpconnector_add_essay_question` | quizcmid, name, questiontext, graderinfo="", responsetemplate="", responselines=15, attachments=0, defaultmark=1 | `questionid`, `slot` |
| `local_mcpconnector_add_numerical_question` | quizcmid, name, questiontext, answers [{answer, tolerance=0, fraction=1, feedback=""}] (1 or more), defaultmark=1 | `questionid`, `slot` |
| `local_mcpconnector_add_matching_question` | quizcmid, name, questiontext, pairs [{question, answer}] (3 or more), shuffleanswers=1, defaultmark=1 | `questionid`, `slot` |
| `local_mcpconnector_create_forum` | courseid, section=0, name, intro="", type="general" (general, eachuser, single, qanda, blog), visible=1 | `cmid`, `instanceid` |
| `local_mcpconnector_create_choice` | courseid, section=0, name, question, options [string] (2 or more), allowupdate=1, visible=1 | `cmid`, `instanceid` |
| `local_mcpconnector_create_assignment` | courseid, section=0, name, intro="", duedate=0, grade=100, visible=1 | `cmid`, `instanceid` |
| `local_mcpconnector_backup_course` | courseid, includeusers=0 | `filename`, `size`, `content_base64` |
| `local_mcpconnector_restore_course` | content_base64, categoryid=1, fullname="", shortname="" (empty keeps the backup's names) | `courseid`, `fullname`, `shortname` |

`section` / `sectionnum` parameters are section numbers within the course
(0 is the general section), not section database ids.

The `add_*_question` functions save the question in the quiz's own question
bank (its default category) and add it at the end of the quiz, as the quiz's
*Add > a new question* does. They refuse quizzes that already have attempts.
Each question type is checked like its editing form: fractions must be
Moodle grade values (1.0, 0.5, 0.3333333, 0.25… within 0.005, so 0.33 is
read as a third), and a matching question needs at least three pairs.

`backup_course` and `restore_course` run Moodle's course backup and restore
synchronously with the site's general backup defaults, and refuse files over
100 MB. The web server's upload limits (`post_max_size`, `memory_limit`) must
allow the base64-encoded file, which is about a third larger. `restore_course`
always creates a new course and deletes it again if the restore fails. As on
the restore page, names that clash with an existing course get a " copy 1" or
"_1" suffix; the result returns the names used.

## Development

Run the tests from the Moodle root:

```bash
vendor/bin/phpunit --testsuite local_mcpconnector_testsuite
```

Continuous integration runs
[moodle-plugin-ci](https://moodlehq.github.io/moodle-plugin-ci/) on every
supported Moodle 5.x branch.

## License

2026 Teci. GNU GPL v3 or later; see <http://www.gnu.org/copyleft/gpl.html>.
