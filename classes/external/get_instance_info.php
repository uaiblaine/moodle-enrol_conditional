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

/**
 * Web service enrol_conditional_get_instance_info: what the current user needs to enrol through an instance.
 *
 * @package    enrol_conditional
 * @copyright  2012 Rajesh Taneja <rajesh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_instance_info extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'instanceid' => new external_value(PARAM_INT, 'Id of an instance of the conditional self enrolment method'),
        ]);
    }

    /**
     * Returns the information of an instance of this method.
     *
     * The context is the category of the instance's course: the caller is not enrolled yet, so the course
     * context would refuse them in require_login(), as the enrol form's context does
     * ({@see \enrol_conditional\form\enrol_form::get_context_for_dynamic_submission()}).
     *
     * @param int $instanceid
     * @return array
     * @throws \moodle_exception invalidenrolinstance for a missing instance or one of another method, coursehidden.
     */
    public static function execute(int $instanceid): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['instanceid' => $instanceid]);

        // A missing instance and an instance of another method get the same answer.
        $instance = $DB->get_record('enrol', ['id' => $params['instanceid'], 'enrol' => 'conditional']);
        $plugin = enrol_get_plugin('conditional');
        if (!$instance || !$plugin) {
            throw new \moodle_exception('invalidenrolinstance', 'enrol');
        }
        $course = get_course((int) $instance->courseid);
        self::validate_context(\context_course::instance($course->id)->get_parent_context());

        if (!\core_course_category::can_view_course_info($course) && !can_access_course($course)) {
            throw new \moodle_exception('coursehidden');
        }

        $info = $plugin->get_enrol_info($instance);
        $result = [
            'id' => (int) $info->id,
            'courseid' => (int) $info->courseid,
            'type' => $info->type,
            'name' => $info->name,
            'status' => $info->status,
        ];
        if (isset($info->requiredparam->enrolpassword)) {
            $result['enrolpassword'] = $info->requiredparam->enrolpassword;
        }
        return $result;
    }

    /**
     * Returned structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Id of the enrolment instance'),
            'courseid' => new external_value(PARAM_INT, 'Id of the course'),
            'type' => new external_value(PARAM_PLUGIN, 'Type of the enrolment method'),
            'name' => new external_value(PARAM_RAW, 'Name of the enrolment instance'),
            'status' => new external_value(PARAM_RAW, 'True when the user can enrol, else the reason (HTML)'),
            'enrolpassword' => new external_value(
                PARAM_RAW,
                'Label of the enrolment key field, when a key is required',
                VALUE_OPTIONAL
            ),
        ]);
    }
}
