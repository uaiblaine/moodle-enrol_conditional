<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace enrol_conditional;

use enrol_conditional_plugin;
use local_enrolconditions\condition\manager;
use local_enrolconditions\local\enforcer;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/enrol/conditional/lib.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/local/enrolconditions/tests/fixtures/fake_condition.php');

/**
 * Tests of backup and restore of conditional instances.
 *
 * @package    enrol_conditional
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(enrol_conditional_plugin::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\restore_enrol_conditional_plugin::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\backup_enrol_conditional_plugin::class)]
final class backup_restore_test extends \advanced_testcase {
    /** @var enrol_conditional_plugin */
    protected enrol_conditional_plugin $plugin;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        \core\plugininfo\enrol::enable_plugin('conditional', true);
        manager::reset_caches();
        manager::register_for_testing('fake', new \enrolcond_fake\condition());
        $this->plugin = enrol_get_plugin('conditional');
    }

    #[\Override]
    protected function tearDown(): void {
        manager::reset_caches();
        parent::tearDown();
    }

    /**
     * Backs up a course with users.
     *
     * @param int $courseid
     * @return string Id of the extracted backup, for restore_controller.
     */
    protected function backup(int $courseid): string {
        global $CFG, $USER;
        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $courseid,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_value(true);
        $bc->execute_plan();
        $file = $bc->get_results()['backup_destination'];
        $backupid = 'test-' . $courseid . '-' . random_string(6);
        $file->extract_to_pathname(get_file_packer('application/vnd.moodle.backup'), $CFG->tempdir . '/backup/' . $backupid);
        $bc->destroy();
        return $backupid;
    }

    /**
     * Restores a backup into a course.
     *
     * @param string $backupid
     * @param int $courseid Target course.
     * @param int $target One of the backup::TARGET_ constants.
     */
    protected function restore(string $backupid, int $courseid, int $target): void {
        global $USER;
        $rc = new \restore_controller(
            $backupid,
            $courseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            $target
        );
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();
    }

    /**
     * Backs up a course with users and restores it as a new course.
     *
     * @param int $courseid
     * @param bool $othersite Whether to simulate a backup from another site.
     * @return int Id of the new course.
     */
    protected function backup_and_restore(int $courseid, bool $othersite = false): int {
        $backupid = $this->backup($courseid);
        if ($othersite) {
            set_config('siteidentifier', random_string(32) . 'other');
        }
        $newcourseid = \restore_dbops::create_new_course('Restored', 'R' . random_string(4), 1);
        $this->restore($backupid, $newcourseid, \backup::TARGET_NEW_COURSE);
        return $newcourseid;
    }

    /**
     * Creates a course with a suspending instance and a cohort condition, and a suspended and an active user.
     *
     * @return array [course, instance, suspended user, active user, cohort]
     */
    protected function create_course(): array {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $cohort = $this->getDataGenerator()->create_cohort();
        $suspended = $this->getDataGenerator()->create_user();
        $active = $this->getDataGenerator()->create_user();
        $tree = json_encode(['op' => 'or', 'children' => [
            ['type' => 'fake', 'config' => ['userids' => [(int) $active->id]]],
            ['type' => 'cohort', 'config' => ['cohortid' => (int) $cohort->id]],
        ]]);
        $id = $this->plugin->add_instance($course, [
            'status' => ENROL_INSTANCE_ENABLED,
            'customtext2' => $tree,
            'conditionaction' => enrol_conditional_plugin::ACTION_SUSPEND,
            'conditionreactivate' => 1,
            'checkmodelogin' => 1,
        ]);
        $instance = $DB->get_record('enrol', ['id' => $id]);
        $this->plugin->enrol_user($instance, $suspended->id);
        $this->plugin->enrol_user($instance, $active->id);
        (new enforcer())->check_user($instance, $suspended->id);
        return [$course, $instance, $suspended, $active, $cohort];
    }

    public function test_same_site(): void {
        global $DB;
        [$course, $instance, $suspended, $active, $cohort] = $this->create_course();

        $newcourseid = $this->backup_and_restore((int) $course->id);
        $restored = $DB->get_record('enrol', ['courseid' => $newcourseid, 'enrol' => 'conditional'], '*', MUST_EXIST);

        $this->assertSame($instance->customtext2, $restored->customtext2);
        $this->assertEquals($instance->customint7, $restored->customint7);
        $this->assertEquals($instance->customint8, $restored->customint8);

        $ue = $DB->get_record('user_enrolments', ['enrolid' => $restored->id, 'userid' => $suspended->id], '*', MUST_EXIST);
        $this->assertEquals(ENROL_USER_SUSPENDED, $ue->status);
        $states = $DB->get_records(enforcer::TABLE, ['instanceid' => $restored->id]);
        $this->assertCount(1, $states);
        $state = reset($states);
        $this->assertEquals($ue->id, $state->userenrolmentid);
        $this->assertTrue(enforcer::owns($state, $ue));

        // The restored suspension still belongs to the plugin, so it is reactivated.
        require_once($GLOBALS["CFG"]->dirroot . "/cohort/lib.php");
        cohort_add_member($cohort->id, $suspended->id);
        $outcome = (new enforcer())->check_user($restored, $suspended->id);
        $this->assertSame(enforcer::OUTCOME_REACTIVATED, $outcome);
    }

    public function test_file_of_usernames(): void {
        global $DB, $USER;
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user(['username' => 'listed']);
        $fileid = 4242;
        get_file_storage()->create_file_from_string([
            'contextid' => \context_course::instance($course->id)->id,
            'component' => 'enrolcond_userfile',
            'filearea' => 'usernames',
            'itemid' => $fileid,
            'filepath' => '/',
            'filename' => 'list.csv',
        ], "listed\n");
        $tree = json_encode(['op' => 'and', 'children' => [
            ['type' => 'userfile', 'config' => ['fileid' => $fileid, 'filename' => 'list.csv', 'count' => 1]],
        ]]);
        $this->plugin->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED, 'customtext2' => $tree]);

        // From the same site the file comes back with the course and the list is met.
        $newcourseid = $this->backup_and_restore((int) $course->id);
        $restored = $DB->get_record('enrol', ['courseid' => $newcourseid, 'enrol' => 'conditional'], '*', MUST_EXIST);
        $result = $this->plugin->evaluate_conditions($restored, (int) $user->id);
        $this->assertTrue($result->is_met());

        // Control: from another site the list names other people, so the condition comes back without its file.
        // It cannot be evaluated: unknown, which the engine reports to developers, never "not met".
        $othercourseid = $this->backup_and_restore((int) $course->id, true);
        $other = $DB->get_record('enrol', ['courseid' => $othercourseid, 'enrol' => 'conditional'], '*', MUST_EXIST);
        $otherresult = $this->plugin->evaluate_conditions($other, (int) $user->id);
        $this->assertTrue($otherresult->is_unknown());
        $this->assertFalse($otherresult->is_met());
        $this->assertDebuggingCalled();
    }

    public function test_other_site(): void {
        global $DB;
        [$course, , $suspended, $active] = $this->create_course();

        $newcourseid = $this->backup_and_restore((int) $course->id, true);
        $restored = $DB->get_record('enrol', ['courseid' => $newcourseid, 'enrol' => 'conditional'], '*', MUST_EXIST);

        $tree = json_decode($restored->customtext2, true);
        $this->assertSame(['userids' => [(int) $active->id]], $tree['children'][0]['config']);
        $this->assertSame(['cohortid' => 0], $tree['children'][1]['config']);
    }

    public function test_without_users(): void {
        global $DB, $CFG, $USER;
        [$course] = $this->create_course();
        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_value(false);
        $bc->execute_plan();
        $file = $bc->get_results()['backup_destination'];
        $backupid = 'nousers-' . random_string(6);
        $file->extract_to_pathname(get_file_packer('application/vnd.moodle.backup'), $CFG->tempdir . '/backup/' . $backupid);
        $bc->destroy();
        $newcourseid = \restore_dbops::create_new_course('Restored', 'RN' . random_string(4), 1);
        $rc = new \restore_controller(
            $backupid,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $rc->get_plan()->get_setting('enrolments')->set_value(\backup::ENROL_ALWAYS);
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        $restored = $DB->get_record('enrol', ['courseid' => $newcourseid, 'enrol' => 'conditional'], '*', MUST_EXIST);
        $this->assertNotEmpty($restored->customtext2);
        $this->assertSame(0, $DB->count_records(enforcer::TABLE, ['instanceid' => $restored->id]));
    }

    public function test_invalid_tree_is_kept_and_fails_closed(): void {
        global $DB;
        [$course, $instance] = $this->create_course();
        $DB->set_field('enrol', 'customtext2', '{"op":"xor"}', ['id' => $instance->id]);
        $DB->set_field('enrol', 'customint6', 1, ['id' => $instance->id]);

        $newcourseid = $this->backup_and_restore((int) $course->id);
        $restored = $DB->get_record('enrol', ['courseid' => $newcourseid, 'enrol' => 'conditional'], '*', MUST_EXIST);
        $this->assertSame('{"op":"xor"}', $restored->customtext2);

        // The invalid tree evaluates to unknown: the enrol page refuses and so does the enrolment itself.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $status = $this->plugin->can_self_enrol($restored);
        $this->assertDebuggingCalled();
        $this->assertIsString($status);
        $this->assertStringContainsString(get_string('failureunknown', 'local_enrolconditions'), $status);
        try {
            $this->plugin->enrol_conditional($restored, (object) []);
            $this->fail('The enrolment must be refused.');
        } catch (\moodle_exception $e) {
            $this->assertDebuggingCalled();
            $this->assertSame('conditionsnotmetshort', $e->errorcode);
        }
        $this->assertFalse($DB->record_exists('user_enrolments', ['enrolid' => $restored->id, 'userid' => $user->id]));

        // Control: the same restored instance with a valid tree enrols the user.
        $DB->set_field('enrol', 'customtext2', null, ['id' => $restored->id]);
        $restored = $DB->get_record('enrol', ['id' => $restored->id], '*', MUST_EXIST);
        $this->assertTrue($this->plugin->can_self_enrol($restored));
        $this->plugin->enrol_conditional($restored, (object) []);
        $this->assertTrue($DB->record_exists('user_enrolments', ['enrolid' => $restored->id, 'userid' => $user->id]));
    }

    public function test_other_site_restores_no_state(): void {
        global $DB;
        [$course, , $suspended] = $this->create_course();

        $newcourseid = $this->backup_and_restore((int) $course->id, true);
        $restored = $DB->get_record('enrol', ['courseid' => $newcourseid, 'enrol' => 'conditional'], '*', MUST_EXIST);

        // The restore created the suspended enrolment, but the suspension was decided on another site.
        $ue = $DB->get_record('user_enrolments', ['enrolid' => $restored->id, 'userid' => $suspended->id], '*', MUST_EXIST);
        $this->assertEquals(ENROL_USER_SUSPENDED, $ue->status);
        $this->assertSame(0, $DB->count_records(enforcer::TABLE, ['instanceid' => $restored->id]));
    }

    public function test_merge_into_existing_course_keeps_hand_suspension(): void {
        global $DB;
        [$course, $instance, $suspended] = $this->create_course();
        $backupid = $this->backup((int) $course->id);

        // A course whose instance the restore merges into (same status and role), where a teacher suspended the
        // same user by hand, and whose conditions the user meets, so an owned suspension would be reactivated.
        $target = $this->getDataGenerator()->create_course();
        $id = $this->plugin->add_instance($target, [
            'status' => $instance->status,
            'roleid' => $instance->roleid,
            'customtext2' => json_encode(['op' => 'and', 'children' => [
                ['type' => 'fake', 'config' => ['userids' => [(int) $suspended->id]]],
            ]]),
            'conditionaction' => enrol_conditional_plugin::ACTION_SUSPEND,
            'conditionreactivate' => 1,
        ]);
        $targetinstance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
        $this->plugin->enrol_user($targetinstance, $suspended->id);
        $this->plugin->update_user_enrol($targetinstance, $suspended->id, ENROL_USER_SUSPENDED);

        $this->restore($backupid, (int) $target->id, \backup::TARGET_EXISTING_ADDING);

        $this->assertSame(1, $DB->count_records('enrol', ['courseid' => $target->id, 'enrol' => 'conditional']));
        $ue = $DB->get_record('user_enrolments', ['enrolid' => $targetinstance->id, 'userid' => $suspended->id], '*', MUST_EXIST);
        $this->assertEquals(ENROL_USER_SUSPENDED, $ue->status);
        $this->assertSame(0, $DB->count_records(enforcer::TABLE, ['instanceid' => $targetinstance->id]));

        $outcome = (new enforcer())->check_user($targetinstance, (int) $suspended->id);
        $this->assertSame(enforcer::OUTCOME_NOTHING, $outcome);
        $this->assertEquals(
            ENROL_USER_SUSPENDED,
            $DB->get_field('user_enrolments', 'status', ['id' => $ue->id])
        );
    }

    public function test_merge_restores_state_of_enrolment_it_created(): void {
        global $DB;
        [$course, $instance, $suspended] = $this->create_course();
        $backupid = $this->backup((int) $course->id);

        // Control of the test above: the same merge, without the user in the target, creates the enrolment and
        // keeps the ownership of its suspension.
        $target = $this->getDataGenerator()->create_course();
        $id = $this->plugin->add_instance($target, [
            'status' => $instance->status,
            'roleid' => $instance->roleid,
            'customtext2' => json_encode(['op' => 'and', 'children' => [
                ['type' => 'fake', 'config' => ['userids' => [(int) $suspended->id]]],
            ]]),
            'conditionaction' => enrol_conditional_plugin::ACTION_SUSPEND,
            'conditionreactivate' => 1,
        ]);
        $targetinstance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);

        $this->restore($backupid, (int) $target->id, \backup::TARGET_EXISTING_ADDING);

        $this->assertSame(1, $DB->count_records(enforcer::TABLE, ['instanceid' => $targetinstance->id]));
        $outcome = (new enforcer())->check_user($targetinstance, (int) $suspended->id);
        $this->assertSame(enforcer::OUTCOME_REACTIVATED, $outcome);
    }
}
