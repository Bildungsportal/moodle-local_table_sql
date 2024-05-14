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

// This script manages which interfaces an app is permitted to use,
// and if the interfaces is activated by the partner.

/**
 * @package    local_table_sql
 * @copyright  2022 Austrian Federal Ministry of Education
 * @author     GTN solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// only for demo page
// Notwendig, damit beim Entwicklen unter localhost:3000 auch die xhr Reqeuests durchgehen
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Credentials: true");

require_once('../../../config.php');

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/local/table_sql/demo/', [
    'table_sql_html' => optional_param('table_sql_html', '', PARAM_BOOL),
]);
$PAGE->set_title("Demo");

if ($_SERVER['HTTP_HOST'] != 'localhost') {
    require_admin();
}

class log_table extends \local_table_sql\table_sql {
    protected $appid;

    public function __construct($uniqueid, $appid) {
        $this->appid = $appid;

        parent::__construct($uniqueid);
    }

    protected function define_table_configs() {

        $cols = [
            'timecreated' => get_string('time'),
            'setto' => get_string('to'),
            'interface_or_widget' => get_string('interface', 'local_eduportal') . ' | ' .
                get_string('widget', 'local_eduportal'),
            'widgetid' => 'WidgetId',
            'approval' => get_string('approval', 'local_eduportal'),
            'username' => "Username\nNewline",
        ];

        $this->no_sorting('approval');
        $this->no_sorting('interface_or_widget');
        $this->no_filter('approval');
        $this->no_filter('interface_or_widget');

        $this->define_columns(array_keys($cols));
        $this->define_headers(array_values($cols));

        $this->set_sql('log.*, u.username', '{local_eduportal_app_log} log JOIN {user} u ON log.userid=u.id');

        $this->sortable(true, 'timecreated', SORT_DESC);

        $this->set_column_options('timecreated', data_type: static::PARAM_TIMESTAMP);
        $this->set_column_options('widgetid', internal: true);

        $this->column_style('timecreated', 'background', 'red');
        $this->column_class('setto', 'setto-test');

        // $this->enable_row_selection();

        $this->pagesize = 8;

        $this->add_row_action(
            type: 'edit'
        );
        $this->add_row_action(
            type: 'delete'
        );
        $this->add_row_action(
            $_SERVER['REQUEST_URI'],
            label: 'Test',
        );

        // $this->set_row_actions_js_callback("function({ row, row_actions }){
        //     // console.log('callback');
        //     return row_actions;
        // }");

        // $this->set_render_detail_panel_js_callback("function({ row }){
        //     // return '<div style=\"font-weight: bold; background: red\">dfdsfdfd123</div>';
        //     return '/fffdsfsd';
        // }");

        $this->enable_detail_panel();

        $this->set_row_actions_display_as_menu(true);

        $this->enable_page_size_selector(false);
        $this->set_initial_page_index(1);
    }

    public function col_setto($row) {
        return $this->format_col_content(
            $row->setto ? get_string('enable') : get_string('disable'),
            link: '/test'
        );
    }

    public function col_interface_or_widget($log) {
        if (!empty($log->ifid)) {
            try {
                $interface = \local_eduportal\iface\lib::get_by_id($log->ifid);
            } catch (\Exception $e) {
                return $e->getMessage();
            }
            $plugin = $interface->plugin;
            $interface = $interface->interface;

            return "{$plugin}/{$interface}";
        }
        if (!empty($log->widgetid)) {
            try {
                $widget = \local_eduportal\widget\lib::get_widget_row($log->widgetid);
                return $widget->localizedname;
            } catch (\Exception $e) {
                return $e->getMessage();
            }
        }
    }

    protected function render_detail_panel_content(object $row) {
        return $row->id;
    }

    public function col_approval($row) {
        if ($row->approvalmaintainer) {
            return get_string('maintainer', 'local_eduportal');
        }
        if ($row->approvalpartner) {
            return get_string('partner', 'local_eduportal');
        }
    }

    protected function get_row_actions(object $row, array $row_actions): ?array {
        $row_actions[1]->disabled = true;
        return $row_actions;
    }
}

$log_table = new log_table('log_table', 1);

echo $OUTPUT->header();


$html = optional_param('table_sql_html', '', PARAM_BOOL);
if ($html) {
    echo '<b>Version: html</b>&nbsp;&nbsp;&nbsp;&nbsp;<a href="' . $PAGE->url->out(true, ['table_sql_html' => 0]) . '">wechseln</a>';
    $log_table->outHtml();
} else {
    echo '<b>Version: dynamisch</b>&nbsp;&nbsp;&nbsp;&nbsp;<a href="' . $PAGE->url->out(true, ['table_sql_html' => 1]) . '">wechseln</a>';
    $log_table->out();
}

echo $OUTPUT->footer();
