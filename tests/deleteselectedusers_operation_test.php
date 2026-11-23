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

namespace enrol_conditional;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/enrol/locallib.php');

/**
 * Tests for the bulk operation that removes the selected user enrolments.
 *
 * @package    enrol_conditional
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\enrol_conditional_deleteselectedusers_operation::class)]
final class deleteselectedusers_operation_test extends \advanced_testcase {
    /**
     * Only the enrolments of this method are removed, even when the selected user carries others.
     */
    public function test_process_removes_only_this_method(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        \core\plugininfo\enrol::enable_plugin('conditional', true);
        $plugin = enrol_get_plugin('conditional');
        $course = $this->getDataGenerator()->create_course();
        $id = $plugin->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED]);
        $instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
        $manual = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', MUST_EXIST);

        $both = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $plugin->enrol_user($instance, $both->id);
        $plugin->enrol_user($instance, $other->id);
        $this->getDataGenerator()->enrol_user($both->id, $course->id, null, 'manual');

        $manager = new \course_enrolment_manager($PAGE, $course);
        $selected = $manager->get_users_enrolments([$both->id, $other->id]);
        $this->assertCount(2, $selected[$both->id]->enrolments);
        $operation = new \enrol_conditional_deleteselectedusers_operation($manager, $plugin);

        $this->assertTrue($operation->process($manager, $selected, new \stdClass()));

        $this->assertFalse($DB->record_exists('user_enrolments', ['enrolid' => $instance->id, 'userid' => $both->id]));
        $this->assertFalse($DB->record_exists('user_enrolments', ['enrolid' => $instance->id, 'userid' => $other->id]));
        $this->assertTrue($DB->record_exists('user_enrolments', ['enrolid' => $manual->id, 'userid' => $both->id]));
    }
}
