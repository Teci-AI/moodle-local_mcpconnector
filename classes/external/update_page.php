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

/**
 * Web service function local_mcpconnector_update_page.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_page extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id of the page'),
            'content' => new external_value(PARAM_RAW, 'New page content (HTML)'),
            'intro' => new external_value(PARAM_RAW, 'New description (HTML); empty keeps the current one', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Replaces the content (and optionally the description) of a Page activity.
     *
     * @param int $cmid
     * @param string $content
     * @param string $intro
     * @return array cmid and success flag
     */
    public static function execute(int $cmid, string $content, string $intro = ''): array {
        global $CFG;
        require_once($CFG->dirroot . '/course/modlib.php');

        [
            'cmid' => $cmid,
            'content' => $content,
            'intro' => $intro,
        ] = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'content' => $content,
            'intro' => $intro,
        ]);

        [$course, $cm] = get_course_and_cm_from_cmid($cmid, 'page');
        self::validate_context(\context_course::instance($course->id));
        require_capability('moodle/course:manageactivities', $cm->context);
        course_helper::require_not_empty('content', $content);

        // The same course module record course/modedit.php passes on.
        $cm = get_coursemodule_from_id('page', $cm->id, $course->id, false, MUST_EXIST);
        [$cm, , , $data] = get_moduleinfo_data($cm, $course);

        // The page settings form unpacks these from displayoptions; page_update_instance() packs them again.
        $displayoptions = (array) unserialize_array($data->displayoptions);
        $config = get_config('page');
        $data->printintro = $displayoptions['printintro'] ?? $config->printintro;
        $data->printlastmodified = $displayoptions['printlastmodified'] ?? $config->printlastmodified;
        $data->popupwidth = $displayoptions['popupwidth'] ?? $config->popupwidth;
        $data->popupheight = $displayoptions['popupheight'] ?? $config->popupheight;

        $data->page = ['text' => $content, 'format' => FORMAT_HTML, 'itemid' => 0];
        if (trim($intro) !== '') {
            $data->introeditor['text'] = $intro;
            $data->introeditor['format'] = FORMAT_HTML;
        }

        update_moduleinfo($cm, $data, $course);

        return [
            'cmid' => (int) $cm->id,
            'success' => true,
        ];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'Course module id of the page'),
            'success' => new external_value(PARAM_BOOL, 'True when the page was updated'),
        ]);
    }
}
