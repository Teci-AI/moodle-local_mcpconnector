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
use local_mcpconnector\local\invalid_parameter_exception;

/**
 * Web service function local_mcpconnector_restore_course.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_course extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'content_base64' => new external_value(PARAM_RAW, 'A course backup (.mbz), base64-encoded'),
            'categoryid' => new external_value(PARAM_INT, 'Category the new course goes into', VALUE_DEFAULT, 1),
            'fullname' => new external_value(PARAM_TEXT, 'Full name; empty keeps the backup\'s; made unique', VALUE_DEFAULT, ''),
            'shortname' => new external_value(PARAM_TEXT, 'Short name; empty keeps the backup\'s; made unique', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Restores a course backup into a new course.
     *
     * Follows admin/cli/restore_backup.php: the backup is extracted, a new course is created in the
     * category and the backup is restored into it with the site's general restore defaults. If the
     * restore fails, the new course is deleted.
     *
     * @param string $contentbase64
     * @param int $categoryid
     * @param string $fullname
     * @param string $shortname
     * @return array id and names of the new course
     */
    public static function execute(
        string $contentbase64,
        int $categoryid = 1,
        string $fullname = '',
        string $shortname = ''
    ): array {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'content_base64' => $contentbase64,
            'categoryid' => $categoryid,
            'fullname' => $fullname,
            'shortname' => $shortname,
        ]);

        $category = \core_course_category::get($params['categoryid']);
        $context = $category->get_context();
        self::validate_context($context);
        // Restoring an uploaded file into a new course, as on the restore page.
        require_capability('moodle/course:create', $context);
        require_capability('moodle/restore:restorecourse', $context);
        require_capability('moodle/restore:uploadfile', $context);

        // As on the restore page, names that clash with an existing course get a " copy 1" / "_1" suffix.
        $fullname = trim($params['fullname']);
        $shortname = trim($params['shortname']);

        \core_php_time_limit::raise();
        raise_memory_limit(MEMORY_HUGE);

        $content = base64_decode($params['content_base64'], true);
        unset($params['content_base64'], $contentbase64);
        if ($content === false || $content === '') {
            throw new invalid_parameter_exception('content_base64 is not valid base64');
        }
        if (strlen($content) > backup_course::MAX_BYTES) {
            throw new \moodle_exception('backuptoolarge', 'local_mcpconnector', '', display_size(strlen($content)));
        }
        $mbz = make_request_directory() . '/backup.mbz';
        file_put_contents($mbz, $content);
        unset($content);

        $backupid = \restore_controller::get_tempdir_name(0, $USER->id);
        $path = make_backup_temp_directory($backupid);
        if (!get_file_packer('application/vnd.moodle.backup')->extract_to_pathname($mbz, $path)) {
            fulldelete($path);
            throw new \moodle_exception('invalidbackupfile', 'local_mcpconnector');
        }

        $courseid = 0;
        try {
            [$tmpfullname, $tmpshortname] = \restore_dbops::calculate_course_names(
                0,
                get_string('restoringcourse', 'backup'),
                get_string('restoringcourseshortname', 'backup')
            );
            $courseid = \restore_dbops::create_new_course($tmpfullname, $tmpshortname, $category->id);
            $rc = new \restore_controller(
                $backupid,
                $courseid,
                \backup::INTERACTIVE_NO,
                \backup::MODE_GENERAL,
                $USER->id,
                \backup::TARGET_NEW_COURSE
            );
            try {
                if ($rc->get_type() !== \backup::TYPE_1COURSE) {
                    throw new \moodle_exception('notcoursebackup', 'local_mcpconnector');
                }
                if ($fullname !== '') {
                    $rc->get_plan()->get_setting('course_fullname')->set_value($fullname);
                }
                if ($shortname !== '') {
                    $rc->get_plan()->get_setting('course_shortname')->set_value($shortname);
                }
                if (!$rc->execute_precheck()) {
                    $errors = $rc->get_precheck_results()['errors'] ?? [];
                    if ($errors) {
                        throw new \moodle_exception('restoreprecheckfailed', 'local_mcpconnector', '', implode(' ', $errors));
                    }
                }
                $rc->execute_plan();
            } finally {
                $rc->destroy();
            }
        } catch (\Throwable $e) {
            if ($courseid) {
                delete_course($courseid, false);
            }
            throw $e;
        } finally {
            fulldelete($path);
        }

        $course = get_course($courseid);
        return [
            'courseid' => (int) $course->id,
            'fullname' => $course->fullname,
            'shortname' => $course->shortname,
        ];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courseid' => new external_value(PARAM_INT, 'Id of the new course'),
            'fullname' => new external_value(PARAM_TEXT, 'Full name of the new course'),
            'shortname' => new external_value(PARAM_TEXT, 'Short name of the new course'),
        ]);
    }
}
