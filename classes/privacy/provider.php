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

namespace enrol_conditional\privacy;

/**
 * Privacy provider: this plugin has no store of personal data of its own.
 *
 * What it writes is covered elsewhere: user enrolments by core_enrol; role assignments by core_role, made
 * without a component because roles_protected() is false; group memberships by core_group, because they are
 * added without a component; expiry notifications by core_message. The suspension records and the user lists
 * that some conditions keep in the instance settings belong to local_enrolconditions and its condition types.
 * Writing either row with this component (roles_protected() true, or a component passed to groups_add_member()
 * in {@see \enrol_conditional_plugin::enrol_conditional()}) would make it this plugin's to cover.
 *
 * @package    enrol_conditional
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\null_provider {
    /**
     * Identifier of the string that explains why no data is stored.
     *
     * @return string
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
