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

namespace local_mcpconnector\local;

/**
 * An invalid parameter error whose message says what is wrong.
 *
 * Moodle's invalid_parameter_exception only puts the reason in the debug information, which web
 * service clients do not see unless the site has debugging on. This one keeps the same class
 * and error code but puts the reason in the message, so the client can correct its call.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Teci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class invalid_parameter_exception extends \invalid_parameter_exception {
    /**
     * Constructor.
     *
     * @param string $reason what is wrong with the parameter
     */
    public function __construct($reason = '') {
        \moodle_exception::__construct('invalidparameter', 'local_mcpconnector', '', $reason);
    }
}
