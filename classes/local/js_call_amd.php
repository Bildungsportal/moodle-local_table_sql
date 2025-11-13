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

namespace local_table_sql\local;

defined('MOODLE_INTERNAL') || die();

class js_call_amd {
    function __construct(
        public string $fullmodule,
        public string $func,
        public array $params = []
    ) {
    }

    function get_js_callback() {
        $params = [];
        foreach ($this->params as $param) {
            if ($param instanceof js_expression) {
                $params[] = $param->expression;
            } else {
                $params[] = json_encode($param);
            }
        }
    }

    function equals(js_call_amd $other): bool {
        return json_encode($this) === json_encode($other);
    }
}
