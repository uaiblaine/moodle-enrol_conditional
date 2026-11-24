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

namespace enrol_conditional\privacy;

/**
 * Tests of the privacy provider.
 *
 * @package    enrol_conditional
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
final class provider_test extends \advanced_testcase {
    public function test_component_is_compliant(): void {
        $this->resetAfterTest();
        $manager = new \core_privacy\manager();
        $this->assertTrue($manager->component_is_compliant('enrol_conditional'));
    }

    /**
     * The rows this method writes carry no component, so core's providers cover them.
     *
     * The null provider is right only while that holds: roles_protected() is false, so core assigns the role
     * without a component, and group memberships are added without one, which is what core_group covers.
     */
    public function test_writes_are_covered_by_core(): void {
        global $DB;
        $this->resetAfterTest();
        \core\plugininfo\enrol::enable_plugin('conditional', true);
        $plugin = enrol_get_plugin('conditional');
        $course = $this->getDataGenerator()->create_course();
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'enrolmentkey' => 'groupkey']);
        $id = $plugin->add_instance($course, [
            'status' => ENROL_INSTANCE_ENABLED,
            'customint6' => 1,
            'password' => 'coursekey',
            'customint1' => 1,
            'roleid' => $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST),
        ]);
        $instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $plugin->enrol_conditional($instance, (object) ['enrolpassword' => 'groupkey']);

        $this->assertSame([''], $DB->get_fieldset('role_assignments', 'component', ['userid' => $user->id]));
        $this->assertSame(
            [''],
            $DB->get_fieldset('groups_members', 'component', ['userid' => $user->id, 'groupid' => $group->id])
        );
        $contexts = \core_role\privacy\provider::get_contexts_for_userid((int) $user->id)->get_contextids();
        $this->assertContainsEquals(\context_course::instance($course->id)->id, $contexts);
        $contexts = \core_group\privacy\provider::get_contexts_for_userid((int) $user->id)->get_contextids();
        $this->assertContainsEquals(\context_course::instance($course->id)->id, $contexts);
    }
}
