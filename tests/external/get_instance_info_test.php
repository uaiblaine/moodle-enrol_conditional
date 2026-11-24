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

namespace enrol_conditional\external;

/**
 * Tests of enrol_conditional_get_instance_info, called through the web service layer.
 *
 * @package    enrol_conditional
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(get_instance_info::class)]
final class get_instance_info_test extends \advanced_testcase {
    /** @var \enrol_conditional_plugin The enrolment plugin. */
    private \enrol_conditional_plugin $plugin;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        \core\plugininfo\enrol::enable_plugin('conditional', true);
        $this->plugin = enrol_get_plugin('conditional');
        $_POST['sesskey'] = sesskey();
    }

    /**
     * Calls the function as a client does.
     *
     * @param int $instanceid
     * @return array Response of call_external_function().
     */
    private function call(int $instanceid): array {
        $_POST['sesskey'] = sesskey();
        return \core_external\external_api::call_external_function(
            'enrol_conditional_get_instance_info',
            ['instanceid' => $instanceid]
        );
    }

    /**
     * Creates an enabled instance of this method.
     *
     * @param \stdClass $course
     * @param array $fields Other fields.
     * @return int
     */
    private function create_instance(\stdClass $course, array $fields = []): int {
        return (int) $this->plugin->add_instance($course, $fields + [
            'status' => ENROL_INSTANCE_ENABLED,
            'customint6' => 1,
            'name' => 'Conditional',
        ]);
    }

    public function test_instance_of_another_method_is_refused(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $manual = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        $this->setUser($this->getDataGenerator()->create_user());

        $response = $this->call((int) $manual->id);
        $this->assertTrue($response['error']);
        $this->assertSame('invalidenrolinstance', $response['exception']->errorcode);

        // Control: an instance of this method in the same course answers.
        $id = $this->create_instance($course);
        $response = $this->call($id);
        $this->assertFalse($response['error']);
        $this->assertEquals($id, $response['data']['id']);
    }

    public function test_missing_instance_gets_the_same_answer(): void {
        $this->setUser($this->getDataGenerator()->create_user());
        $response = $this->call(987654);
        $this->assertTrue($response['error']);
        $this->assertSame('invalidenrolinstance', $response['exception']->errorcode);
    }

    public function test_answer_is_the_declared_fields(): void {
        $course = $this->getDataGenerator()->create_course();
        $open = $this->create_instance($course);
        $withkey = $this->create_instance($course, ['password' => 'secretkey']);
        $this->setUser($this->getDataGenerator()->create_user());

        $response = $this->call($open);
        $this->assertFalse($response['error']);
        $this->assertSame(['courseid', 'id', 'name', 'status', 'type'], $this->sorted_keys($response['data']));
        $this->assertSame('conditional', $response['data']['type']);
        $this->assertEquals($course->id, $response['data']['courseid']);

        $response = $this->call($withkey);
        $this->assertSame(['courseid', 'enrolpassword', 'id', 'name', 'status', 'type'], $this->sorted_keys($response['data']));
        // The label of the key field, never the key.
        $this->assertSame(get_string('password', 'enrol_conditional'), $response['data']['enrolpassword']);
    }

    public function test_hidden_course_is_refused(): void {
        $hidden = $this->getDataGenerator()->create_course(['visible' => 0]);
        $visible = $this->getDataGenerator()->create_course();
        $hiddenid = $this->create_instance($hidden);
        $visibleid = $this->create_instance($visible);
        $this->setUser($this->getDataGenerator()->create_user());

        $response = $this->call($hiddenid);
        $this->assertTrue($response['error']);
        $this->assertSame('coursehidden', $response['exception']->errorcode);

        $response = $this->call($visibleid);
        $this->assertFalse($response['error']);
    }

    public function test_context_the_session_may_not_use_is_refused(): void {
        $course = $this->getDataGenerator()->create_course();
        $id = $this->create_instance($course);
        $this->setUser($this->getDataGenerator()->create_user());

        // A token restricted to another course's context may not reach the category context this function validates.
        $other = $this->getDataGenerator()->create_course();
        \core_external\external_api::set_context_restriction(\context_course::instance($other->id));
        try {
            $response = $this->call($id);
        } finally {
            \core_external\external_api::set_context_restriction(null);
        }
        $this->assertTrue($response['error']);
        $this->assertSame('restrictedcontextexception', $response['exception']->errorcode);

        // Control: without the restriction the same call answers.
        $response = $this->call($id);
        $this->assertFalse($response['error']);
        $this->assertEquals($id, $response['data']['id']);
    }

    public function test_execute_returns_only_the_declared_fields(): void {
        $course = $this->getDataGenerator()->create_course();
        $open = $this->create_instance($course);
        $withkey = $this->create_instance($course, ['password' => 'secretkey', 'customtext1' => 'Welcome text']);
        $this->setUser($this->getDataGenerator()->create_user());

        // Straight from execute(): call_external_function() cleans the answer to the declared structure, so
        // extra keys (the key itself among them) would never be seen through it.
        $this->assertSame(['courseid', 'id', 'name', 'status', 'type'], $this->sorted_keys(get_instance_info::execute($open)));
        $answer = get_instance_info::execute($withkey);
        $this->assertSame(['courseid', 'enrolpassword', 'id', 'name', 'status', 'type'], $this->sorted_keys($answer));
        $this->assertStringNotContainsString('secretkey', json_encode($answer));
    }

    public function test_key_zero_requires_the_key_field(): void {
        $course = $this->getDataGenerator()->create_course();
        $zero = $this->create_instance($course, ['password' => '0']);
        $this->setUser($this->getDataGenerator()->create_user());

        $response = $this->call($zero);
        $this->assertSame(['courseid', 'enrolpassword', 'id', 'name', 'status', 'type'], $this->sorted_keys($response['data']));
    }

    /**
     * Keys of an answer, sorted.
     *
     * @param array $data
     * @return string[]
     */
    private function sorted_keys(array $data): array {
        $keys = array_keys($data);
        sort($keys);
        return $keys;
    }
}
