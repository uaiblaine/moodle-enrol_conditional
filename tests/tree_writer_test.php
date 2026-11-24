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

use core_privacy\local\request\approved_contextlist;
use enrolcond_userlist\privacy\provider;
use local_enrolconditions\condition\manager;

/**
 * Tests of the writer the engine uses to erase usernames from the stored trees of this method.
 *
 * @package    enrol_conditional
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\enrol_conditional_plugin::class)]
final class tree_writer_test extends \core_privacy\tests\provider_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        \core\plugininfo\enrol::enable_plugin('conditional', true);
        manager::reset_caches();
    }

    #[\Override]
    public function tearDown(): void {
        manager::reset_caches();
        parent::tearDown();
    }

    /**
     * An instance of the method whose tree has a list of usernames and a second, untouched node.
     *
     * @param string[] $names
     * @return \stdClass Row of the enrol table.
     */
    private function instance_listing(array $names): \stdClass {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $plugin = enrol_get_plugin('conditional');
        $id = $plugin->add_instance($course, [
            'status' => ENROL_INSTANCE_ENABLED,
            'roleid' => $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST),
        ]);
        $DB->set_field('enrol', \enrol_conditional_plugin::FIELD_CONDITIONS, json_encode(['op' => 'and', 'children' => [
            ['type' => 'userlist', 'config' => ['usernames' => $names]],
            ['type' => 'userlist', 'config' => ['usernames' => ['keeper']]],
        ]]), ['id' => $id]);
        return $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Usernames of the first list node of the stored tree.
     *
     * @param \stdClass $instance
     * @return string[]
     */
    private function stored_names(\stdClass $instance): array {
        global $DB;
        $json = $DB->get_field('enrol', \enrol_conditional_plugin::FIELD_CONDITIONS, ['id' => $instance->id]);
        return json_decode($json, true)['children'][0]['config']['usernames'];
    }

    public function test_the_engine_finds_the_method_as_a_tree_writer(): void {
        $this->assertInstanceOf(\local_enrolconditions\tree_writer::class, enrol_get_plugin('conditional'));
        $this->assertInstanceOf(
            \local_enrolconditions\tree_writer::class,
            manager::get_enforceable_plugins()['conditional']
        );
    }

    public function test_privacy_erases_a_listed_user_from_the_stored_tree(): void {
        global $DB;
        $ana = $this->getDataGenerator()->create_user(['username' => 'ana']);
        $this->getDataGenerator()->create_user(['username' => 'bia']);
        $instance = $this->instance_listing(['ana', 'bia']);
        $before = (int) $instance->timemodified;
        $this->setUser(null);

        $contextids = provider::get_contexts_for_userid((int) $ana->id)->get_contextids();
        $this->assertEquals([\context_course::instance($instance->courseid)->id], $contextids);
        $this->waitForSecond();
        provider::delete_data_for_user(new approved_contextlist($ana, 'enrolcond_userlist', $contextids));

        $this->assertSame(['bia'], $this->stored_names($instance));
        // The rest of the tree is intact.
        $tree = json_decode($DB->get_field('enrol', 'customtext2', ['id' => $instance->id]), true);
        $this->assertSame(['keeper'], $tree['children'][1]['config']['usernames']);
        $this->assertSame('and', $tree['op']);
        $this->assertGreaterThan($before, (int) $DB->get_field('enrol', 'timemodified', ['id' => $instance->id]));
        $this->assertSame([], provider::get_contexts_for_userid((int) $ana->id)->get_contextids());
    }

    public function test_privacy_erases_a_deleted_account(): void {
        $ana = $this->getDataGenerator()->create_user(['username' => 'ana']);
        $instance = $this->instance_listing(['ana', 'bia']);
        delete_user($ana);

        $contextids = provider::get_contexts_for_userid((int) $ana->id)->get_contextids();
        $this->assertCount(1, $contextids);
        provider::delete_data_for_user(new approved_contextlist($ana, 'enrolcond_userlist', $contextids));

        $this->assertSame(['bia'], $this->stored_names($instance));
    }

    public function test_privacy_leaves_the_tree_of_a_user_not_listed(): void {
        global $DB;
        $dora = $this->getDataGenerator()->create_user(['username' => 'dora']);
        $instance = $this->instance_listing(['ana', 'bia']);
        $stored = $DB->get_field('enrol', 'customtext2', ['id' => $instance->id]);

        $this->assertSame([], provider::get_contexts_for_userid((int) $dora->id)->get_contextids());
        $context = \context_course::instance($instance->courseid);
        provider::delete_data_for_user(new approved_contextlist($dora, 'enrolcond_userlist', [$context->id]));

        $this->assertSame($stored, $DB->get_field('enrol', 'customtext2', ['id' => $instance->id]));
        $this->assertSame(['ana', 'bia'], $this->stored_names($instance));
    }

    public function test_the_writer_refuses_an_instance_of_another_method(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $manual = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        $stored = $manual->customtext2;

        try {
            enrol_get_plugin('conditional')->set_enforcement_tree($manual, '{"op":"and","children":[]}');
            $this->fail('A row of another method must be refused');
        } catch (\coding_exception $e) {
            $this->assertStringContainsString('conditional', $e->getMessage());
        }
        $this->assertSame($stored, $DB->get_field('enrol', 'customtext2', ['id' => $manual->id]));
    }

    public function test_the_writer_refuses_a_missing_instance(): void {
        global $DB;
        $instance = $this->instance_listing(['ana']);
        $missing = clone $instance;
        $missing->id = (int) $instance->id + 1000;

        $this->expectException(\coding_exception::class);
        try {
            enrol_get_plugin('conditional')->set_enforcement_tree($missing, '{"op":"and","children":[]}');
        } finally {
            $this->assertSame(['ana'], $this->stored_names($instance));
            $this->assertFalse($DB->record_exists('enrol', ['id' => $missing->id]));
        }
    }

    public function test_the_instance_evaluates_from_the_new_tree(): void {
        global $DB;
        $dora = $this->getDataGenerator()->create_user(['username' => 'dora']);
        $instance = $this->instance_listing(['dora']);
        $DB->set_field('enrol', 'customtext2', json_encode(['op' => 'and', 'children' => [
            ['type' => 'userlist', 'config' => ['usernames' => ['dora']]],
        ]]), ['id' => $instance->id]);
        $plugin = enrol_get_plugin('conditional');
        $instance = $DB->get_record('enrol', ['id' => $instance->id], '*', MUST_EXIST);
        $this->assertTrue($plugin->evaluate_conditions($instance, (int) $dora->id)->is_met());

        $plugin->set_enforcement_tree($instance, json_encode(['op' => 'and', 'children' => [
            ['type' => 'userlist', 'config' => ['usernames' => ['carla']]],
        ]]));

        $fresh = $DB->get_record('enrol', ['id' => $instance->id], '*', MUST_EXIST);
        $this->assertFalse($plugin->evaluate_conditions($fresh, (int) $dora->id)->is_met());
    }

    public function test_the_write_purges_the_map_of_observed_events(): void {
        $instance = $this->instance_listing(['ana']);
        $cache = \cache::make('local_enrolconditions', 'observedevents');
        $cache->set('probe', 'warm');
        $this->assertSame('warm', $cache->get('probe'));

        // The events are held back, so the engine's observer of enrol_instance_updated does not run: the writer
        // purges the map itself, as update_instance() does, and never relies on an observer registered elsewhere.
        $sink = $this->redirectEvents();
        enrol_get_plugin('conditional')->set_enforcement_tree($instance, '{"op":"and","children":[]}');

        $names = array_map(fn($event) => get_class($event), $sink->get_events());
        $this->assertContains(\core\event\enrol_instance_updated::class, $names);
        // A cache entry that survived would keep observing the events of the tree that was replaced.
        $this->assertFalse($cache->get('probe'));
    }
}
