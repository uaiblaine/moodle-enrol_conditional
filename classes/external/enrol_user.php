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

namespace enrol_conditional\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use enrol_conditional\local\enrolment_key;

/**
 * Web service enrol_conditional_enrol_user: the current user enrols in a course through this method.
 *
 * @package    enrol_conditional
 * @copyright  2012 Rajesh Taneja <rajesh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_user extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Id of the course'),
            'password' => new external_value(PARAM_RAW, 'Enrolment key', VALUE_DEFAULT, ''),
            'instanceid' => new external_value(
                PARAM_INT,
                'Id of an instance of this method in the course; 0 tries every enabled one',
                VALUE_DEFAULT,
                0
            ),
        ]);
    }

    /**
     * Enrols the current user through the first instance of the course that accepts them.
     *
     * Each refused instance gives a warning: 1 not allowed (the reason in plain text), 2 wrong key where group
     * keys are accepted, 3 wrong key with a hint, 4 wrong key. The enrolment itself is
     * enrol_conditional_plugin::enrol_conditional(), which checks the conditions, the key and the limit again.
     *
     * @param int $courseid
     * @param string $password Enrolment key.
     * @param int $instanceid
     * @return array
     * @throws \moodle_exception coursehidden, canntenrol when the course has no such enabled instance.
     */
    public static function execute(int $courseid, string $password = '', int $instanceid = 0): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'password' => $password,
            'instanceid' => $instanceid,
        ]);

        $course = get_course($params['courseid']);
        // The caller is not enrolled yet, so the course context would refuse them in require_login().
        self::validate_context(\context_course::instance($course->id)->get_parent_context());

        if (!\core_course_category::can_view_course_info($course)) {
            throw new \moodle_exception('coursehidden');
        }

        $plugin = enrol_get_plugin('conditional');
        if (!$plugin) {
            throw new \moodle_exception('canntenrol', 'enrol_conditional');
        }

        $instances = [];
        foreach (enrol_get_instances($course->id, true) as $instance) {
            if ($instance->enrol !== 'conditional') {
                continue;
            }
            if ($params['instanceid'] && (int) $instance->id !== $params['instanceid']) {
                continue;
            }
            $instances[] = $instance;
        }
        if (!$instances) {
            throw new \moodle_exception('canntenrol', 'enrol_conditional');
        }

        $warnings = [];
        $enrolled = false;
        foreach ($instances as $instance) {
            $status = $plugin->can_self_enrol($instance);
            if ($status !== true) {
                $warnings[] = self::warning($instance, 1, html_to_text((string) $status, 0, false));
                continue;
            }
            $check = enrolment_key::check($instance, $params['password']);
            if ($check !== enrolment_key::ACCEPTED) {
                // The message is PARAM_TEXT: the hint character is escaped.
                $warnings[] = self::warning($instance, $check, s(enrolment_key::refusal_message($instance, $check)));
                continue;
            }
            $plugin->enrol_conditional($instance, (object) ['enrolpassword' => $params['password']]);
            $enrolled = true;
            break;
        }

        return ['status' => $enrolled, 'warnings' => $warnings];
    }

    /**
     * A warning about one instance.
     *
     * @param \stdClass $instance
     * @param int $code Warning code.
     * @param string $message Plain text.
     * @return array
     */
    protected static function warning(\stdClass $instance, int $code, string $message): array {
        return [
            'item' => 'instance',
            'itemid' => (int) $instance->id,
            'warningcode' => (string) $code,
            'message' => $message,
        ];
    }

    /**
     * Returned structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'True if the user is enrolled, false otherwise'),
            'warnings' => new external_warnings(),
        ]);
    }
}
