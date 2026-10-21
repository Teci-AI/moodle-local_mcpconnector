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
use invalid_parameter_exception;
use local_mcpconnector\local\course_helper;

/**
 * Web service function local_mcpconnector_create_url.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_url extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'section' => new external_value(PARAM_INT, 'Section number within the course', VALUE_DEFAULT, 0),
            'name' => new external_value(PARAM_TEXT, 'Resource name'),
            'externalurl' => new external_value(PARAM_RAW_TRIMMED, 'The URL to link to'),
            'intro' => new external_value(PARAM_RAW, 'Description (HTML)', VALUE_DEFAULT, ''),
            'display' => new external_value(
                PARAM_INT,
                'Display mode (RESOURCELIB_DISPLAY_*): 0 automatic, 1 embed, 2 frame, 3 new window, 5 open, 6 popup; ' .
                    'must be enabled in the URL module settings',
                VALUE_DEFAULT,
                0
            ),
            'visible' => new external_value(PARAM_INT, '1 to show the resource, 0 to hide it', VALUE_DEFAULT, 1),
        ]);
    }

    /**
     * Creates a URL resource.
     *
     * @param int $courseid
     * @param int $section
     * @param string $name
     * @param string $externalurl
     * @param string $intro
     * @param int $display
     * @param int $visible
     * @return array cmid and instanceid of the new resource
     */
    public static function execute(
        int $courseid,
        int $section,
        string $name,
        string $externalurl,
        string $intro = '',
        int $display = 0,
        int $visible = 1
    ): array {
        global $CFG;
        require_once($CFG->libdir . '/resourcelib.php');
        require_once($CFG->dirroot . '/mod/url/locallib.php');

        [
            'courseid' => $courseid,
            'section' => $section,
            'name' => $name,
            'externalurl' => $externalurl,
            'intro' => $intro,
            'display' => $display,
            'visible' => $visible,
        ] = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'section' => $section,
            'name' => $name,
            'externalurl' => $externalurl,
            'intro' => $intro,
            'display' => $display,
            'visible' => $visible,
        ]);

        $course = get_course($courseid);
        self::validate_context(\context_course::instance($course->id));
        course_helper::require_can_add_module($course, 'url', $visible);
        course_helper::require_not_empty('name', $name);
        course_helper::validate_visible($visible);
        self::validate_url($externalurl);
        course_helper::get_section($course, $section);

        $config = get_config('url');
        if (!in_array($display, array_map('intval', explode(',', $config->displayoptions)), true)) {
            throw new invalid_parameter_exception("display {$display} is not enabled in the URL module settings");
        }

        $moduleinfo = course_helper::add_module($course, 'url', $section, $visible, [
            'name' => $name,
            'intro' => $intro,
            'introformat' => FORMAT_HTML,
            'externalurl' => $externalurl,
            'display' => $display,
            'popupwidth' => $config->popupwidth,
            'popupheight' => $config->popupheight,
            'printintro' => $config->printintro,
        ]);

        return [
            'cmid' => (int) $moduleinfo->coursemodule,
            'instanceid' => (int) $moduleinfo->instance,
        ];
    }

    /**
     * Validates the URL the same way the URL resource's settings form does.
     *
     * @param string $url
     * @throws invalid_parameter_exception
     */
    private static function validate_url(string $url): void {
        course_helper::require_not_empty('externalurl', $url);
        if (preg_match('|^/|', $url)) {
            // Links relative to the server root are allowed.
            return;
        }
        if (preg_match('|^[a-z]+://|i', $url) || preg_match('|^https?:|i', $url) || preg_match('|^ftp:|i', $url)) {
            $valid = url_appears_valid_url($url);
        } else if (preg_match('|^[a-z]+:|i', $url)) {
            // Other URI schemes (mailto: and similar) are allowed as they are.
            $valid = true;
        } else {
            // A URL without a scheme gets http:// added when it is saved.
            $valid = url_appears_valid_url('http://' . $url);
        }
        if (!$valid) {
            throw new invalid_parameter_exception('externalurl is not a valid URL');
        }
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'Course module id of the new resource'),
            'instanceid' => new external_value(PARAM_INT, 'URL instance id'),
        ]);
    }
}
