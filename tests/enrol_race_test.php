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
 * Tests of the windows between what enrol_conditional() reads and what it writes.
 *
 * @package    enrol_conditional
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_conditional;

use enrol_conditional_plugin;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/enrol/conditional/lib.php');
require_once($CFG->dirroot . '/group/lib.php');
require_once(__DIR__ . '/fixtures/probe_plugin.php');
require_once(__DIR__ . '/fixtures/recording_lock_factory.php');

/**
 * Same user in two requests, a group deleted under the enrolment, and the capacity lock around the write.
 *
 * The probe plugin stands in for the second request: its instance status is read before the first request
 * wrote, so it still finds the user not enrolled, which is what two web nodes see.
 *
 * @package    enrol_conditional
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(enrol_conditional_plugin::class)]
final class enrol_race_test extends \advanced_testcase {
    /** @var probe_plugin The plugin under test. */
    private probe_plugin $plugin;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        \core\plugininfo\enrol::enable_plugin('conditional', true);
        probe_plugin::reset();
        $this->plugin = new probe_plugin();
    }

    #[\Override]
    protected function tearDown(): void {
        global $CFG;
        probe_plugin::reset();
        unset($CFG->lock_factory);
        parent::tearDown();
    }

    /**
     * Creates an enabled instance.
     *
     * @param array $fields Other fields.
     * @return \stdClass
     */
    private function create_instance(array $fields = []): \stdClass {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $id = $this->plugin->add_instance($course, $fields + ['status' => ENROL_INSTANCE_ENABLED, 'customint6' => 1]);
        return $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Instances with and without a limit, so both write paths are held.
     *
     * @return array
     */
    public static function limits(): array {
        return ['without a limit' => [0], 'with a limit' => [5]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('limits')]
    public function test_second_request_of_the_same_user_does_not_restart_the_period(int $limit): void {
        global $DB;
        $instance = $this->create_instance(['enrolperiod' => 3600, 'customint3' => $limit]);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Control: the first request creates the enrolment.
        $this->plugin->enrol_conditional($instance, (object) []);
        $ue = $DB->get_record('user_enrolments', ['enrolid' => $instance->id, 'userid' => $user->id], '*', MUST_EXIST);
        // Make a restart visible: the stored period begins in the past.
        $DB->update_record('user_enrolments', (object) [
            'id' => $ue->id,
            'timestart' => $ue->timestart - 1000,
            'timeend' => $ue->timeend - 1000,
            'timemodified' => $ue->timemodified - 1000,
        ]);
        $before = $DB->get_record('user_enrolments', ['id' => $ue->id], '*', MUST_EXIST);
        probe_plugin::$log = [];

        probe_plugin::$stale = true;
        $this->plugin->enrol_conditional($instance, (object) []);

        $after = $DB->get_record('user_enrolments', ['id' => $ue->id], '*', MUST_EXIST);
        $this->assertEquals($before, $after);
        $this->assertNotContains('write', probe_plugin::$log);
        $this->assertSame(1, $DB->count_records('user_enrolments', ['enrolid' => $instance->id, 'userid' => $user->id]));
    }

    public function test_group_deleted_before_the_write_enrols_without_it(): void {
        $instance = $this->create_instance(['password' => 'coursekey', 'customint1' => 1]);
        $group = $this->getDataGenerator()->create_group(['courseid' => $instance->courseid, 'enrolmentkey' => 'groupkey']);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Another request deletes the group after the key was matched to it and before the enrolment is written.
        probe_plugin::$beforewrite = fn() => groups_delete_group($group->id);
        $this->plugin->enrol_conditional($instance, (object) ['enrolpassword' => 'groupkey']);

        $this->assertTrue(is_enrolled(\context_course::instance($instance->courseid), $user));
        $messages = array_map(fn($n) => $n->get_message(), \core\notification::fetch());
        $this->assertContains(get_string('groupgone', 'enrol_conditional'), $messages);
    }

    public function test_group_still_there_is_joined(): void {
        $instance = $this->create_instance(['password' => 'coursekey', 'customint1' => 1]);
        $group = $this->getDataGenerator()->create_group(['courseid' => $instance->courseid, 'enrolmentkey' => 'groupkey']);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Control of the test above: the same enrolment without the deletion joins the group and says nothing.
        $this->plugin->enrol_conditional($instance, (object) ['enrolpassword' => 'groupkey']);

        $this->assertTrue(groups_is_member($group->id, $user->id));
        $messages = array_map(fn($n) => $n->get_message(), \core\notification::fetch());
        $this->assertNotContains(get_string('groupgone', 'enrol_conditional'), $messages);
    }

    public function test_lock_is_held_across_the_enrolment_and_released_after(): void {
        global $CFG;
        $instance = $this->create_instance(['customint3' => 3]);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $CFG->lock_factory = '\\enrol_conditional\\recording_lock_factory';

        $this->plugin->enrol_conditional($instance, (object) []);

        $this->assertSame(['acquire capacity_' . $instance->id, 'write', 'release'], probe_plugin::$log);
    }

    public function test_lock_is_released_when_the_instance_is_full(): void {
        global $CFG;
        $instance = $this->create_instance(['customint3' => 1]);
        $this->plugin->enrol_user($instance, $this->getDataGenerator()->create_user()->id);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $CFG->lock_factory = '\\enrol_conditional\\recording_lock_factory';
        probe_plugin::$log = [];

        try {
            $this->plugin->enrol_conditional($instance, (object) []);
            $this->fail('The enrolment must be refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame('maxenrolledreached', $e->errorcode);
        }

        // The refusal is decided under the lock, which is released, and nothing was written.
        $this->assertSame(['acquire capacity_' . $instance->id, 'release'], probe_plugin::$log);
    }

    public function test_without_a_limit_the_user_is_enrolled_under_a_lock_of_their_own(): void {
        global $CFG;
        $instance = $this->create_instance(['customint3' => 0]);
        $first = $this->getDataGenerator()->create_user();
        $second = $this->getDataGenerator()->create_user();
        $CFG->lock_factory = '\\enrol_conditional\\recording_lock_factory';

        $this->setUser($first);
        $this->plugin->enrol_conditional($instance, (object) []);
        $this->setUser($second);
        $this->plugin->enrol_conditional($instance, (object) []);

        // Held across the write, released after it, and a resource per instance and user: one user never waits for another.
        $this->assertSame([
            'acquire enrol_' . $instance->id . '_' . $first->id, 'write', 'release',
            'acquire enrol_' . $instance->id . '_' . $second->id, 'write', 'release',
        ], probe_plugin::$log);
    }

    public function test_without_a_limit_a_user_enrolled_while_the_lock_was_awaited_is_not_enrolled_again(): void {
        global $CFG, $DB;
        $instance = $this->create_instance(['customint3' => 0, 'enrolperiod' => 3600]);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $CFG->lock_factory = '\\enrol_conditional\\recording_lock_factory';
        // The other request wrote while this one waited for the lock, so only a read taken under the lock sees it.
        probe_plugin::$afteracquire = function () use ($instance, $user): void {
            $other = new \enrol_conditional_plugin();
            $other->enrol_user($instance, $user->id, null, 111, 222);
        };

        $this->plugin->enrol_conditional($instance, (object) []);

        $this->assertNotContains('write', probe_plugin::$log);
        $ue = $DB->get_record('user_enrolments', ['enrolid' => $instance->id, 'userid' => $user->id], '*', MUST_EXIST);
        $this->assertEquals(111, $ue->timestart);
        $this->assertSame(['acquire enrol_' . $instance->id . '_' . $user->id, 'release'], probe_plugin::$log);
    }

    public function test_without_a_limit_the_lock_is_released_when_the_user_is_enrolled_already(): void {
        global $CFG;
        $instance = $this->create_instance(['customint3' => 0]);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->plugin->enrol_conditional($instance, (object) []);
        $CFG->lock_factory = '\\enrol_conditional\\recording_lock_factory';
        probe_plugin::$log = [];
        probe_plugin::$stale = true;

        $this->plugin->enrol_conditional($instance, (object) []);

        // Control of the test above: nothing was written and the lock is not left held.
        $this->assertSame(['acquire enrol_' . $instance->id . '_' . $user->id, 'release'], probe_plugin::$log);
    }
}
