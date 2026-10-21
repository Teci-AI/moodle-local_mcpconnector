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

/**
 * English strings for local_mcpconnector.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['backuptoolarge'] = 'The course backup is {$a}; backups over 100 MB cannot be transferred through the web service.';
$string['backupuserslocked'] = 'The site\'s backup settings lock whether user data is included, so it cannot be changed for this backup.';
$string['cannotdeletesection'] = 'The course format does not allow deleting section {$a}.';
$string['cannotdeletesectionzero'] = 'The general section (section 0) cannot be deleted.';
$string['invalidparameter'] = 'Invalid parameter value: {$a}';
$string['pluginname'] = 'MCP Connector';
$string['privacy:metadata'] = 'The MCP Connector plugin does not store any personal data. It only creates and edits course content through Moodle\'s own APIs.';
$string['sectionnotfound'] = 'Section {$a} does not exist in this course.';
