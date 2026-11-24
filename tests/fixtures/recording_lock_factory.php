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
 * A lock factory that grants every lock and records when it was taken and released.
 *
 * @package    enrol_conditional
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_conditional;

/**
 * A lock factory that grants every lock and logs "acquire <resource>" and "release" into {@see probe_plugin::$log}
 * and runs {@see probe_plugin::$afteracquire} once a lock is granted.
 *
 * @package    enrol_conditional
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class recording_lock_factory implements \core\lock\lock_factory {
    /**
     * Nothing to set up.
     *
     * @param string $type The lock type.
     */
    public function __construct($type) {
    }

    /**
     * Waiting is supported.
     *
     * @return bool
     */
    public function supports_timeout() {
        return true;
    }

    /**
     * Locks are released by the caller.
     *
     * @return bool
     */
    public function supports_auto_release() {
        return true;
    }

    /**
     * Always available, so lock_config hands it out.
     *
     * @return bool
     */
    public function is_available() {
        return true;
    }

    /**
     * Grant the lock and record it.
     *
     * @param string $resource The resource.
     * @param int $timeout The wait.
     * @param int $maxlifetime The lifetime.
     * @return \core\lock\lock
     */
    public function get_lock($resource, $timeout, $maxlifetime = 86400) {
        probe_plugin::$log[] = 'acquire ' . $resource;
        if (probe_plugin::$afteracquire !== null) {
            (probe_plugin::$afteracquire)();
        }
        return new \core\lock\lock($resource, $this);
    }

    /**
     * Record the release.
     *
     * @param \core\lock\lock $lock The lock.
     * @return bool
     */
    public function release_lock(\core\lock\lock $lock) {
        probe_plugin::$log[] = 'release';
        return true;
    }
}
