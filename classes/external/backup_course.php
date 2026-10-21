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
 * Web service function local_mcpconnector_backup_course.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_course extends external_api {
    /** @var int largest backup transferred, in bytes */
    public const MAX_BYTES = 100 * 1024 * 1024;

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'includeusers' => new external_value(
                PARAM_INT,
                '1 to include enrolments and user data, 0 for content only',
                VALUE_DEFAULT,
                0
            ),
        ]);
    }

    /**
     * Backs a course up and returns the .mbz file.
     *
     * Runs Moodle's course backup with the site's general backup defaults, as the backup page does,
     * except that user data is included only on request. The file is removed from the user's
     * backup area once it has been read.
     *
     * @param int $courseid
     * @param int $includeusers
     * @return array filename, size and base64-encoded content
     */
    public static function execute(int $courseid, int $includeusers = 0): array {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'includeusers' => $includeusers,
        ]);

        $course = get_course($params['courseid']);
        $context = \context_course::instance($course->id);
        self::validate_context($context);
        require_capability('moodle/backup:backupcourse', $context);
        if ($params['includeusers'] !== 0 && $params['includeusers'] !== 1) {
            throw new invalid_parameter_exception('includeusers must be 0 or 1');
        }
        if ($params['includeusers']) {
            require_capability('moodle/backup:userinfo', $context);
        }

        \core_php_time_limit::raise();
        raise_memory_limit(MEMORY_HUGE);

        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id
        );
        try {
            $users = $bc->get_plan()->get_setting('users');
            if ((bool) $users->get_value() !== (bool) $params['includeusers']) {
                if ($users->get_status() !== \base_setting::NOT_LOCKED) {
                    throw new \moodle_exception('backupuserslocked', 'local_mcpconnector');
                }
                $users->set_value((bool) $params['includeusers']);
            }
            // The file name the backup page proposes, e.g. backup-moodle2-course-2-bio101-20261021-1200-nu.mbz.
            $anonymised = (bool) $bc->get_plan()->get_setting('anonymize')->get_value();
            $bc->get_plan()->get_setting('filename')->set_value(\backup_plan_dbops::get_default_backup_filename(
                \backup::FORMAT_MOODLE,
                \backup::TYPE_1COURSE,
                $course->id,
                (bool) $params['includeusers'],
                $anonymised
            ));
            $bc->execute_plan();
            $file = $bc->get_results()['backup_destination'];
        } finally {
            $bc->destroy();
        }

        $size = (int) $file->get_filesize();
        if ($size > self::MAX_BYTES) {
            $file->delete();
            throw new \moodle_exception('backuptoolarge', 'local_mcpconnector', '', display_size($size));
        }
        $result = [
            'filename' => $file->get_filename(),
            'size' => $size,
            'content_base64' => base64_encode($file->get_content()),
        ];
        $file->delete();

        return $result;
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'filename' => new external_value(PARAM_FILE, 'Backup file name (.mbz)'),
            'size' => new external_value(PARAM_INT, 'File size in bytes'),
            'content_base64' => new external_value(PARAM_RAW, 'The .mbz file, base64-encoded'),
        ]);
    }
}
