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
use local_enrolconditions\invalid_tree_exception;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/enrol/conditional/lib.php');

/**
 * A teacher cannot save a condition tree that names a course they cannot see.
 *
 * Entered through the real save paths: the instance form's validation, add_instance() and update_instance().
 * A condition already stored in the instance stays editable by the teacher, wherever it moves in the tree.
 *
 * @package    enrol_conditional
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(enrol_conditional_plugin::class)]
final class conditions_reach_test extends \advanced_testcase {
    /** @var enrol_conditional_plugin */
    protected enrol_conditional_plugin $plugin;

    /** @var \stdClass Course whose instance is edited. */
    protected \stdClass $course;

    /** @var \stdClass Hidden course the teacher cannot see. */
    protected \stdClass $hidden;

    /** @var \stdClass Visible course. */
    protected \stdClass $visible;

    /** @var \stdClass Editing teacher of $course. */
    protected \stdClass $teacher;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        \core\plugininfo\enrol::enable_plugin('conditional', true);
        manager::reset_caches();
        $this->plugin = enrol_get_plugin('conditional');
        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $this->hidden = $generator->create_course(['visible' => 0]);
        $this->visible = $generator->create_course();
        $this->teacher = $generator->create_and_enrol($this->course, 'editingteacher');
    }

    #[\Override]
    protected function tearDown(): void {
        manager::reset_caches();
        parent::tearDown();
    }

    /**
     * JSON of a tree with one coursecompleted condition per course id.
     *
     * @param int[] $courseids
     * @param bool $negate Whether the conditions are negated.
     * @return string
     */
    protected static function tree(array $courseids, bool $negate = false): string {
        $children = [];
        foreach ($courseids as $courseid) {
            $children[] = ['type' => 'coursecompleted', 'negate' => $negate, 'config' => ['courseid' => $courseid]];
        }
        return json_encode(['op' => 'and', 'negate' => false, 'children' => $children]);
    }

    /**
     * Instance of the course whose stored tree an administrator wrote.
     *
     * @param string $json Stored tree.
     * @return \stdClass
     */
    protected function stored_instance(string $json): \stdClass {
        global $DB;
        $this->setAdminUser();
        $id = $this->plugin->add_instance($this->course, ['customtext2' => $json]);
        return $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Data the instance form would post for an instance.
     *
     * @param \stdClass $instance
     * @param string $json Tree posted.
     * @return \stdClass
     */
    protected function posted(\stdClass $instance, string $json): \stdClass {
        $data = (object) (array) $instance;
        $data->customtext2 = $json;
        $data->expirynotify = 0;
        return $data;
    }

    public function test_form_validation_refuses_a_hidden_course(): void {
        $instance = $this->stored_instance(self::tree([]));
        $context = \context_course::instance($this->course->id);
        $this->setUser($this->teacher);

        $data = (array) $this->posted($instance, self::tree([(int) $this->hidden->id])) + ['password' => ''];
        $errors = $this->plugin->edit_instance_validation($data, [], $instance, $context);
        $this->assertArrayHasKey('customtext2_editor', $errors);

        // Control: a course the teacher can see is accepted.
        $data = (array) $this->posted($instance, self::tree([(int) $this->visible->id])) + ['password' => ''];
        $errors = $this->plugin->edit_instance_validation($data, [], $instance, $context);
        $this->assertArrayNotHasKey('customtext2_editor', $errors);
    }

    public function test_a_missing_course_gets_the_answer_of_a_hidden_one(): void {
        $instance = $this->stored_instance(self::tree([]));
        $context = \context_course::instance($this->course->id);
        $this->setUser($this->teacher);
        $missingid = (int) $this->hidden->id + 1000;

        $hidden = $this->plugin->validate_conditions(self::tree([(int) $this->hidden->id]), $instance, $context);
        $missing = $this->plugin->validate_conditions(self::tree([$missingid]), $instance, $context);

        // The reach check answers first, so the teacher cannot tell a course that does not exist from one out of reach.
        $this->assertNotNull($hidden);
        $this->assertStringContainsString(get_string('noaccess', 'enrolcond_coursecompleted'), $hidden);
        $this->assertStringContainsString(get_string('noaccess', 'enrolcond_coursecompleted'), $missing);
        $this->assertStringNotContainsString(get_string('invalidcourse', 'enrolcond_coursecompleted'), $missing);
    }

    public function test_update_instance_refuses_a_hidden_course(): void {
        global $DB;
        $instance = $this->stored_instance(self::tree([(int) $this->visible->id]));
        $before = $instance->customtext2;
        $this->setUser($this->teacher);

        try {
            $this->plugin->update_instance($instance, $this->posted($instance, self::tree([(int) $this->hidden->id])));
            $this->fail('The hidden course must be refused.');
        } catch (invalid_tree_exception $e) {
            $this->assertSame($before, $DB->get_field('enrol', 'customtext2', ['id' => $instance->id]));
        }

        // Control: a visible course is stored.
        $other = self::tree([(int) $this->visible->id, (int) $this->course->id]);
        $this->plugin->update_instance($instance, $this->posted($instance, $other));
        $stored = $DB->get_field('enrol', 'customtext2', ['id' => $instance->id]);
        $this->assertNotSame($before, $stored);
        $this->assertCount(2, json_decode($stored, true)['children']);
    }

    public function test_add_instance_refuses_a_hidden_course(): void {
        global $DB;
        $this->setUser($this->teacher);
        $count = $DB->count_records('enrol', ['courseid' => $this->course->id, 'enrol' => 'conditional']);

        try {
            $this->plugin->add_instance($this->course, ['customtext2' => self::tree([(int) $this->hidden->id])]);
            $this->fail('The hidden course must be refused.');
        } catch (invalid_tree_exception $e) {
            $this->assertSame($count, $DB->count_records('enrol', ['courseid' => $this->course->id, 'enrol' => 'conditional']));
        }

        // Control: a visible course creates the instance.
        $this->plugin->add_instance($this->course, ['customtext2' => self::tree([(int) $this->visible->id])]);
        $this->assertSame($count + 1, $DB->count_records('enrol', ['courseid' => $this->course->id, 'enrol' => 'conditional']));
    }

    public function test_a_stored_hidden_course_stays_editable(): void {
        global $DB;
        $hiddenid = (int) $this->hidden->id;
        $instance = $this->stored_instance(self::tree([$hiddenid]));
        $context = \context_course::instance($this->course->id);
        $this->setUser($this->teacher);

        // Another field changed, the tree posted back as it is.
        $data = $this->posted($instance, $instance->customtext2);
        $data->name = 'Renamed';
        $this->plugin->update_instance($instance, $data);
        $this->assertSame('Renamed', $DB->get_field('enrol', 'name', ['id' => $instance->id]));

        // The node left as it is but moved, negated and joined by a visible course: validation accepts it.
        $instance = $DB->get_record('enrol', ['id' => $instance->id], '*', MUST_EXIST);
        $moved = json_encode(['op' => 'or', 'negate' => false, 'children' => [
            json_decode(self::tree([(int) $this->visible->id]), true),
            json_decode(self::tree([$hiddenid], true), true),
        ]]);
        $this->assertNull($this->plugin->validate_conditions($moved, $instance, $context));

        // Control: a hidden course that is not stored is still refused in the same tree.
        $other = (int) $this->getDataGenerator()->create_course(['visible' => 0])->id;
        $added = json_encode(['op' => 'or', 'negate' => false, 'children' => [
            json_decode(self::tree([$hiddenid]), true),
            json_decode(self::tree([$other]), true),
        ]]);
        $this->assertNotNull($this->plugin->validate_conditions($added, $instance, $context));
    }
}
