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

use local_table_sql\table_sql;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . "/formslib.php");

class demo_table extends table_sql {
    protected function define_table_configs() {
        $sql = "SELECT id, d.groupid, d.label1, d.label2,
            CASE WHEN (id % 2) = 0
                THEN CONCAT('user', id, '@example.com')
                ELSE CONCAT('Secret-Text-', id)
            END AS sensitive_data
            FROM {local_table_sql_demo} d";
        $params = [];

        // local test with many rows:
        /*
        for ($i = 1; $i < 40; $i++) {
            $sql .= " UNION ALL SELECT id + " . ($i * 1000) . ", groupid, label1, label2 FROM {local_table_sql_demo} d";
        }
        */
        $this->set_sql_query($sql, $params);
        $this->set_sql_table('local_table_sql_demo');

        // Define headers and columns.
        $cols = [
            'id' => 'id',
            'groupid' => 'groupid',
            'label1' => 'label1',
            'label2' => 'label2',
            'sensitive_data' => 'Sensitive Data',
        ];
        $this->set_table_columns($cols);
        $this->sortable(true, 'id', SORT_ASC);
        $this->is_downloadable(true);

        // $this->set_column_options('timecreated', data_type: static::PARAM_TIMESTAMP);
        // $this->set_column_options('internal_test', internal: true);
        // $this->set_column_options('groupid', select_options: [
        //     '1' => 'Aktiviert',
        //     '0' => 'Deaktiviert',
        // ]);
        $this->set_column_options('groupid', mrt_options: [
            'filterVariant' => 'range-slider',
            'muiFilterSliderProps' => [
                'marks' => false,
                'max' => 100, //custom max (as opposed to faceted max)
                'min' => 0, //custom min (as opposed to faceted min)
                'step' => 1,
            ],
        ]);
        $this->set_column_options('sensitive_data', sensitive: true);
        // $this->column_class('widgetid', 'local_table_sql-ignore-click-row-action');

        // $this->column_style('timecreated', 'background', 'lightgreen');
        // $this->column_class('timecreated', 'text-right');
        // $this->column_class('setto', 'text-center');

        $this->pagesize = 8;

        $this->add_row_action(
            type: 'edit'
        );
        $this->add_row_action(
            type: 'delete'
        );
        $this->add_row_action(
            '/',
            label: 'Test Link',
            target: '_blank',
            class: 'btn btn-sm btn-primary',
        );
        $this->add_row_action(
            id: 'test-onclick-nolink',
            label: 'Test Onlclick without link',
            onclick: 'function(e,row){ console.log(e,row); alert(\'onclick\'); }',
        );
        $this->add_row_action(
            '/',
            label: 'Test Onlclick with link',
            onclick: 'function(e,row){ console.log(e,row); return confirm("weiter?"); }',
        );

        $this->set_row_actions_display_as_menu(false);
        $this->enable_row_selection();

        // $this->set_row_actions_js_callback("function({ row, row_actions }){
        //     // console.log('callback');
        //     return row_actions;
        // }");

        // $this->set_render_detail_panel_js_callback("function({ row }){
        //     // return '<div style=\"font-weight: bold; background: red\">dfdsfdfd123</div>';
        //     return '/fffdsfsd';
        // }");

        // development: enable the detail panel
        // $this->enable_detail_panel();

        // development: disable the global filter
        // $this->set_mrt_options([
        //     'enableGlobalFilter' => false,
        // ]);

        // development: disable page size selector test
        // $this->enable_page_size_selector(false);

        // development: set initial page index to another page
        // $this->set_initial_page_index(1);
    }

    public function col_label1($row) {
        return $this->format_col_content(
            $row->label1,
            link: '/test?' . $row->id,
    );
    }

    protected function render_detail_panel_content(object $row): string {
        return $row->id;
    }

    protected function get_row_actions(object $row): array {
        $row_actions = parent::get_row_actions($row);

        // test disabled with non-bool
        // $row_actions[0]->disabled = '0';

        $row_actions['test-onclick-nolink']->disabled = $row->id % 2;

        return $row_actions;
    }
}
