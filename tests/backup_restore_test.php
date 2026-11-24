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
            // A new node may reference only a file its own user uploaded: the one who saves the tree.
            'userid' => (int) $USER->id,
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

    /**
     * Restores an instance as a backup taken before the cohort restriction became a condition carries it.
     *
     * @param \stdClass $course Target course.
     * @param int $cohortid The old customint5.
     * @param bool $samesite Whether the backup comes from this site.
     * @param string|null $tree The old instance's condition tree, if it had one.
     * @return \stdClass The restored instance.
     */
    protected function restore_old_instance(\stdClass $course, int $cohortid, bool $samesite, ?string $tree = null): \stdClass {
        global $DB;
        $task = $this->createMock(\restore_task::class);
        $task->method('get_target')->willReturn(\backup::TARGET_NEW_COURSE);
        $task->method('is_samesite')->willReturn($samesite);
        $step = $this->createMock(\restore_enrolments_structure_step::class);
        $step->method('get_task')->willReturn($task);

        $data = (object) [
            'courseid' => $course->id,
            'enrol' => 'conditional',
            'status' => ENROL_INSTANCE_ENABLED,
            'roleid' => $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST),
            'customint5' => $cohortid,
            'customint6' => 1,
            'customtext2' => $tree,
        ];
        $this->plugin->restore_instance($step, $data, $course, 4242);
        $instances = $DB->get_records('enrol', ['courseid' => $course->id, 'enrol' => 'conditional'], 'id DESC', '*', 0, 1);
        return reset($instances);
    }

    public function test_old_backup_keeps_the_cohort_restriction(): void {
        global $CFG;
        require_once($CFG->dirroot . '/cohort/lib.php');
        $cohort = $this->getDataGenerator()->create_cohort();
        $member = $this->getDataGenerator()->create_user();
        $outsider = $this->getDataGenerator()->create_user();
        cohort_add_member($cohort->id, $member->id);

        $restored = $this->restore_old_instance($this->getDataGenerator()->create_course(), (int) $cohort->id, true);

        // The restriction now lives in the tree, and the column nothing reads is cleared.
        $this->assertEquals(0, $restored->customint5);
        $tree = json_decode($restored->customtext2, true);
        $this->assertSame('cohort', $tree['children'][0]['type']);
        $this->assertSame((int) $cohort->id, $tree['children'][0]['config']['cohortid']);

        $this->setUser($outsider);
        try {
            $this->plugin->enrol_conditional($restored, (object) []);
            $this->fail('A user outside the cohort must be refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame('conditionsnotmetshort', $e->errorcode);
        }
        $this->assertFalse(is_enrolled(\context_course::instance($restored->courseid), $outsider));

        // Control: a member of the cohort enrols through the same instance.
        $this->setUser($member);
        $this->plugin->enrol_conditional($restored, (object) []);
        $this->assertTrue(is_enrolled(\context_course::instance($restored->courseid), $member));
    }

    public function test_old_backup_cohort_restriction_joins_the_existing_conditions(): void {
        $cohort = $this->getDataGenerator()->create_cohort();
        $listed = $this->getDataGenerator()->create_user();
        $tree = json_encode(['op' => 'or', 'children' => [
            ['type' => 'fake', 'config' => ['userids' => [(int) $listed->id]]],
        ]]);

        $restored = $this->restore_old_instance($this->getDataGenerator()->create_course(), (int) $cohort->id, true, $tree);

        // An OR of the old conditions stays one group, required together with the cohort.
        $root = json_decode($restored->customtext2, true);
        $this->assertSame('and', $root['op']);
        $this->assertCount(2, $root['children']);
        $this->setUser($listed);
        $result = $this->plugin->evaluate_conditions($restored, (int) $listed->id);
        $this->assertFalse($result->is_met());

        // Control: without the restriction the same tree is met by the listed user.
        $unrestricted = $this->restore_old_instance($this->getDataGenerator()->create_course(), 0, true, $tree);
        $this->assertTrue($this->plugin->evaluate_conditions($unrestricted, (int) $listed->id)->is_met());
    }

    public function test_old_backup_from_another_site_admits_nobody(): void {
        global $CFG;
        require_once($CFG->dirroot . '/cohort/lib.php');
        $cohort = $this->getDataGenerator()->create_cohort();
        $member = $this->getDataGenerator()->create_user();
        cohort_add_member($cohort->id, $member->id);

        $restored = $this->restore_old_instance($this->getDataGenerator()->create_course(), (int) $cohort->id, false);

        // The cohort id means nothing here: the condition comes back pointing at no cohort, so even a member of the
        // cohort that happens to have that id is refused.
        $this->assertEquals(0, $restored->customint5);
        $tree = json_decode($restored->customtext2, true);
        $this->assertSame(0, $tree['children'][0]['config']['cohortid']);
        $this->setUser($member);
        $this->resetDebugging();
        try {
            $this->plugin->enrol_conditional($restored, (object) []);
            $this->fail('Nobody may enrol through a restriction that cannot be evaluated.');
        } catch (\moodle_exception $e) {
            $this->assertSame('conditionsnotmetshort', $e->errorcode);
        }
        $this->resetDebugging();
        $this->assertFalse(is_enrolled(\context_course::instance($restored->courseid), $member));
    }

    public function test_old_backup_with_an_invalid_tree_keeps_the_cohort_restriction(): void {
        global $CFG;
        require_once($CFG->dirroot . '/cohort/lib.php');
        $cohort = $this->getDataGenerator()->create_cohort();
        $member = $this->getDataGenerator()->create_user();
        $outsider = $this->getDataGenerator()->create_user();
        cohort_add_member($cohort->id, $member->id);

        $course = $this->getDataGenerator()->create_course();
        $restored = $this->restore_old_instance($course, (int) $cohort->id, true, '{"op":"xor"}');

        // The tree did not parse: the restriction is the whole tree, so the column is cleared with nothing lost.
        $this->assertEquals(0, $restored->customint5);
        $tree = json_decode($restored->customtext2, true);
        $this->assertSame('and', $tree['op']);
        $this->assertCount(1, $tree['children']);
        $this->assertSame((int) $cohort->id, $tree['children'][0]['config']['cohortid']);

        $this->setUser($outsider);
        try {
            $this->plugin->enrol_conditional($restored, (object) []);
            $this->fail('A user outside the cohort must be refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame('conditionsnotmetshort', $e->errorcode);
        }
        $this->assertFalse(is_enrolled(\context_course::instance($restored->courseid), $outsider));

        // Control: a member of the cohort enrols through the same instance.
        $this->setUser($member);
        $this->plugin->enrol_conditional($restored, (object) []);
        $this->assertTrue(is_enrolled(\context_course::instance($restored->courseid), $member));
    }

    public function test_old_backup_with_an_invalid_tree_from_another_site_admits_nobody(): void {
        global $CFG;
        require_once($CFG->dirroot . '/cohort/lib.php');
        $cohort = $this->getDataGenerator()->create_cohort();
        $member = $this->getDataGenerator()->create_user();
        cohort_add_member($cohort->id, $member->id);

        $restored = $this->restore_old_instance($this->getDataGenerator()->create_course(), (int) $cohort->id, false, 'not json');

        $this->assertEquals(0, $restored->customint5);
        $tree = json_decode($restored->customtext2, true);
        $this->assertSame(0, $tree['children'][0]['config']['cohortid']);
        $this->setUser($member);
        $this->resetDebugging();
        try {
            $this->plugin->enrol_conditional($restored, (object) []);
            $this->fail('Nobody may enrol through a restriction that cannot be evaluated.');
        } catch (\moodle_exception $e) {
            $this->assertSame('conditionsnotmetshort', $e->errorcode);
        }
        $this->resetDebugging();
        $this->assertFalse(is_enrolled(\context_course::instance($restored->courseid), $member));
    }

    /**
     * Inputs of the rule the restore of an old backup and the upgrade step 2026110902 share, with each literal tree.
     *
     * @return array
     */
    public static function restriction_inputs(): array {
        $cohort = '{"type":"cohort","negate":false,"config":{"cohortid":7}}';
        $other = '{"type":"fake","negate":false,"config":{"userids":[1]}}';
        $alone = '{"op":"and","negate":false,"children":[' . $cohort . ']}';
        $deep = '{"op":"or","children":[{"op":"or","children":[{"op":"or","children":[{"op":"or","children":[{"op":"or",'
            . '"children":[' . $other . ']}]}]}]}]}';
        $wrapped = '{"op":"and","negate":false,"children":[{"op":"or","negate":false,"children":[{"op":"or","negate":false,'
            . '"children":[{"op":"or","negate":false,"children":[{"op":"or","negate":false,"children":[{"op":"or",'
            . '"negate":false,"children":[' . $other . ']}]}]}]}]},' . $cohort . ']}';
        return [
            'no tree' => [null, $alone],
            'empty string' => ['', $alone],
            'empty root' => ['{"op":"and","children":[]}', $alone],
            'and root' => [
                '{"op":"and","negate":false,"children":[' . $other . ']}',
                '{"op":"and","negate":false,"children":[' . $other . ',' . $cohort . ']}',
            ],
            'or root' => [
                '{"op":"or","negate":false,"children":[' . $other . ']}',
                '{"op":"and","negate":false,"children":[{"op":"or","negate":false,"children":[' . $other . ']},' . $cohort . ']}',
            ],
            'negated and root' => [
                '{"op":"and","negate":true,"children":[' . $other . ']}',
                '{"op":"and","negate":false,"children":[{"op":"and","negate":true,"children":[' . $other . ']},' . $cohort . ']}',
            ],
            'unknown operator' => ['{"op":"xor","children":[' . $other . ']}', $alone],
            'not json' => ['not json', $alone],
            'json scalar' => ['"text"', $alone],
            'tree at the depth limit' => [$deep, $wrapped],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('restriction_inputs')]
    public function test_the_restriction_rule_gives_one_literal_tree_per_input(?string $stored, string $expected): void {
        $this->assertSame($expected, \enrol_conditional\local\cohort_restriction::add_to_tree($stored, 7));
    }

    public function test_the_upgrade_step_uses_the_restriction_rule(): void {
        global $CFG;
        // The step calls the method the table above pins, instead of keeping a second copy of the rule.
        $source = file_get_contents($CFG->dirroot . '/enrol/conditional/db/upgrade.php');
        $start = strpos($source, 'if ($oldversion < 2026110902) {');
        $end = strpos($source, 'upgrade_plugin_savepoint(true, 2026110902');
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $step = substr($source, $start, $end - $start);
        $this->assertStringContainsString('cohort_restriction::add_to_tree(', $step);
        $this->assertStringNotContainsString("'children'", $step);
    }

    public function test_instance_without_the_old_restriction_is_restored_as_it_was(): void {
        $restored = $this->restore_old_instance($this->getDataGenerator()->create_course(), 0, true);
        $this->assertEmpty($restored->customtext2);
        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertTrue($this->plugin->can_self_enrol($restored));
    }

    /**
     * Backs up the course of create_course() and restores it as a new course after a change of the backup's enrolments.
     *
     * @param callable $edit Receives the XML of course/enrolments.xml and returns it changed.
     * @return \stdClass The restored instance.
     */
    protected function restore_after_editing_the_backup(callable $edit): \stdClass {
        global $CFG, $DB;
        [$course] = $this->create_course();
        $backupid = $this->backup((int) $course->id);
        $path = $CFG->tempdir . '/backup/' . $backupid . '/course/enrolments.xml';
        $xml = file_get_contents($path);
        $this->assertStringContainsString('<state id=', $xml);
        file_put_contents($path, $edit($xml));
        $newcourseid = \restore_dbops::create_new_course('Restored', 'RE' . random_string(4), 1);
        $this->restore($backupid, $newcourseid, \backup::TARGET_NEW_COURSE);
        return $DB->get_record('enrol', ['courseid' => $newcourseid, 'enrol' => 'conditional'], '*', MUST_EXIST);
    }

    public function test_state_of_an_enrolment_no_longer_suspended_is_not_restored(): void {
        global $DB;
        // Control: the unchanged backup restores its one open state.
        $control = $this->restore_after_editing_the_backup(fn(string $xml): string => $xml);
        $this->assertSame(1, $DB->count_records(enforcer::TABLE, ['instanceid' => $control->id]));

        // The enrolment comes back active while its record says suspended: the record is not restored.
        $changed = $this->restore_after_editing_the_backup(
            fn(string $xml): string => str_replace('<status>1</status>', '<status>0</status>', $xml)
        );
        $this->assertSame(0, $DB->count_records(enforcer::TABLE, ['instanceid' => $changed->id]));
    }

    public function test_second_open_state_of_an_enrolment_is_not_restored(): void {
        global $DB;
        // Two open records of the same enrolment: the backup is edited, since the engine never writes two.
        $doubled = $this->restore_after_editing_the_backup(function (string $xml): string {
            $this->assertSame(1, preg_match('#<state id="(\d+)">.*?</state>#s', $xml, $match));
            $copy = str_replace('<state id="' . $match[1] . '">', '<state id="' . ($match[1] + 1000) . '">', $match[0]);
            return str_replace($match[0], $match[0] . $copy, $xml);
        });
        $this->assertSame(1, $DB->count_records(enforcer::TABLE, ['instanceid' => $doubled->id]));
    }

    public function test_state_with_an_unknown_action_is_not_restored(): void {
        global $DB;
        $changed = $this->restore_after_editing_the_backup(
            fn(string $xml): string => str_replace('<action>suspend</action>', '<action>unknown</action>', $xml)
        );
        $this->assertSame(0, $DB->count_records(enforcer::TABLE, ['instanceid' => $changed->id]));
        // Control: the enrolment itself came back, still suspended, so only the record was refused.
        $this->assertSame(1, $DB->count_records('user_enrolments', ['enrolid' => $changed->id, 'status' => ENROL_USER_SUSPENDED]));
    }
}
