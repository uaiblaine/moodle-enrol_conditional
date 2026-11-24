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
 * A bulk operation for the conditional self enrolment plugin to edit selected users.
 *
 * @package enrol_conditional
 * @copyright 2018 Farhan Karmali
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * A bulk operation for the conditional self enrolment plugin to edit selected users.
 *
 * @package enrol_conditional
 * @copyright 2018 Farhan Karmali
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_conditional_editselectedusers_operation extends enrol_bulk_enrolment_operation {
    /**
     * Returns the title to display for this bulk operation.
     *
     * @return string
     */
    public function get_title() {
        return get_string('editselectedusers', 'enrol_conditional');
    }

    /**
     * Returns the identifier for this bulk operation. This is the key used when the plugin
     * returns an array containing all of the bulk operations it supports.
     *
     * @return string
     */
    public function get_identifier() {
        return 'editselectedusers';
    }

    /**
     * Processes the bulk operation request for the given userids with the provided properties.
     *
     * Only the enrolments of this method change. Each selected user carries every enrolment the manager
     * loaded, which, when it is not filtered by instance, includes those of other methods: core refuses to
     * update them through this plugin and would stop the batch halfway.
     *
     * @param course_enrolment_manager $manager
     * @param array $users Users with their enrolments, as the participants page selects them.
     * @param stdClass $properties The data returned by the form.
     * @return bool Whether the operation ran.
     */
    public function process(course_enrolment_manager $manager, array $users, stdClass $properties) {
        if (!has_capability("enrol/conditional:manage", $manager->get_context())) {
            return false;
        }

        // Get the user enrolments of this method.
        $instances = [];
        foreach ($users as $user) {
            foreach ($this->own_enrolments($user) as $enrolment) {
                if (!array_key_exists($enrolment->id, $instances)) {
                    $instances[$enrolment->id] = $enrolment;
                }
            }
        }

        // Check that each instance is manageable by the current user.
        foreach ($instances as $instance) {
            if (!$this->plugin->allow_manage($instance)) {
                return false;
            }
        }

        // Collect the known properties; null keeps the stored value.
        $status = null;
        if ($properties->status == ENROL_USER_ACTIVE || $properties->status == ENROL_USER_SUSPENDED) {
            $status = (int)$properties->status;
        }
        $timestart = empty($properties->timestart) ? null : (int)$properties->timestart;
        $timeend = empty($properties->timeend) ? null : (int)$properties->timeend;
        if ($status === null && $timestart === null && $timeend === null) {
            return true;
        }

        // The enrolment API updates each record, sets modifierid and timemodified and triggers the event.
        foreach ($users as $user) {
            foreach ($this->own_enrolments($user) as $enrolment) {
                $this->plugin->update_user_enrol($enrolment->enrolmentinstance, $user->id, $status, $timestart, $timeend);
            }
        }

        // Delete cached course contacts for this course because they may be affected.
        cache::make('core', 'coursecontacts')->delete($manager->get_context()->instanceid);
        return true;
    }

    /**
     * The enrolments of a selected user that belong to this method.
     *
     * @param stdClass $user Selected user with its enrolments.
     * @return array User enrolments with their enrolmentinstance.
     */
    protected function own_enrolments(stdClass $user): array {
        $name = $this->plugin->get_name();
        return array_filter(
            $user->enrolments,
            fn(stdClass $enrolment): bool => $enrolment->enrolmentinstance->enrol === $name
        );
    }

    /**
     * Returns a enrol_bulk_enrolment_operation extension form to be used
     * in collecting required information for this operation to be processed.
     *
     * @param string|moodle_url|null $defaultaction
     * @param mixed $defaultcustomdata
     * @return enrol_conditional_editselectedusers_form
     */
    public function get_form($defaultaction = null, $defaultcustomdata = null) {
        return new enrol_conditional_editselectedusers_form($defaultaction, $defaultcustomdata);
    }
}
