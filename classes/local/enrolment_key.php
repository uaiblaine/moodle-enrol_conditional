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

namespace enrol_conditional\local;

use core_text;
use stdClass;

/**
 * The enrolment key check, shared by the enrol form, the web service and the enrolment itself.
 *
 * An instance with an empty key needs none. Otherwise the posted key must equal the instance key or, when
 * the instance accepts group keys, a non-empty enrolment key of a group of the course. An empty posted key
 * never matches.
 *
 * @package    enrol_conditional
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class enrolment_key {
    /** @var int The key is accepted, or the instance needs none. */
    public const ACCEPTED = 0;

    /** @var int Refused on an instance that accepts group keys: no hint, there may be several keys. */
    public const REFUSED_GROUP = 2;

    /** @var int Refused, and the site shows the first character of the key as a hint. */
    public const REFUSED_HINT = 3;

    /** @var int Refused, without a hint. */
    public const REFUSED = 4;

    /**
     * Whether the instance needs a key to enrol.
     *
     * The one answer for the enrol page, the listing icons, the app information, the key form and the enrolment
     * itself: a stored key of '0' is a key, which a truthiness test of the column would not say.
     *
     * @param stdClass $instance Enrolment instance.
     * @return bool
     */
    public static function is_required(stdClass $instance): bool {
        return self::is_key((string) ($instance->password ?? ''));
    }

    /**
     * Whether a key as typed, posted or uploaded is a key.
     *
     * The one definition for a value that is not an instance yet (the instance form, the course upload): only the
     * empty string is no key, and '0' is one.
     *
     * @param string $key
     * @return bool
     */
    public static function is_key(string $key): bool {
        return $key !== '';
    }

    /**
     * Checks a posted key against an instance.
     *
     * The values double as the warning codes of the enrol_conditional_enrol_user web service.
     *
     * @param stdClass $instance Enrolment instance.
     * @param string $key Posted key.
     * @return int One of the class constants.
     */
    public static function check(stdClass $instance, string $key): int {
        if (!self::is_required($instance)) {
            return self::ACCEPTED;
        }
        $stored = (string) $instance->password;
        if ($key !== '' && hash_equals($stored, $key)) {
            return self::ACCEPTED;
        }
        if (!empty($instance->customint1)) {
            return self::find_group((int) $instance->courseid, $key) === null ? self::REFUSED_GROUP : self::ACCEPTED;
        }
        return get_config('enrol_conditional', 'showhint') ? self::REFUSED_HINT : self::REFUSED;
    }

    /**
     * Group of a course whose enrolment key is the posted key.
     *
     * @param int $courseid
     * @param string $key Posted key.
     * @return int|null Group id, or null when no group has that key.
     */
    public static function find_group(int $courseid, string $key): ?int {
        global $DB;
        if ($key === '') {
            return null;
        }
        $groups = $DB->get_records_select(
            'groups',
            'courseid = :courseid AND enrolmentkey IS NOT NULL',
            ['courseid' => $courseid],
            'id ASC',
            'id, enrolmentkey'
        );
        foreach ($groups as $group) {
            $groupkey = (string) $group->enrolmentkey;
            if ($groupkey !== '' && hash_equals($groupkey, $key)) {
                return (int) $group->id;
            }
        }
        return null;
    }

    /**
     * Message of a refusal, as plain text.
     *
     * @param stdClass $instance Enrolment instance.
     * @param int $code One of the REFUSED_ constants.
     * @return string
     */
    public static function refusal_message(stdClass $instance, int $code): string {
        if ($code === self::REFUSED_HINT) {
            $hint = core_text::substr((string) $instance->password, 0, 1);
            return get_string('passwordinvalidhint', 'enrol_conditional', $hint);
        }
        return get_string('passwordinvalid', 'enrol_conditional');
    }
}
