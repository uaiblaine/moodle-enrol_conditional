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
 * Tests of the capability and session checks that the plugin's own methods make.
 *
 * @package    enrol_conditional
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_conditional;

use enrol_conditional_plugin;
use local_enrolconditions\condition\manager;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/enrol/conditional/lib.php');
require_once($CFG->dirroot . '/enrol/locallib.php');
require_once($CFG->dirroot . '/local/enrolconditions/tests/fixtures/fake_condition.php');

/**
 * Each refusal sits beside the same call accepted, the one thing that differs being the capability or the session.
 *
 * @package    enrol_conditional
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(enrol_conditional_plugin::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\enrol_conditional_editselectedusers_operation::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\enrol_conditional_deleteselectedusers_operation::class)]
final class capability_gates_test extends \advanced_testcase {
    /** @var enrol_conditional_plugin The enrolment plugin. */
    private enrol_conditional_plugin $plugin;

    /** @var \stdClass Course of the tests. */
    private \stdClass $course;

    /** @var \context_course Its context. */
    private \context_course $context;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        \core\plugininfo\enrol::enable_plugin('conditional', true);
        manager::reset_caches();
        manager::register_for_testing('fake', new \enrolcond_fake\condition());
        $this->plugin = enrol_get_plugin('conditional');
        $this->course = $this->getDataGenerator()->create_course();
        $this->context = \context_course::instance($this->course->id);
    }

    #[\Override]
    protected function tearDown(): void {
        manager::reset_caches();
        parent::tearDown();
    }

    /**
     * Creates an instance of this method in the course.
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
     * Sets a capability of a role in the course and reloads the current user's access.
     *
     * @param string $capability
     * @param int $permission CAP_ALLOW or CAP_PROHIBIT.
     * @param string $shortname Role.
     * @param \stdClass $user The user whose session is reloaded.
     */
    private function set_permission(string $capability, int $permission, string $shortname, \stdClass $user): void {
        global $DB;
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => $shortname], MUST_EXIST);
        assign_capability($capability, $permission, $roleid, $this->context->id, true);
        $this->setUser($user);
    }

    /**
     * A teacher of the course, with every capability the roles of the plugin give by default.
     *
     * @return \stdClass
     */
    private function create_teacher(): \stdClass {
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->setUser($teacher);
        return $teacher;
    }

    public function test_enrolment_needs_the_enrolself_capability(): void {
        global $DB;
        $instance = $this->create_instance();
        $user = $this->getDataGenerator()->create_user();
        $this->set_permission('enrol/conditional:enrolself', CAP_PROHIBIT, 'user', $user);

        try {
            $this->plugin->enrol_conditional($instance, (object) []);
            $this->fail('The enrolment must be refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame('canntenrol', $e->errorcode);
        }
        $this->assertFalse($DB->record_exists('user_enrolments', ['enrolid' => $instance->id, 'userid' => $user->id]));

        // Control: with the capability the same call enrols.
        $this->set_permission('enrol/conditional:enrolself', CAP_ALLOW, 'user', $user);
        $this->plugin->enrol_conditional($instance, (object) []);
        $this->assertTrue($DB->record_exists('user_enrolments', ['enrolid' => $instance->id, 'userid' => $user->id]));
    }

    public function test_page_does_not_offer_enrolment_without_the_capability(): void {
        $instance = $this->create_instance();
        $user = $this->getDataGenerator()->create_user();
        $this->set_permission('enrol/conditional:enrolself', CAP_PROHIBIT, 'user', $user);

        $this->assertSame(get_string('canntenrol', 'enrol_conditional'), $this->plugin->can_self_enrol($instance));
        $this->assertFalse($this->plugin->show_enrolme_link($instance));
        $this->assertSame([], $this->plugin->get_info_icons([$instance]));

        // Control: with the capability the page offers it.
        $this->set_permission('enrol/conditional:enrolself', CAP_ALLOW, 'user', $user);
        $this->assertTrue($this->plugin->can_self_enrol($instance));
        $this->assertTrue($this->plugin->show_enrolme_link($instance));
    }

    public function test_guests_are_refused_before_the_conditions_are_evaluated(): void {
        $allowed = $this->getDataGenerator()->create_user();
        $instance = $this->create_instance(['customtext2' => json_encode(['op' => 'and', 'children' => [
            ['type' => 'fake', 'config' => ['userids' => [(int) $allowed->id]]],
        ]])]);

        // Core never grants a write capability to the guest account, so it stops at the capability: its answer is the
        // plain refusal, not the list of conditions a person could still meet.
        $this->setGuestUser();
        $this->assertSame(get_string('canntenrol', 'enrol_conditional'), $this->plugin->can_self_enrol($instance, false));

        // Control: a logged in user outside the list is told the conditions, with the very same tree.
        $this->setUser($this->getDataGenerator()->create_user());
        $status = $this->plugin->can_self_enrol($instance, false);
        $this->assertIsString($status);
        $this->assertStringContainsString(get_string('conditionsnotmet', 'enrol_conditional'), $status);
    }

    public function test_enrol_button_needs_the_session_key(): void {
        global $DB, $PAGE;
        $instance = $this->create_instance();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $PAGE->set_url('/enrol/index.php', ['id' => $this->course->id]);

        // A link followed from another site carries no session key, or a wrong one.
        $_GET['action'] = 'enrol';
        $_GET['sesskey'] = 'notthesesskey';
        $html = $this->plugin->enrol_page_hook($instance);
        $this->assertNotSame('', $html);
        $this->assertFalse($DB->record_exists('user_enrolments', ['enrolid' => $instance->id, 'userid' => $user->id]));

        // Without any session key the page refuses to go on.
        unset($_GET['sesskey']);
        try {
            $this->plugin->enrol_page_hook($instance);
            $this->fail('A request without a session key must be refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame('missingparam', $e->errorcode);
        }
        $this->assertFalse($DB->record_exists('user_enrolments', ['enrolid' => $instance->id, 'userid' => $user->id]));

        // Control: the link the page itself builds enrols.
        $_GET['sesskey'] = sesskey();
        $this->assertSame('', $this->plugin->enrol_page_hook($instance));
        $this->assertTrue($DB->record_exists('user_enrolments', ['enrolid' => $instance->id, 'userid' => $user->id]));
    }

    public function test_adding_an_instance_needs_both_capabilities(): void {
        $teacher = $this->create_teacher();
        $this->assertTrue($this->plugin->can_add_instance($this->course->id));

        $this->set_permission('moodle/course:enrolconfig', CAP_PROHIBIT, 'editingteacher', $teacher);
        $this->assertFalse($this->plugin->can_add_instance($this->course->id));

        $this->set_permission('moodle/course:enrolconfig', CAP_ALLOW, 'editingteacher', $teacher);
        $this->assertTrue($this->plugin->can_add_instance($this->course->id));
        $this->set_permission('enrol/conditional:config', CAP_PROHIBIT, 'editingteacher', $teacher);
        $this->assertFalse($this->plugin->can_add_instance($this->course->id));
    }

    public function test_deleting_an_instance_needs_the_config_capability(): void {
        $instance = $this->create_instance();
        $teacher = $this->create_teacher();
        $this->assertTrue($this->plugin->can_delete_instance($instance));

        $this->set_permission('enrol/conditional:config', CAP_PROHIBIT, 'editingteacher', $teacher);
        $this->assertFalse($this->plugin->can_delete_instance($instance));
    }

    public function test_hiding_and_showing_needs_the_config_capability(): void {
        $instance = $this->create_instance();
        $teacher = $this->create_teacher();
        $this->assertTrue($this->plugin->can_hide_show_instance($instance));

        $this->set_permission('enrol/conditional:config', CAP_PROHIBIT, 'editingteacher', $teacher);
        $this->assertFalse($this->plugin->can_hide_show_instance($instance));
    }

    public function test_enabling_needs_the_key_the_site_requires(): void {
        $this->create_teacher();
        $this->plugin->set_config('requirepassword', 1);
        $nokey = $this->create_instance(['status' => ENROL_INSTANCE_DISABLED]);
        $withkey = $this->create_instance(['status' => ENROL_INSTANCE_DISABLED, 'password' => 'somekey']);

        $this->assertFalse($this->plugin->can_hide_show_instance($nokey));
        $this->assertTrue($this->plugin->can_hide_show_instance($withkey));

        // Control: when the site does not require a key, the instance without one can be enabled.
        $this->plugin->set_config('requirepassword', 0);
        $this->assertTrue($this->plugin->can_hide_show_instance($nokey));
    }

    public function test_enabling_needs_a_key_that_meets_the_password_policy(): void {
        global $CFG;
        $this->create_teacher();
        $CFG->passwordpolicy = 1;
        $CFG->minpasswordlength = 8;
        $CFG->minpassworddigits = 1;
        $CFG->minpasswordlower = 1;
        $CFG->minpasswordupper = 1;
        $CFG->minpasswordnonalphanum = 1;
        $CFG->maxconsecutiveidentchars = 0;
        $this->plugin->set_config('usepasswordpolicy', 1);
        $weak = $this->create_instance(['status' => ENROL_INSTANCE_DISABLED, 'password' => 'weak']);
        $strong = $this->create_instance(['status' => ENROL_INSTANCE_DISABLED, 'password' => 'Str0ng-enough']);

        $this->assertFalse($this->plugin->can_hide_show_instance($weak));
        $this->assertTrue($this->plugin->can_hide_show_instance($strong));

        // Control: without the site's policy, the weak key can be enabled.
        $this->plugin->set_config('usepasswordpolicy', 0);
        $this->assertTrue($this->plugin->can_hide_show_instance($weak));
    }

    public function test_enabling_accepts_a_key_of_zero_where_a_key_is_required(): void {
        $this->create_teacher();
        $this->plugin->set_config('requirepassword', 1);
        $zero = $this->create_instance(['status' => ENROL_INSTANCE_DISABLED, 'password' => '0']);

        // A key of '0' is a key: the site's requirement is met.
        $this->assertTrue($this->plugin->can_hide_show_instance($zero));

        // Control: an empty key still is not one.
        $none = $this->create_instance(['status' => ENROL_INSTANCE_DISABLED, 'password' => '']);
        $this->assertFalse($this->plugin->can_hide_show_instance($none));
    }

    public function test_enabling_applies_the_password_policy_to_a_key_of_zero(): void {
        global $CFG;
        $this->create_teacher();
        $CFG->passwordpolicy = 1;
        $CFG->minpasswordlength = 8;
        $CFG->minpassworddigits = 0;
        $CFG->minpasswordlower = 0;
        $CFG->minpasswordupper = 0;
        $CFG->minpasswordnonalphanum = 0;
        $CFG->maxconsecutiveidentchars = 0;
        $this->plugin->set_config('usepasswordpolicy', 1);
        $zero = $this->create_instance(['status' => ENROL_INSTANCE_DISABLED, 'password' => '0']);

        $this->assertFalse($this->plugin->can_hide_show_instance($zero));

        // Control: without the site's policy the same key can be enabled.
        $this->plugin->set_config('usepasswordpolicy', 0);
        $this->assertTrue($this->plugin->can_hide_show_instance($zero));
    }

    public function test_teachers_may_unenrol_and_manage_the_enrolments(): void {
        global $DB;
        $instance = $this->create_instance();
        $user = $this->getDataGenerator()->create_user();
        $this->plugin->enrol_user($instance, $user->id);
        $ue = $DB->get_record('user_enrolments', ['enrolid' => $instance->id, 'userid' => $user->id], '*', MUST_EXIST);

        $this->assertTrue($this->plugin->allow_unenrol($instance));
        $this->assertTrue($this->plugin->allow_unenrol_user($instance, $ue));
        $this->assertTrue($this->plugin->allow_manage($instance));
    }

    /**
     * The participants page's view of the users of this method: their enrolments, as the bulk operations get them.
     *
     * @param \stdClass $instance
     * @return array [manager, users with their enrolments]
     */
    private function select_users(\stdClass $instance): array {
        global $PAGE;
        $user = $this->getDataGenerator()->create_user();
        $this->plugin->enrol_user($instance, $user->id, null, 100, 200);
        $manager = new \course_enrolment_manager($PAGE, $this->course);
        return [$manager, $manager->get_users_enrolments([$user->id]), $user];
    }

    public function test_bulk_delete_needs_the_unenrol_capability(): void {
        global $DB;
        $instance = $this->create_instance();
        $teacher = $this->create_teacher();
        [$manager, $selected, $user] = $this->select_users($instance);
        $operation = new \enrol_conditional_deleteselectedusers_operation($manager, $this->plugin);

        $this->set_permission('enrol/conditional:unenrol', CAP_PROHIBIT, 'editingteacher', $teacher);
        $this->assertFalse($operation->process($manager, $selected, new \stdClass()));
        $this->assertTrue($DB->record_exists('user_enrolments', ['enrolid' => $instance->id, 'userid' => $user->id]));

        // Control: with the capability the same selection is removed.
        $this->set_permission('enrol/conditional:unenrol', CAP_ALLOW, 'editingteacher', $teacher);
        $this->assertTrue($operation->process($manager, $selected, new \stdClass()));
        $this->assertFalse($DB->record_exists('user_enrolments', ['enrolid' => $instance->id, 'userid' => $user->id]));
    }

    public function test_bulk_edit_needs_the_manage_capability(): void {
        global $DB;
        $instance = $this->create_instance();
        $teacher = $this->create_teacher();
        [$manager, $selected, $user] = $this->select_users($instance);
        $operation = new \enrol_conditional_editselectedusers_operation($manager, $this->plugin);
        $properties = (object) ['status' => ENROL_USER_SUSPENDED, 'timestart' => 0, 'timeend' => 0];

        $this->set_permission('enrol/conditional:manage', CAP_PROHIBIT, 'editingteacher', $teacher);
        $this->assertFalse($operation->process($manager, $selected, $properties));
        $status = $DB->get_field('user_enrolments', 'status', ['enrolid' => $instance->id, 'userid' => $user->id]);
        $this->assertEquals(ENROL_USER_ACTIVE, $status);

        // Control: with the capability the same selection is suspended.
        $this->set_permission('enrol/conditional:manage', CAP_ALLOW, 'editingteacher', $teacher);
        $this->assertTrue($operation->process($manager, $selected, $properties));
        $status = $DB->get_field('user_enrolments', 'status', ['enrolid' => $instance->id, 'userid' => $user->id]);
        $this->assertEquals(ENROL_USER_SUSPENDED, $status);
    }

    public function test_participants_page_offers_the_operations_the_user_may_run(): void {
        global $PAGE;
        $this->create_instance();
        $teacher = $this->create_teacher();
        $manager = new \course_enrolment_manager($PAGE, $this->course);

        $this->assertSame(
            ['editselectedusers', 'deleteselectedusers'],
            array_keys($this->plugin->get_bulk_operations($manager))
        );

        $this->set_permission('enrol/conditional:manage', CAP_PROHIBIT, 'editingteacher', $teacher);
        $this->assertSame(['deleteselectedusers'], array_keys($this->plugin->get_bulk_operations($manager)));
        $this->set_permission('enrol/conditional:manage', CAP_ALLOW, 'editingteacher', $teacher);

        $this->set_permission('enrol/conditional:unenrol', CAP_PROHIBIT, 'editingteacher', $teacher);
        $this->assertSame(['editselectedusers'], array_keys($this->plugin->get_bulk_operations($manager)));
    }

    public function test_condition_editor_is_given_the_method_and_the_instance(): void {
        global $PAGE;
        $this->setAdminUser();
        $instance = $this->create_instance();
        $PAGE->set_url('/enrol/editinstance.php', [
            'courseid' => $this->course->id,
            'id' => $instance->id,
            'type' => 'conditional',
        ]);
        $PAGE->set_context($this->context);

        $mform = new \MoodleQuickForm('test', 'post', '');
        $this->plugin->edit_instance_form($instance, $mform, $this->context);
        $config = $this->editor_config($mform);
        $this->assertSame('conditional', $config['enrol']);
        $this->assertSame((int) $instance->id, $config['instanceid']);
        $this->assertSame((int) $this->context->id, $config['contextid']);
    }

    /**
     * The configuration the condition editor hands to its script.
     *
     * @param \MoodleQuickForm $mform
     * @return array
     */
    private function editor_config(\MoodleQuickForm $mform): array {
        $dom = new \DOMDocument();
        $element = $mform->getElement(enrol_conditional_plugin::FIELD_CONDITIONS . '_editor');
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $element->toHtml());
        libxml_use_internal_errors($previous);
        $container = $dom->getElementsByTagName('div')->item(0);
        $config = json_decode($container->getAttribute('data-config'), true);
        $this->assertIsArray($config);
        return $config;
    }
}
