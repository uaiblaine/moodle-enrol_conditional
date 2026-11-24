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
 * Tests that course listings decide from the instance alone and the enrol page from the conditions.
 *
 * Core asks get_info_icons() once per course box on category, front page and search listings, and
 * show_enrolme_link() from the course navigation. The cohort condition reads the database on every
 * evaluation, so a listing that evaluated the conditions would cost more reads than one without them.
 *
 * @package    enrol_conditional
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(enrol_conditional_plugin::class)]
final class listing_cost_test extends \advanced_testcase {
    /** @var enrol_conditional_plugin The enrolment plugin. */
    private enrol_conditional_plugin $plugin;

    /** @var \stdClass Instance whose cohort condition the user does not meet. */
    private \stdClass $withconditions;

    /** @var \stdClass Instance without conditions. */
    private \stdClass $without;

    #[\Override]
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        \core\plugininfo\enrol::enable_plugin('conditional', true);
        $this->plugin = enrol_get_plugin('conditional');

        $cohort = $this->getDataGenerator()->create_cohort();
        $tree = json_encode(['op' => 'and', 'children' => [
            ['type' => 'cohort', 'config' => ['cohortid' => (int) $cohort->id]],
        ]]);
        $course = $this->getDataGenerator()->create_course();
        $id = $this->plugin->add_instance($course, [
            'status' => ENROL_INSTANCE_ENABLED,
            'customint6' => 1,
            'customtext2' => $tree,
        ]);
        $this->withconditions = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);

        // Same course, so the two calls share every cache but the conditions.
        $id = $this->plugin->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED, 'customint6' => 1]);
        $this->without = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);

        $this->setUser($this->getDataGenerator()->create_user());
    }

    /**
     * Database reads of the same call on each instance, after both were warmed.
     *
     * @param callable $call Receives the instance.
     * @return int[] [reads with conditions, reads without]
     */
    private function reads(callable $call): array {
        global $DB;
        $call($this->withconditions);
        $call($this->without);
        $call($this->withconditions);
        $call($this->without);
        $reads = [];
        foreach ([$this->withconditions, $this->without] as $instance) {
            $before = $DB->perf_get_reads();
            $call($instance);
            $reads[] = $DB->perf_get_reads() - $before;
        }
        return $reads;
    }

    public function test_info_icons_do_not_evaluate_conditions(): void {
        [$with, $without] = $this->reads(fn(\stdClass $instance) => $this->plugin->get_info_icons([$instance]));

        $this->assertSame($without, $with);
        // The listing advertises the method; the enrol page explains the conditions.
        $this->assertCount(1, $this->plugin->get_info_icons([$this->withconditions]));
    }

    public function test_enrolme_link_does_not_evaluate_conditions(): void {
        [$with, $without] = $this->reads(fn(\stdClass $instance) => $this->plugin->show_enrolme_link($instance));

        $this->assertSame($without, $with);
        $this->assertTrue($this->plugin->show_enrolme_link($this->withconditions));
    }

    public function test_enrol_page_evaluates_and_refuses(): void {
        [$with, $without] = $this->reads(fn(\stdClass $instance) => $this->plugin->can_self_enrol($instance));

        // Control of the two tests above: the same tree costs reads where it is evaluated.
        $this->assertGreaterThan($without, $with);
        $status = $this->plugin->can_self_enrol($this->withconditions);
        $this->assertIsString($status);
        $this->assertStringContainsString(get_string('conditionsnotmet', 'enrol_conditional'), $status);
        $this->assertTrue($this->plugin->can_self_enrol($this->without));

        $html = $this->plugin->enrol_page_hook($this->withconditions);
        $this->assertStringContainsString(get_string('conditionsnotmet', 'enrol_conditional'), $html);
        $this->assertStringNotContainsString(get_string('enrolme', 'enrol_conditional'), $html);
    }

    public function test_enrol_info_evaluates(): void {
        $this->assertIsString($this->plugin->get_enrol_info($this->withconditions)->status);
        $this->assertTrue($this->plugin->get_enrol_info($this->without)->status);
    }
}
