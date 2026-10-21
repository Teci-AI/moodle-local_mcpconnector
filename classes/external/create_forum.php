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
 * Web service function local_mcpconnector_create_forum.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_forum extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'section' => new external_value(PARAM_INT, 'Section number within the course', VALUE_DEFAULT, 0),
            'name' => new external_value(PARAM_TEXT, 'Forum name'),
            'intro' => new external_value(PARAM_RAW, 'Description (HTML); first post of a single discussion', VALUE_DEFAULT, ''),
            'type' => new external_value(PARAM_ALPHA, 'general, eachuser, single, qanda or blog', VALUE_DEFAULT, 'general'),
            'visible' => new external_value(PARAM_INT, '1 to show the forum, 0 to hide it', VALUE_DEFAULT, 1),
        ]);
    }

    /**
     * Creates a Forum activity, with the site's default forum settings for everything not given.
     *
     * @param int $courseid
     * @param int $section
     * @param string $name
     * @param string $intro
     * @param string $type
     * @param int $visible
     * @return array cmid and instanceid of the new forum
     */
    public static function execute(
        int $courseid,
        int $section,
        string $name,
        string $intro = '',
        string $type = 'general',
        int $visible = 1
    ): array {
        global $CFG;
        require_once($CFG->dirroot . '/mod/forum/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'section' => $section,
            'name' => $name,
            'intro' => $intro,
            'type' => $type,
            'visible' => $visible,
        ]);

        $course = get_course($params['courseid']);
        self::validate_context(\context_course::instance($course->id));
        course_helper::require_can_add_module($course, 'forum', $params['visible']);
        course_helper::require_not_empty('name', $params['name']);
        course_helper::validate_visible($params['visible']);
        if (!array_key_exists($params['type'], forum_get_forum_types())) {
            throw new invalid_parameter_exception('type must be one of ' . implode(', ', array_keys(forum_get_forum_types())));
        }
        course_helper::get_section($course, $params['section']);

        // The forum settings form's defaults (Site administration > Plugins > Forum).
        $trackingtype = $CFG->forum_trackingtype;
        if (!$CFG->forum_allowforcedreadtracking && $trackingtype == FORUM_TRACKING_FORCED) {
            $trackingtype = FORUM_TRACKING_OPTIONAL;
        }
        $moduleinfo = course_helper::add_module($course, 'forum', $params['section'], $params['visible'], [
            'name' => $params['name'],
            'intro' => $params['intro'],
            'introformat' => FORMAT_HTML,
            'type' => $params['type'],
            'forcesubscribe' => $CFG->forum_subscription ?? FORUM_CHOOSESUBSCRIBE,
            'trackingtype' => $trackingtype,
            'maxbytes' => $CFG->forum_maxbytes,
            'maxattachments' => $CFG->forum_maxattachments,
            'displaywordcount' => 0,
            'assessed' => 0,
            'scale' => 0,
            'grade_forum' => 0,
            'blockafter' => 0,
            'blockperiod' => 0,
            'warnafter' => 0,
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
            'cmid' => new external_value(PARAM_INT, 'Course module id of the new forum'),
            'instanceid' => new external_value(PARAM_INT, 'Forum instance id'),
        ]);
    }
}
