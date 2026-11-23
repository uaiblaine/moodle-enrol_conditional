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

use enrol_conditional_plugin;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/enrol/conditional/lib.php');

/**
 * Tests of the "Max enrolled users" limit at the moment of the enrolment.
 *
 * Two web nodes can both find a free place on the enrol page; the enrolment re-counts under a lock.
 *
 * @package    enrol_conditional
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(enrol_conditional_plugin::class)]
final class capacity_test extends \advanced_testcase {
    /** @var enrol_conditional_plugin The enrolment plugin. */
    private enrol_conditional_plugin $plugin;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        \core\plugininfo\enrol::enable_plugin('conditional', true);
        $this->plugin = enrol_get_plugin('conditional');
    }

    #[\Override]
    protected function tearDown(): void {
        global $CFG;
        unset($CFG->lock_factory);
        parent::tearDown();
    }

    /**
     * Creates an enabled instance with a limit.
     *
     * @param int $max Max enrolled users, 0 for none.
     * @return \stdClass
     */
    private function create_instance(int $max): \stdClass {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $id = $this->plugin->add_instance($course, [
            'status' => ENROL_INSTANCE_ENABLED,
            'customint6' => 1,
            'customint3' => $max,
        ]);
        return $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Whether a user is enrolled through the instance.
     *
     * @param \stdClass $instance
     * @param int $userid
     * @return bool
     */
    private function enrolled(\stdClass $instance, int $userid): bool {
        global $DB;
        return $DB->record_exists('user_enrolments', ['enrolid' => $instance->id, 'userid' => $userid]);
    }

    public function test_full_instance_refuses_at_enrolment(): void {
        $instance = $this->create_instance(1);
        // Another node enrolled somebody after this user's enrol page found a free place.
        $this->plugin->enrol_user($instance, $this->getDataGenerator()->create_user()->id);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        try {
            $this->plugin->enrol_conditional($instance, (object) []);
            $this->fail('The enrolment must be refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame('maxenrolledreached', $e->errorcode);
        }
        $this->assertFalse($this->enrolled($instance, (int) $user->id));
    }

    public function test_free_place_enrols(): void {
        $instance = $this->create_instance(2);
        $this->plugin->enrol_user($instance, $this->getDataGenerator()->create_user()->id);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->plugin->enrol_conditional($instance, (object) []);

        $this->assertTrue($this->enrolled($instance, (int) $user->id));
    }

    public function test_refused_lock_enrols_nobody(): void {
        global $CFG;
        $instance = $this->create_instance(5);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        require_once(__DIR__ . '/fixtures/refusing_lock_factory.php');
        $CFG->lock_factory = '\\enrol_conditional\\refusing_lock_factory';

        try {
            $this->plugin->enrol_conditional($instance, (object) []);
            $this->fail('The enrolment must be refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame('capacitybusy', $e->errorcode);
        }
        $this->assertFalse($this->enrolled($instance, (int) $user->id));
    }

    public function test_without_limit_no_lock_is_taken(): void {
        global $CFG;
        // Control of the test above: the same refusing factory, on an instance without a limit, enrols.
        $instance = $this->create_instance(0);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        require_once(__DIR__ . '/fixtures/refusing_lock_factory.php');
        $CFG->lock_factory = '\\enrol_conditional\\refusing_lock_factory';

        $this->plugin->enrol_conditional($instance, (object) []);

        $this->assertTrue($this->enrolled($instance, (int) $user->id));
    }
}
