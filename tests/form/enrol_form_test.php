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
 * Tests of the key form, entered the way core's dynamic form web service enters it.
 *
 * @package    enrol_conditional
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_conditional\form;

use local_enrolconditions\condition\manager;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/enrol/conditional/lib.php');
require_once($CFG->dirroot . '/local/enrolconditions/tests/fixtures/fake_condition.php');

/**
 * The form's validation, and the checks of its constructor: instance, guest and eligibility.
 *
 * @package    enrol_conditional
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(enrol_form::class)]
final class enrol_form_test extends \advanced_testcase {
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
     * Creates an enabled instance of this method.
     *
     * @param array $fields Other fields.
     * @return \stdClass
     */
    private function create_instance(array $fields = []): \stdClass {
        global $DB;
        $id = $this->plugin->add_instance($this->course, $fields + ['status' => ENROL_INSTANCE_ENABLED, 'customint6' => 1]);
        return $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Opens the form as the web service does.
     *
     * @param int $instanceid
     * @param string $key Posted key.
     * @return enrol_form
     */
    private function open(int $instanceid, string $key = ''): enrol_form {
        $data = enrol_form::mock_ajax_submit([
            'id' => $this->course->id,
            'instance' => $instanceid,
            'enrolpassword' => $key,
        ]);
        $form = new enrol_form(null, null, 'post', '', [], true, $data, true);
        $form->set_data_for_dynamic_submission();
        return $form;
    }

    public function test_validation_refuses_a_wrong_key(): void {
        $instance = $this->create_instance(['password' => 'rightkey']);
        $this->setUser($this->getDataGenerator()->create_user());

        foreach (['', 'wrongkey', 'rightkey '] as $key) {
            $form = $this->open((int) $instance->id, $key);
            $this->assertFalse($form->is_validated(), "Key '$key' must be refused.");
            $this->assertArrayHasKey('enrolpassword', $form->validation(['enrolpassword' => $key], []));
        }

        // Control: the right key validates and the submission enrols.
        $form = $this->open((int) $instance->id, 'rightkey');
        $this->assertTrue($form->is_validated());
        $this->assertSame([], $form->validation(['enrolpassword' => 'rightkey'], []));
    }

    public function test_key_zero_opens_the_form_and_enrols(): void {
        global $DB;
        $instance = $this->create_instance(['password' => '0']);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $form = $this->open((int) $instance->id, '1');
        $this->assertFalse($form->is_validated());

        $form = $this->open((int) $instance->id, '0');
        $this->assertTrue($form->is_validated());
        $form->process_dynamic_submission();
        $this->assertTrue($DB->record_exists('user_enrolments', ['enrolid' => $instance->id, 'userid' => $user->id]));
    }

    public function test_instance_of_another_method_does_not_open(): void {
        global $DB;
        $manual = $DB->get_record('enrol', ['courseid' => $this->course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        $instance = $this->create_instance(['password' => 'rightkey']);
        $this->setUser($this->getDataGenerator()->create_user());

        try {
            $this->open((int) $manual->id);
            $this->fail('The form must not open.');
        } catch (\moodle_exception $e) {
            $this->assertSame('invalidenrolinstance', $e->errorcode);
        }

        // Control: an instance of this method opens.
        $this->assertInstanceOf(enrol_form::class, $this->open((int) $instance->id));
    }

    public function test_guest_does_not_open_the_form(): void {
        $instance = $this->create_instance(['password' => 'rightkey']);
        $this->setGuestUser();

        try {
            $this->open((int) $instance->id);
            $this->fail('The form must not open.');
        } catch (\moodle_exception $e) {
            $this->assertSame('noguestaccess', $e->errorcode);
        }

        // Control: a person opens the same form.
        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertInstanceOf(enrol_form::class, $this->open((int) $instance->id));
    }

    public function test_user_who_cannot_enrol_does_not_open_the_form(): void {
        $allowed = $this->getDataGenerator()->create_user();
        $refused = $this->getDataGenerator()->create_user();
        $instance = $this->create_instance([
            'password' => 'rightkey',
            'customtext2' => json_encode(['op' => 'and', 'children' => [
                ['type' => 'fake', 'config' => ['userids' => [(int) $allowed->id]]],
            ]]),
        ]);

        $this->setUser($refused);
        try {
            $this->open((int) $instance->id, 'rightkey');
            $this->fail('The form must not open.');
        } catch (\moodle_exception $e) {
            $this->assertSame('cannotenrolreason', $e->errorcode);
        }

        // Control: the user who meets the conditions opens it.
        $this->setUser($allowed);
        $this->assertInstanceOf(enrol_form::class, $this->open((int) $instance->id, 'rightkey'));
    }
}
