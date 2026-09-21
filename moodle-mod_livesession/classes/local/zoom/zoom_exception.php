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

namespace mod_livesession\local\zoom;

use moodle_exception;

/**
 * Thrown when the Zoom API rejects a request or is unreachable.
 *
 * @package    mod_livesession
 * @copyright  2026 Aspire Education and Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class zoom_exception extends moodle_exception {
    /** @var int HTTP status code returned by Zoom, or 0 if the call never completed. */
    public $httpstatus;

    /** @var int Zoom's own numeric error code, or 0 if absent. */
    public $zoomcode;

    /**
     * Construct the exception from what Zoom told us.
     *
     * @param string $detail human readable detail, already safe to show an admin
     * @param int $httpstatus
     * @param int $zoomcode
     */
    public function __construct(string $detail, int $httpstatus = 0, int $zoomcode = 0) {
        $this->httpstatus = $httpstatus;
        $this->zoomcode = $zoomcode;
        parent::__construct('error:zoomapi', 'mod_livesession', '', $detail);
    }
}
