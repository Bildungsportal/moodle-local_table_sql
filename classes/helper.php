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
 * @package    local_table_sql
 * @copyright  2022 Austrian Federal Ministry of Education
 * @author     GTN solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_table_sql;

defined('MOODLE_INTERNAL') || die();

class helper {
    /**
     * Returns a class name. For anonymous classes, it returns the file path and line number, which IS NOT stable against code changes above the class definition.
     * But good enough for us
     * PHP's get_class() on anonymous functions also returns file + line number, but also adds some random string
     * And that is not exactly defined in the PHP manual
     *
     * @return string The class name
     */
    public static function get_class_name(object $instance): string {
        $reflection = new \ReflectionClass($instance);

        // 1. Handle Named Classes
        if (!$reflection->isAnonymous()) {
            return $reflection->getName();
        }

        $fileName = $reflection->getFileName();
        $startLine = $reflection->getStartLine();

        // 2. Handle Anonymous Classes (Name is Location)
        $classes = static::findAnonymousClasses($reflection->getFileName(), table_sql::class);
        if (count($classes) === 1) {
            // only one anonymous class in this file, so the stable identifier can just the file name
            // so any code changes in the file, which would result the line number changing, do not affect the identifier
            return $fileName . ':single';
        } else {
            // more than one class, or class not found (maybe because of the realpath() difference)
            //  Create the volatile location string including line number
            return $fileName . ':' . $startLine;
        }
    }

    /**
     * Find all anonymous classes defined in a specific file
     *
     * @param string $file Path to file
     * @param string $parentClassFilter Optional parent class filter
     * @return string[] Array of anonymous class instances
     */
    public static function findAnonymousClasses(string $file, string $parentClassFilter = ''): array {
        $anonymousClasses = [];
        foreach (get_declared_classes() as $className) {
            $reflection = new \ReflectionClass($className);
            if (!$reflection->isAnonymous()) {
                continue;
            }

            // If a file is specified, filter by file
            if ($reflection->getFileName() !== realpath($file)) {
                continue;
            }

            if ($parentClassFilter && !$reflection->isSubclassOf($parentClassFilter)) {
                continue;
            }

            // Instantiate anonymous class without constructor arguments
            $anonymousClasses[] = $reflection->getName();
        }
        return $anonymousClasses;
    }
}
