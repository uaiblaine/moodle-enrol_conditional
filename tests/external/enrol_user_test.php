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

use local_enrolconditions\condition\manager;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/enrolconditions/tests/fixtures/fake_condition.php');

/**
 * Tests of enrol_conditional_enrol_user, called through the web service layer.
 *
 * The fake condition is met when the user id is in config['userids'].
 *
 * @package    enrol_conditional
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(enrol_user::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\enrol_conditional\local\enrolment_key::class)]
final class enrol_user_test extends \advanced_testcase {
    /** @var \enrol_conditional_plugin The enrolment plugin. */
    private \enrol_conditional_plugin $plugin;

    /** @var \stdClass Course of the tests. */
    private \stdClass $course;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        \core\plugininfo\enrol::enable_plugin('conditional', true);
        manager::reset_caches();
        manager::register_for_testing('fake', new \enrolcond_fake\condition());
        $this->plugin = enrol_get_plugin('conditional');
        $this->course = $this->getDataGenerator()->create_course();
    }

    #[\Override]
    protected function tearDown(): void {
        manager::reset_caches();
        parent::tearDown();
    }

    /**
     * Calls the function as a client does.
     *
     * @param int $courseid
     * @param string $password
     * @param int $instanceid
     * @return array Response of call_external_function().
     */
    private function call(int $courseid, string $password = '', int $instanceid = 0): array {
        $_POST['sesskey'] = sesskey();
        return \core_external\external_api::call_external_function(
            'enrol_conditional_enrol_user',
            ['courseid' => $courseid, 'password' => $password, 'instanceid' => $instanceid]
        );
    }

    /**
     * Creates an enabled instance of this method in the course.
     *
     * @param array $fields Other fields.
     * @return \stdClass
     */
    private function create_instance(array $fields = []): \stdClass {
        global $DB;
        $id = $this->plugin->add_instance($this->course, $fields + [
            'status' => ENROL_INSTANCE_ENABLED,
            'customint6' => 1,
        ]);
        return $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Whether a user is enrolled through an instance.
     *
     * @param \stdClass $instance
     * @param int $userid
     * @return bool
     */
    private function enrolled(\stdClass $instance, int $userid): bool {
        global $DB;
        return $DB->record_exists('user_enrolments', ['enrolid' => $instance->id, 'userid' => $userid]);
    }

    public function test_wrong_key_is_refused_and_right_key_enrols(): void {
        $instance = $this->create_instance(['password' => 'rightkey']);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        foreach (['', 'wrongkey', 'rightke', 'rightkey '] as $key) {
            $response = $this->call((int) $this->course->id, $key, (int) $instance->id);
            $this->assertFalse($response['error']);
            $this->assertFalse($response['data']['status'], "Key '$key' must be refused.");
            $this->assertSame('4', (string) $response['data']['warnings'][0]['warningcode']);
            $this->assertFalse($this->enrolled($instance, (int) $user->id));
        }

        $response = $this->call((int) $this->course->id, 'rightkey', (int) $instance->id);
        $this->assertTrue($response['data']['status']);
        $this->assertTrue($this->enrolled($instance, (int) $user->id));
    }

    public function test_hint_gives_the_first_character(): void {
        $this->plugin->set_config('showhint', 1);
        $instance = $this->create_instance(['password' => 'rightkey']);
        $this->setUser($this->getDataGenerator()->create_user());

        $response = $this->call((int) $this->course->id, 'wrongkey', (int) $instance->id);
        $this->assertSame('3', (string) $response['data']['warnings'][0]['warningcode']);
        $this->assertStringContainsString(
            s(get_string('passwordinvalidhint', 'enrol_conditional', 'r')),
            $response['data']['warnings'][0]['message']
        );
    }

    public function test_group_key_enrols_into_its_group(): void {
        global $CFG;
        require_once($CFG->dirroot . '/group/lib.php');
        $instance = $this->create_instance(['password' => 'coursekey', 'customint1' => 1]);
        $group = $this->getDataGenerator()->create_group(['courseid' => $this->course->id, 'enrolmentkey' => 'groupkey']);
        $this->getDataGenerator()->create_group(['courseid' => $this->course->id, 'enrolmentkey' => '']);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        foreach (['', 'wrongkey'] as $key) {
            $response = $this->call((int) $this->course->id, $key, (int) $instance->id);
            $this->assertFalse($response['data']['status'], "Key '$key' must be refused.");
            $this->assertSame('2', (string) $response['data']['warnings'][0]['warningcode']);
            $this->assertFalse($this->enrolled($instance, (int) $user->id));
        }

        $response = $this->call((int) $this->course->id, 'groupkey', (int) $instance->id);
        $this->assertTrue($response['data']['status']);
        $this->assertTrue($this->enrolled($instance, (int) $user->id));
        $this->assertTrue(groups_is_member($group->id, $user->id));
    }

    public function test_instance_without_key_needs_none(): void {
        $instance = $this->create_instance();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $response = $this->call((int) $this->course->id, '', (int) $instance->id);
        $this->assertTrue($response['data']['status']);
        $this->assertTrue($this->enrolled($instance, (int) $user->id));
    }

    public function test_conditions_refuse_with_plain_text(): void {
        $allowed = $this->getDataGenerator()->create_user();
        $refused = $this->getDataGenerator()->create_user();
        $instance = $this->create_instance(['customtext2' => json_encode(['op' => 'and', 'children' => [
            ['type' => 'fake', 'config' => ['userids' => [(int) $allowed->id]]],
        ]])]);

        $this->setUser($refused);
        $response = $this->call((int) $this->course->id, '', (int) $instance->id);
        $this->assertFalse($response['data']['status']);
        $this->assertSame('1', (string) $response['data']['warnings'][0]['warningcode']);
        $this->assertStringContainsString('Not a listed user', $response['data']['warnings'][0]['message']);
        $this->assertStringNotContainsString('<', $response['data']['warnings'][0]['message']);
        $this->assertFalse($this->enrolled($instance, (int) $refused->id));

        $this->setUser($allowed);
        $response = $this->call((int) $this->course->id, '', (int) $instance->id);
        $this->assertTrue($response['data']['status']);
        $this->assertTrue($this->enrolled($instance, (int) $allowed->id));
    }

    public function test_instance_of_another_course_is_ignored(): void {
        $other = $this->getDataGenerator()->create_course();
        $otherid = $this->plugin->add_instance($other, ['status' => ENROL_INSTANCE_ENABLED, 'customint6' => 1]);
        $this->create_instance();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $response = $this->call((int) $this->course->id, '', (int) $otherid);
        $this->assertTrue($response['error']);
        $this->assertSame('canntenrol', $response['exception']->errorcode);
        $this->assertFalse(is_enrolled(\context_course::instance($other->id), $user));
    }

    public function test_hidden_course_is_refused(): void {
        global $DB;
        $instance = $this->create_instance();
        $DB->set_field('course', 'visible', 0, ['id' => $this->course->id]);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $response = $this->call((int) $this->course->id, '', (int) $instance->id);
        $this->assertTrue($response['error']);
        $this->assertSame('coursehidden', $response['exception']->errorcode);
        $this->assertFalse($this->enrolled($instance, (int) $user->id));
    }

    public function test_guest_is_refused(): void {
        $instance = $this->create_instance();
        $this->setGuestUser();

        $response = $this->call((int) $this->course->id, '', (int) $instance->id);
        $this->assertFalse($response['error']);
        $this->assertFalse($response['data']['status']);
        $this->assertFalse($this->enrolled($instance, (int) guest_user()->id));
    }
}
