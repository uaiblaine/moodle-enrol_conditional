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
 * Tests of what "this instance needs a key" means on every door that asks.
 *
 * @package    enrol_conditional
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_conditional;

use enrol_conditional\local\enrolment_key;
use enrol_conditional_plugin;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/enrol/conditional/lib.php');

/**
 * One definition of a required key, shared by the page, the listings, the app info and the enrolment itself.
 *
 * A stored key of '0' is falsy in PHP, which made the page offer the no-key button for an instance whose
 * enrolment then refused every request without it.
 *
 * @package    enrol_conditional
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(enrolment_key::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(enrol_conditional_plugin::class)]
final class enrolment_key_test extends \advanced_testcase {
    /** @var enrol_conditional_plugin The enrolment plugin. */
    private enrol_conditional_plugin $plugin;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        \core\plugininfo\enrol::enable_plugin('conditional', true);
        $this->plugin = enrol_get_plugin('conditional');
    }

    /**
     * Creates an enabled instance in a new course.
     *
     * @param string $password The key.
     * @return \stdClass
     */
    private function create_instance(string $password): \stdClass {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $id = $this->plugin->add_instance($course, [
            'status' => ENROL_INSTANCE_ENABLED,
            'customint6' => 1,
            'password' => $password,
        ]);
        return $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
    }

    public function test_is_required(): void {
        $this->assertFalse(enrolment_key::is_required((object) ['password' => '']));
        $this->assertFalse(enrolment_key::is_required((object) ['password' => null]));
        $this->assertFalse(enrolment_key::is_required((object) []));
        $this->assertTrue(enrolment_key::is_required((object) ['password' => '0']));
        $this->assertTrue(enrolment_key::is_required((object) ['password' => 'a']));
    }

    public function test_check_agrees_with_is_required(): void {
        $zero = $this->create_instance('0');
        $none = $this->create_instance('');

        $this->assertNotSame(enrolment_key::ACCEPTED, enrolment_key::check($zero, ''));
        $this->assertNotSame(enrolment_key::ACCEPTED, enrolment_key::check($zero, '1'));
        $this->assertSame(enrolment_key::ACCEPTED, enrolment_key::check($zero, '0'));
        // Control: an instance without a key accepts what is posted.
        $this->assertSame(enrolment_key::ACCEPTED, enrolment_key::check($none, ''));
    }

    public function test_enrol_page_asks_for_the_key(): void {
        global $PAGE;
        $zero = $this->create_instance('0');
        $none = $this->create_instance('');
        $this->setUser($this->getDataGenerator()->create_user());
        $PAGE->set_url('/enrol/index.php', ['id' => $zero->courseid]);

        $html = $this->plugin->enrol_page_hook($zero);
        $this->assertStringContainsString(get_string('enrolkeyrequired', 'enrol_conditional'), $html);
        $this->assertStringNotContainsString(get_string('nopassword', 'enrol_conditional'), $html);

        // Control: an instance without a key offers the plain button.
        $html = $this->plugin->enrol_page_hook($none);
        $this->assertStringContainsString(get_string('nopassword', 'enrol_conditional'), $html);
        $this->assertStringNotContainsString(get_string('enrolkeyrequired', 'enrol_conditional'), $html);
    }

    public function test_listing_icon_is_the_key_one(): void {
        $zero = $this->create_instance('0');
        $none = $this->create_instance('');
        $this->setUser($this->getDataGenerator()->create_user());

        $icons = $this->plugin->get_info_icons([$zero]);
        $this->assertCount(1, $icons);
        $this->assertSame('withkey', $icons[0]->pix);

        // Control: no key, the other icon.
        $icons = $this->plugin->get_info_icons([$none]);
        $this->assertCount(1, $icons);
        $this->assertSame('withoutkey', $icons[0]->pix);
    }

    public function test_enrol_info_asks_for_the_key(): void {
        $zero = $this->create_instance('0');
        $none = $this->create_instance('');
        $this->setUser($this->getDataGenerator()->create_user());

        $info = $this->plugin->get_enrol_info($zero);
        $this->assertTrue($info->status);
        $this->assertTrue(isset($info->requiredparam->enrolpassword));
        $this->assertSame('enrol_conditional_get_instance_info', $info->wsfunction);

        $info = $this->plugin->get_enrol_info($none);
        $this->assertFalse(isset($info->requiredparam));
        $this->assertFalse(isset($info->wsfunction));
    }

    public function test_enrolment_refuses_a_wrong_key_and_takes_zero(): void {
        global $DB;
        $zero = $this->create_instance('0');
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        foreach (['', '1', '00'] as $key) {
            try {
                $this->plugin->enrol_conditional($zero, (object) ['enrolpassword' => $key]);
                $this->fail("Key '$key' must be refused.");
            } catch (\moodle_exception $e) {
                $this->assertSame('passwordinvalid', $e->errorcode);
            }
            $this->assertFalse($DB->record_exists('user_enrolments', ['enrolid' => $zero->id, 'userid' => $user->id]));
        }

        $this->plugin->enrol_conditional($zero, (object) ['enrolpassword' => '0']);
        $this->assertTrue($DB->record_exists('user_enrolments', ['enrolid' => $zero->id, 'userid' => $user->id]));
    }

    public function test_csv_upload_refuses_a_key_longer_than_the_column(): void {
        $course = $this->getDataGenerator()->create_course();
        $this->plugin->set_config('usepasswordpolicy', false);

        $errors = $this->plugin->validate_enrol_plugin_data(['password' => str_repeat('a', 51)], (int) $course->id);
        $this->assertNotEmpty($errors);

        // Control: the longest key the instance form takes passes, and so does no key at all.
        $this->assertSame([], $this->plugin->validate_enrol_plugin_data(['password' => str_repeat('a', 50)], (int) $course->id));
        $this->assertSame([], $this->plugin->validate_enrol_plugin_data(['password' => ''], (int) $course->id));
    }

    /**
     * Instance form data that changes the key of an instance.
     *
     * @param \stdClass $instance The instance.
     * @param string $password The posted key.
     * @param int $groupkeys Whether group keys are on.
     * @return array
     */
    private function posted(\stdClass $instance, string $password, int $groupkeys = 0): array {
        return ['password' => $password, 'customint1' => $groupkeys] + (array) $instance;
    }

    /**
     * Errors the instance form gives for a posted key.
     *
     * @param \stdClass $instance The instance.
     * @param string $password The posted key.
     * @param int $groupkeys Whether group keys are on.
     * @return array
     */
    private function form_errors(\stdClass $instance, string $password, int $groupkeys = 0): array {
        $context = \context_course::instance($instance->courseid);
        return $this->plugin->edit_instance_validation($this->posted($instance, $password, $groupkeys), [], $instance, $context);
    }

    public function test_instance_form_applies_the_password_policy_to_a_key_of_zero(): void {
        global $CFG;
        $instance = $this->create_instance('');
        $CFG->passwordpolicy = 1;
        $CFG->minpasswordlength = 8;
        $CFG->minpassworddigits = 0;
        $CFG->minpasswordlower = 0;
        $CFG->minpasswordupper = 0;
        $CFG->minpasswordnonalphanum = 0;
        $CFG->maxconsecutiveidentchars = 0;
        $this->plugin->set_config('usepasswordpolicy', 1);
        $this->plugin->set_config('requirepassword', 0);

        $errors = $this->form_errors($instance, '0');
        $this->assertArrayHasKey('password', $errors);

        // Controls: a key that meets the policy and an empty key (none required) pass.
        $this->assertArrayNotHasKey('password', $this->form_errors($instance, 'longenough'));
        $this->assertArrayNotHasKey('password', $this->form_errors($instance, ''));
    }

    public function test_instance_form_refuses_a_key_of_zero_that_a_group_uses(): void {
        $instance = $this->create_instance('');
        $this->plugin->set_config('usepasswordpolicy', 0);
        $this->getDataGenerator()->create_group(['courseid' => $instance->courseid, 'enrolmentkey' => '0']);

        $errors = $this->form_errors($instance, '0', 1);
        $this->assertSame(get_string('passwordmatchesgroupkey', 'enrol_conditional'), $errors['password'] ?? null);

        // Controls: without group keys, or with another key, the same group is no collision.
        $this->assertArrayNotHasKey('password', $this->form_errors($instance, '0', 0));
        $this->assertArrayNotHasKey('password', $this->form_errors($instance, '1', 1));
    }

    public function test_course_upload_stores_a_key_of_zero(): void {
        $instance = $this->create_instance('');

        $updated = $this->plugin->update_enrol_plugin_data((int) $instance->courseid, ['password' => '0'], $instance);
        $this->assertSame('0', (string) $updated->password);

        // Control: an empty key in the upload leaves the stored one alone.
        $keep = $this->create_instance('stored');
        $updated = $this->plugin->update_enrol_plugin_data((int) $keep->courseid, ['password' => ''], $keep);
        $this->assertSame('stored', (string) $updated->password);
    }

    public function test_course_upload_applies_the_password_policy_to_a_key_of_zero(): void {
        global $CFG;
        $course = $this->getDataGenerator()->create_course();
        $CFG->passwordpolicy = 1;
        $CFG->minpasswordlength = 8;
        $CFG->minpassworddigits = 0;
        $CFG->minpasswordlower = 0;
        $CFG->minpasswordupper = 0;
        $CFG->minpasswordnonalphanum = 0;
        $CFG->maxconsecutiveidentchars = 0;
        $this->plugin->set_config('usepasswordpolicy', 1);

        $errors = $this->plugin->validate_enrol_plugin_data(['password' => '0'], (int) $course->id);
        $this->assertNotEmpty($errors);

        // Controls: a key that meets the policy and no key at all pass.
        $this->assertSame([], $this->plugin->validate_enrol_plugin_data(['password' => 'longenough'], (int) $course->id));
        $this->assertSame([], $this->plugin->validate_enrol_plugin_data(['password' => ''], (int) $course->id));
    }

    public function test_a_posted_key_is_one_unless_it_is_the_empty_string(): void {
        $this->assertTrue(enrolment_key::is_key('0'));
        $this->assertTrue(enrolment_key::is_key('a'));
        $this->assertFalse(enrolment_key::is_key(''));
    }
}
