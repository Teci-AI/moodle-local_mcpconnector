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
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_mcpconnector\local\course_helper;
use local_mcpconnector\local\invalid_parameter_exception;

/**
 * Web service function local_mcpconnector_create_assignment.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_assignment extends external_api {
    /** @var array settings the assignment settings form takes from the site defaults, with fallbacks */
    private const SITE_DEFAULTS = [
        'alwaysshowdescription' => 1,
        'submissiondrafts' => 0,
        'requiresubmissionstatement' => 0,
        'sendnotifications' => 0,
        'sendlatenotifications' => 0,
        'sendstudentnotifications' => 1,
        'teamsubmission' => 0,
        'requireallteammemberssubmit' => 0,
        'teamsubmissiongroupingid' => 0,
        'preventsubmissionnotingroup' => 0,
        'blindmarking' => 0,
        'hidegrader' => 0,
        'attemptreopenmethod' => 'untilpass',
        'maxattempts' => -1,
        'markingworkflow' => 0,
        'markingallocation' => 0,
        'markinganonymous' => 0,
    ];

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'section' => new external_value(PARAM_INT, 'Section number within the course', VALUE_DEFAULT, 0),
            'name' => new external_value(PARAM_TEXT, 'Assignment name'),
            'intro' => new external_value(PARAM_RAW, 'Description (HTML)', VALUE_DEFAULT, ''),
            'duedate' => new external_value(PARAM_INT, 'Due date (Unix timestamp), 0 for none', VALUE_DEFAULT, 0),
            'grade' => new external_value(PARAM_INT, 'Maximum grade (points), 0 for no grade', VALUE_DEFAULT, 100),
            'visible' => new external_value(PARAM_INT, '1 to show the assignment, 0 to hide it', VALUE_DEFAULT, 1),
        ]);
    }

    /**
     * Creates an Assignment with online text and file submissions and feedback comments.
     *
     * Everything not given takes the site's default assignment settings.
     *
     * @param int $courseid
     * @param int $section
     * @param string $name
     * @param string $intro
     * @param int $duedate
     * @param int $grade
     * @param int $visible
     * @return array cmid and instanceid of the new assignment
     */
    public static function execute(
        int $courseid,
        int $section,
        string $name,
        string $intro = '',
        int $duedate = 0,
        int $grade = 100,
        int $visible = 1
    ): array {
        global $CFG;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'section' => $section,
            'name' => $name,
            'intro' => $intro,
            'duedate' => $duedate,
            'grade' => $grade,
            'visible' => $visible,
        ]);

        $course = get_course($params['courseid']);
        self::validate_context(\context_course::instance($course->id));
        course_helper::require_can_add_module($course, 'assign', $params['visible']);
        course_helper::require_not_empty('name', $params['name']);
        course_helper::validate_visible($params['visible']);
        if ($params['duedate'] < 0) {
            throw new invalid_parameter_exception('duedate must not be negative');
        }
        $gradepointmax = (int) get_config('core', 'gradepointmax');
        if ($params['grade'] < 0 || $params['grade'] > $gradepointmax) {
            throw new invalid_parameter_exception("grade must be between 0 and {$gradepointmax}");
        }
        course_helper::get_section($course, $params['section']);

        $config = get_config('assign');
        $settings = [];
        foreach (self::SITE_DEFAULTS as $setting => $fallback) {
            $settings[$setting] = $config->$setting ?? $fallback;
        }
        $fileconfig = get_config('assignsubmission_file');

        $moduleinfo = course_helper::add_module($course, 'assign', $params['section'], $params['visible'], $settings + [
            'name' => $params['name'],
            'intro' => $params['intro'],
            'introformat' => FORMAT_HTML,
            'duedate' => $params['duedate'],
            'allowsubmissionsfromdate' => 0,
            'cutoffdate' => 0,
            'gradingduedate' => 0,
            'grade' => $params['grade'],
            'timelimit' => 0,
            'submissionattachments' => 0,
            'assignsubmission_onlinetext_enabled' => 1,
            'assignsubmission_onlinetext_wordlimit_enabled' => 0,
            'assignsubmission_onlinetext_wordlimit' => 0,
            'assignsubmission_file_enabled' => 1,
            'assignsubmission_file_maxfiles' => $fileconfig->maxfiles,
            'assignsubmission_file_maxsizebytes' => $fileconfig->maxbytes,
            'assignsubmission_file_filetypes' => $fileconfig->filetypes ?? '',
            'assignfeedback_comments_enabled' => 1,
            'assignfeedback_comments_commentinline' => 0,
        ]);

        return [
            'cmid' => (int) $moduleinfo->coursemodule,
            'instanceid' => (int) $moduleinfo->instance,
        ];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'Course module id of the new assignment'),
            'instanceid' => new external_value(PARAM_INT, 'Assignment instance id'),
        ]);
    }
}
