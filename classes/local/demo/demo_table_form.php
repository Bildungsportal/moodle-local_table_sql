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
 * @copyright  2024 Austrian Federal Ministry of Education
 * @author     GTN solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_table_sql\local\demo;

use local_table_sql\table_sql_form;

defined('MOODLE_INTERNAL') || die;

class demo_table_form extends table_sql_form {
    public function __construct() {
        // There are no constructor-IDs
        parent::__construct([], []);
    }

    public function ajax_permission_check(): void {
        require_admin();
    }

    protected function define_table_configs() {
        $sql = "SELECT * FROM {local_table_sql_demo}";
        $this->set_sql_query($sql, []);
        // Define headers and columns.
        $cols = [
            'id' => 'id',
            'groupid' => 'groupid',
            'label1' => 'label1 (maximum 5 chars)',
            'label2' => 'label2 (minimum 5 chars)',
        ];
        $this->set_table_columns($cols);
        $this->sortable(true, 'id', SORT_ASC);

        $this->add_form(
            'demo',
            'Demo',
            new \local_table_sql\local\demo\demo_form()
        );
        $this->add_form_action(
            formid: 'demo',
            col: '*',
            rowids: [ 'id' ],
            label: get_string('edit'),
            type: 'edit'
        );
    }

    function col_groupid($row) {
        return $this->as_modal_formfield(
            'demo', 'groupid',
            ['id' => $row->id],
            ['groupid'],
            \local_table_sql\local\demo\demo_form::get_group($row->groupid)
        );
    }

    function col_label1($row) {
        return $this->as_modal_formfield(
            'demo', 'label1',
            ['id' => $row->id],
            ['label1'],
            $row->label1
        );
    }

    function col_label2($row) {
        return $this->as_modal_formfield(
            'demo', 'label2',
            ['id' => $row->id],
            ['label2'],
            $row->label2
        );
    }
}
