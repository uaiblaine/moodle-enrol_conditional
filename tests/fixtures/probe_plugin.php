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
 * A conditional enrolment plugin that records its writes, for the tests of the enrolment's race windows.
 *
 * @package    enrol_conditional
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_conditional;

/**
 * The plugin, with two seams: the "already enrolled" fact can be made stale (another request enrolled the user
 * after this request read it), and a callback runs right before the enrolment is written (another request
 * deletes the group, say).
 *
 * @package    enrol_conditional
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class probe_plugin extends \enrol_conditional_plugin {
    /** @var string[] What happened, in order, shared with {@see recording_lock_factory}. */
    public static array $log = [];

    /** @var \Closure|null Called right before the enrolment is written. */
    public static ?\Closure $beforewrite = null;

    /** @var \Closure|null Called right after a lock was granted, standing in for what another request wrote while this one waited. */
    public static ?\Closure $afteracquire = null;

    /** @var bool Whether the instance status ignores an existing enrolment, as a read taken before it existed would. */
    public static bool $stale = false;

    /**
     * Forgets everything the seams hold.
     *
     * @return void
     */
    public static function reset(): void {
        self::$log = [];
        self::$beforewrite = null;
        self::$afteracquire = null;
        self::$stale = false;
    }

    #[\Override]
    public function get_name() {
        return 'conditional';
    }

    #[\Override]
    protected function get_instance_status(\stdClass $instance, bool $countplaces) {
        if (self::$stale) {
            return true;
        }
        return parent::get_instance_status($instance, $countplaces);
    }

    #[\Override]
    public function enrol_user(
        \stdClass $instance,
        $userid,
        $roleid = null,
        $timestart = 0,
        $timeend = 0,
        $status = null,
        $recovergrades = null
    ) {
        self::$log[] = 'write';
        if (self::$beforewrite !== null) {
            (self::$beforewrite)();
        }
        parent::enrol_user($instance, $userid, $roleid, $timestart, $timeend, $status, $recovergrades);
    }
}
