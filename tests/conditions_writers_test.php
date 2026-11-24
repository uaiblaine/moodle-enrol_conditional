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
 * Lists every writer of the condition tree column from the plugin's source tree.
 *
 * Each writer must decide whether the tree it stores is validated; a new one fails this test until it is
 * reviewed and added to the list below with that decision.
 *
 * @package    enrol_conditional
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
final class conditions_writers_test extends \advanced_testcase {
    /** @var array Reviewed writers, "path::function" => how the stored tree is checked. */
    private const REVIEWED = [
        'lib.php::insert_instance' => 'Structure only; add_instance() runs validate_conditions() before calling it.',
        'lib.php::update_instance' => 'validate_conditions() when the tree differs from the stored one.',
        'lib.php::restore_instance' => 'Structure only, on purpose: a tree invalid here is kept and evaluates to unknown.',
        'lib.php::set_enforcement_tree' => 'None, on purpose: the privacy system erases a username from a tree already stored, '
            . 'with no user to check reach for; it checks only that the row is an instance of this method.',
        'db/upgrade.php::xmldb_enrol_conditional_upgrade' => 'Upgrade rewrite of trees already stored.',
    ];

    /**
     * The PHP files of the plugin, outside the tests.
     *
     * @return string[] Paths relative to the plugin root.
     */
    private function source_files(): array {
        $root = dirname(__DIR__);
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $relative = substr($file->getPathname(), strlen($root) + 1);
            if ($file->getExtension() !== 'php' || preg_match('~^(tests|vendor|node_modules)/~', $relative)) {
                continue;
            }
            $files[] = $relative;
        }
        sort($files);
        return $files;
    }

    /**
     * Writers found in one source.
     *
     * An assignment or an array key of the column, by constant or by name, and a set_field() of it count as
     * a write.
     *
     * @param string $source PHP source.
     * @return string[] Names of the functions that contain a write.
     */
    private function writers_in(string $source): array {
        $field = '(?:FIELD_CONDITIONS|[\'"]' . enrol_conditional_plugin::FIELD_CONDITIONS . '[\'"]|->'
            . enrol_conditional_plugin::FIELD_CONDITIONS . '\b)';
        $writers = [];
        foreach (explode("\n", $source) as $number => $line) {
            $assigned = preg_match('~' . $field . '[\]}]*\s*(=>|=(?!=))~', $line);
            $setfield = preg_match(
                '~set_field\w*\(\s*[\'"]enrol[\'"]\s*,\s*(self::|enrol_conditional_plugin::)?' . $field . '~',
                $line
            );
            // The declaration of the constant names the column; it writes nothing.
            if ((!$assigned && !$setfield) || preg_match('~\bconst\s+FIELD_CONDITIONS\b~', $line)) {
                continue;
            }
            $before = implode("\n", array_slice(explode("\n", $source), 0, $number + 1));
            preg_match_all('~function\s+(\w+)\s*\(~', $before, $matches);
            $writers[] = end($matches[1]) ?: '(top level)';
        }
        return $writers;
    }

    public function test_every_writer_is_reviewed(): void {
        $root = dirname(__DIR__);
        $found = [];
        foreach ($this->source_files() as $relative) {
            foreach ($this->writers_in(file_get_contents($root . '/' . $relative)) as $function) {
                $found[$relative . '::' . $function] = true;
            }
        }
        $found = array_keys($found);
        sort($found);
        $reviewed = array_keys(self::REVIEWED);
        sort($reviewed);

        $this->assertSame($reviewed, $found, 'A writer of the condition tree was added or removed: review its validation.');
    }

    public function test_scanner_finds_a_write(): void {
        // Control: the scanner sees each shape of write, and not a read.
        $source = "<?php\nfunction a() {\n    \$x->{self::FIELD_CONDITIONS} = 1;\n}\n"
            . "function b() {\n    return ['customtext2' => 1];\n}\n"
            . "function c() {\n    return \$x->customtext2 ?? \$y->{self::FIELD_CONDITIONS} === null;\n}\n"
            . "function d() {\n    \$DB->set_field('enrol', self::FIELD_CONDITIONS, '', []);\n}\n"
            . "class e {\n    public const FIELD_CONDITIONS = 'customtext2';\n}\n";
        $this->assertSame(['a', 'b', 'd'], $this->writers_in($source));
    }
}
