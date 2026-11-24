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

namespace enrol_conditional\local;

use local_enrolconditions\tree;

/**
 * The cohort restriction of core's self enrolment (the column customint5), as a condition of the tree.
 *
 * Nothing reads customint5 any more: the restriction is a cohort condition, required together with the other
 * conditions. The upgrade step 2026110902 made that move for the instances of the site, and the restore of a
 * backup taken before it makes it for the instance it creates.
 *
 * @package    enrol_conditional
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class cohort_restriction {
    /**
     * Adds a cohort condition to a tree, required together with what the tree already asks.
     *
     * The one rule of the restore of an old backup and of the upgrade step 2026110902, which calls this method:
     * an empty tree, and a stored value that is not a valid tree (not JSON, an unknown operator, too deep), become
     * the cohort condition alone, so the restored instance is never open beyond the cohort; an AND group takes the
     * condition as one more child; any other root (an OR group, a negated one) is wrapped with it in a new AND
     * group. The wrapping adds a level and a node: a tree already at the depth or node limit comes out invalid,
     * which refuses every enrolment until a teacher shortens it, and still carries the restriction. A cohort id of
     * -1 marked a cohort that was no longer known and stays a condition no user meets (id 0).
     *
     * @param string|null $json Tree as the instance stores it.
     * @param int $cohortid Cohort the instance was restricted to.
     * @return string JSON of the tree with the restriction.
     */
    public static function add_to_tree(?string $json, int $cohortid): string {
        $cohort = ['type' => 'cohort', 'negate' => false, 'config' => ['cohortid' => max(0, $cohortid)]];
        try {
            $root = tree::from_json($json)->to_array();
        } catch (\local_enrolconditions\invalid_tree_exception $e) {
            $root = [];
        }
        if (empty($root['children'])) {
            $root = ['op' => tree::OP_AND, 'negate' => false, 'children' => [$cohort]];
        } else if (($root['op'] ?? '') === tree::OP_AND && empty($root['negate'])) {
            $root['children'][] = $cohort;
        } else {
            $root = ['op' => tree::OP_AND, 'negate' => false, 'children' => [$root, $cohort]];
        }
        return json_encode($root, tree::JSON_FLAGS);
    }
}
