<?php
// This file is part of Moodle Course Rollover Plugin
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
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_delivery\delivery\lib;

// only for demo page
// Notwendig, damit beim Entwicklen unter localhost:3000 auch die xhr Reqeuests durchgehen
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Credentials: true");

require_once('../../../config.php');

$orgid = required_param('orgid', PARAM_INT);

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/local/table_sql/demo/deliveries.php', [
    'orgid' => $orgid,
    'tablesql_html' => optional_param('tablesql_html', '', PARAM_BOOL),
]);
$PAGE->set_title("Demo");

if ($_SERVER['HTTP_HOST'] != 'localhost') {
    require_admin();
}

$org = \local_eduportal\api\org::get_org($orgid);
$context = \context_coursecat::instance($org->categoryid);

class delivery_table extends \local_table_sql\table_sql {
    protected $orgid;
    protected $filters;
    protected $readonly;
    protected $context;
    protected $t;

    public function __construct($uniqueid, $orgid, $t = 1, array $filters = null, bool $sortable = true, bool $readonly = false) {

        $this->orgid = $orgid;
        $this->filters = $filters;
        $this->readonly = $readonly;
        $this->t = $t;

        $org = \local_eduportal\api\org::get_org($orgid);
        $this->context = \context_coursecat::instance($org->categoryid);

        parent::__construct($uniqueid);
    }

    public function define_table_configs() {
        $this->enable_row_selection($this->readonly == false);

        if ($this->readonly == false) {
            if ($this->readonly == false) {
                $this->set_row_actions_js_callback("(function({ row, row_actions }){
                  row_actions[0].disabled = true;
                  row_actions[0].label += ' (JS Executed!!!)';
                  return row_actions;
                })");
                $this->set_row_actions_display_as_menu(true);
                // javascript demo, just an idea!
                $this->add_row_action("javascript:alert('{id}')",
                    label: 'JavaScript Demo',
                    id: 'test',
                );
                $this->add_row_action("/local/delivery/multiaction.php?orgid=$this->orgid&selecteddeliveries[]={id}&action=" . \local_delivery\delivery\lib::DELIVERY_ACTION_DOWNLOAD,
                    label: get_string('delivery_action_download_as_zip', 'local_delivery'),
                    id: 'test2',
                );
                $this->add_row_action("/local/delivery/multiaction.php?orgid=$this->orgid&selecteddeliveries[]={id}&action=" . \local_delivery\delivery\lib::DELIVERY_ACTION_START_DELIVERY,
                    label: 'with icon',
                    icon: 'fa-paper-plane fa-solid',
                );
                $this->add_row_action("/local/delivery/multiaction.php?orgid=$this->orgid&selecteddeliveries[]={id}&action=" . \local_delivery\delivery\lib::DELIVERY_ACTION_MARK_AS_DELIVERED,
                    label: get_string('delivery_action_mark_as_delivered', 'local_delivery')
                );
                $this->add_row_action("/local/delivery/multiaction.php?orgid=$this->orgid&selecteddeliveries[]={id}&action=" . \local_delivery\delivery\lib::DELIVERY_ACTION_CANCEL,
                    label: get_string('delivery_action_cancel', 'local_delivery')
                );
                $this->add_row_action("/local/delivery/multiaction.php?orgid=$this->orgid&selecteddeliveries[]={id}&action=" . \local_delivery\delivery\lib::DELIVERY_ACTION_REVOKE,
                    label: get_string('delivery_action_revoke', 'local_delivery')
                );
            }
        }

        $this->pagesize = 8;

        $this->sortable(true, 'id', SORT_DESC);

        $this->column_style_all('vertical-align', 'middle');

        $this->set_sql('*', '{local_delivery} delivery');
    }

    /**
     * Hook that can be overridden in child classes to wrap a table in a form
     * for example. Called only when there is data to display and not
     * downloading.
     */
    function wrap_html_start() {
        if ($this->readonly == false) {
            echo html_writer::start_tag('form', array('action' => '/local/delivery/multiaction.php', 'method' => 'post'));
            echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'orgid', 'value' => $this->orgid));
            echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 't', 'value' => $this->t));
            echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'page', 'value' => $this->currpage));
            \local_delivery\app\delivery_table::print_buttons($this->context, $this->orgid);
        }
    }

    /**
     * Hook that can be overridden in child classes to wrap a table in a form
     * for example. Called only when there is data to display and not
     * downloading.
     */
    function wrap_html_finish() {
        if ($this->readonly == false) {
            delivery_table::print_buttons($this->context, $this->orgid);
            echo html_writer::end_tag('form');
        }
    }

    static function print_buttons($context, $orgid) {
        global $USER;
        $fa_style = 'font-size:19px';
        $fa_class = 'float-left';
        echo html_writer::start_div('d-flex');
        //UPDATE
        if (is_siteadmin($USER->id) || is_primary_admin($USER->id)) {
            echo html_writer::start_div('justify-content-start my-1');
            echo html_writer::start_tag('a', array('href' => "/local/delivery/update_delivery.php?orgid=$orgid"));
            echo html_writer::nonempty_tag('button', get_string('delivery_action_update', 'local_delivery'), array('class' => 'btn btn-primary float-right m-1', 'type' => 'button', 'id' => 'updateDeliveries'));
            echo html_writer::end_tag('a');
            echo html_writer::end_div();
        }

        echo html_writer::start_div('my-1', array('style' => 'flex:auto'));
        echo html_writer::start_div('dropdown');
        echo html_writer::nonempty_tag('button', get_string('delivery_actions', 'local_delivery'), array('class' => 'btn btn-primary dropdown-toggle float-right m-1', 'type' => 'button', 'data-toggle' => 'dropdown', 'data-flip' => 'true', 'data-popperConfig' => '{"modifiers": {"flip": {"enabled": true}, "restoreTopPosition": {"enabled": false } }'));
        echo html_writer::start_div('dropdown-menu');
        $button_class = 'dropdown-item d-flex flex-row';
        //DOWNLOAD
        echo html_writer::nonempty_tag('button', html_writer::nonempty_tag('i', '&nbsp', array('class' => "fa-solid fa-download $fa_class", 'style' => $fa_style)) . html_writer::nonempty_tag('div', get_string('delivery_action_download_as_zip', 'local_delivery')), array('class' => $button_class, 'type' => 'submit', 'name' => 'action', 'value' => \local_delivery\delivery\lib::DELIVERY_ACTION_DOWNLOAD));

        if (has_capability('local/delivery:setdeliveryparameters', $context)) {

            //SET PARAMETERS
            echo html_writer::nonempty_tag('button', html_writer::nonempty_tag('i', '&nbsp', array('class' => "fa-solid fa-pen-to-square $fa_class", 'style' => $fa_style)) . html_writer::nonempty_tag('div', get_string('delivery_action_set_parameters', 'local_delivery')), array('class' => $button_class, 'type' => 'submit', 'name' => 'action', 'value' => \local_delivery\delivery\lib::DELIVERY_ACTION_SET_PARAMETERS));
        }

        if (has_capability('local/delivery:startdeliveryprocess', $context)) {

            //START DELIVERY
            echo html_writer::nonempty_tag('button', html_writer::nonempty_tag('i', '&nbsp', array('class' => "fa-solid fa-paper-plane $fa_class", 'style' => $fa_style)) . html_writer::nonempty_tag('div', get_string('delivery_action_start_delivery', 'local_delivery')), array('class' => $button_class, 'type' => 'submit', 'name' => 'action', 'value' => \local_delivery\delivery\lib::DELIVERY_ACTION_START_DELIVERY));

            //MARK AS DELIVERED
            echo html_writer::nonempty_tag('button', html_writer::nonempty_tag('i', '&nbsp', array('class' => "fa-solid fa-check $fa_class", 'style' => $fa_style)) . html_writer::nonempty_tag('div', get_string('delivery_action_mark_as_delivered', 'local_delivery')), array('class' => $button_class, 'type' => 'submit', 'name' => 'action', 'value' => \local_delivery\delivery\lib::DELIVERY_ACTION_MARK_AS_DELIVERED));

            //CANCEL
            echo html_writer::nonempty_tag('button', html_writer::nonempty_tag('i', '&nbsp', array('class' => "fa-solid fa-xmark $fa_class", 'style' => $fa_style)) . html_writer::nonempty_tag('div', get_string('delivery_action_cancel', 'local_delivery')), array('class' => $button_class, 'type' => 'submit', 'name' => 'action', 'value' => \local_delivery\delivery\lib::DELIVERY_ACTION_CANCEL));

            //REVOKE
            echo html_writer::nonempty_tag('button', html_writer::nonempty_tag('i', '&nbsp', array('class' => "fa-solid fa-ban $fa_class", 'style' => $fa_style)) . html_writer::nonempty_tag('div', get_string('delivery_action_revoke', 'local_delivery')), array('class' => $button_class, 'type' => 'submit', 'name' => 'action', 'value' => \local_delivery\delivery\lib::DELIVERY_ACTION_REVOKE));
        }
        //dropdown-menu
        echo html_writer::end_div();
        //dropup
        echo html_writer::end_div();

        //CREATE DELIVERY
        if (has_capability('local/delivery:createdelivery', $context)) {
            echo html_writer::start_tag('a', array('href' => "/local/delivery/create_delivery.php?orgid=$orgid"));
            echo html_writer::nonempty_tag('button', get_string('create_delivery', 'local_delivery'), array('class' => 'btn btn-primary float-right m-1', 'type' => 'button', 'id' => 'createDeliveryButton'));
            echo html_writer::end_tag('a');
        }
        //my-1
        echo html_writer::end_div();

        //d-flex
        echo html_writer::end_div();
    }

    /**
     * Setup the headers for the table.
     */
    protected function define_table_columns() {
        // Define headers and columns.
        $cols = [];
        // if ($this->readonly == false) {
        //     $cols['checkbox'] = 'check';
        //     $this->no_sorting('checkbox');
        // }
        $cols['column_js_test'] = 'column js test';
        $cols['id'] = get_string('deliveryid', 'local_delivery');
        $cols['description'] = get_string('description');
        $cols['relateduserid'] = get_string('relateduser', 'local_delivery');
        // $cols['recipientuserid'] = get_string('recipientuser', 'local_delivery');
        $cols['class'] = get_string('class', 'local_delivery');
        $cols['rsa'] = get_string('rsa', 'local_delivery');
        $cols['deliveryway'] = get_string('deliveryway', 'local_delivery');
        $cols['filename'] = get_string('file', 'local_delivery');
        // $cols['sendinguserid'] = get_string('sendinguser', 'local_delivery');
        // $cols['createdat'] = get_string('createdat', 'local_delivery');
        $cols['status_string'] = get_string('status');
        $cols['status'] = '';

        $this->define_columns(array_keys($cols));

        //$filterHeader = array_map(function($oldheader) { return $oldheader . '<input type="text"/>'; }, $cols);

        $this->define_headers(array_values($cols));
        //$this->define_headers(array_values($filterHeader));

        $this->set_column_options('status_string', sql_column: 'delivery.status');
        $this->set_column_options('status', hidden: true, no_filter: true);

        // $this->no_sorting('file');
        // $this->no_filter('file');

        global $DB;

        $this->set_column_options('relateduserid', sql_column: $DB->sql_concat_join("' '", ['u.id', 'u.firstname', 'u.lastname']));
        $this->set_column_options('status_string',
            select_options: [
                ['text' => get_string('DELIVERY_STATUS_' . lib::DELIVERY_STATUS_BEING_SIGNED, 'local_delivery'), 'value' => lib::DELIVERY_STATUS_BEING_SIGNED],
                ['text' => get_string('DELIVERY_STATUS_' . lib::DELIVERY_STATUS_DELIVERY_IS_BEING_PREPARED, 'local_delivery'), 'value' => lib::DELIVERY_STATUS_DELIVERY_IS_BEING_PREPARED],
                ['text' => get_string('DELIVERY_STATUS_' . lib::DELIVERY_STATUS_REVOKED, 'local_delivery'), 'value' => lib::DELIVERY_STATUS_REVOKED],
                ['text' => get_string('DELIVERY_STATUS_' . lib::DELIVERY_STATUS_READY_FOR_DELIVERY, 'local_delivery'), 'value' => lib::DELIVERY_STATUS_READY_FOR_DELIVERY],
                ['text' => get_string('DELIVERY_STATUS_' . lib::DELIVERY_STATUS_SIGNED, 'local_delivery'), 'value' => lib::DELIVERY_STATUS_SIGNED],
            ]);

        $this->set_column_options('filename', sql_column: 'CASE signedfiles.id IS NULL WHEN true THEN originalfiles.filename ELSE signedfiles.filename END');

        $this->set_column_options('column_js_test', no_filter: true, no_sorting: true, onclick: 'function({row}){ console.log("from js", row); }');
        $this->column_style('column_js_test', 'background-color', 'green');

        $this->is_downloadable(true);

        $this->set_row_actions_js_callback("function({ row, row_actions }){
                if(row['status'] != ".lib::DELIVERY_STATUS_SIGNED.") {
                    row_actions.filter(obj => obj.id == ".lib::DELIVERY_ACTION_SET_PARAMETERS.")[0].disabled = true;
                }
                if(row['status'] != ".lib::DELIVERY_STATUS_READY_FOR_DELIVERY.") {
                    row_actions.filter(obj => obj.id == ".lib::DELIVERY_ACTION_START_DELIVERY.")[0].disabled = true;
                }
                if(row['status'] != ".lib::DELIVERY_STATUS_READY_FOR_DELIVERY." || row['deliveryway'] != ".lib::DELIVERY_WAY_MANUAL.") {
                    row_actions.filter(obj => obj.id == ".lib::DELIVERY_ACTION_MARK_AS_DELIVERED.")[0].disabled = true;
                    row_actions.filter(obj => obj.id == ".lib::DELIVERY_ACTION_CANCEL.")[0].disabled = true;
                }
                if(row['status'] == ".lib::DELIVERY_STATUS_REVOKED.") {
                    row_actions.filter(obj => obj.id == ".lib::DELIVERY_ACTION_REVOKE.")[0].disabled = true;
                }
                return row_actions;
              }");
    }

    public function col_column_js_test() {
        return microtime(true);
    }

    /**
     * Builds the SQL query.
     *
     * @param bool $count When true, return the count SQL.
     * @return array containing sql to use and an array of params.
     */
    protected function get_sql_and_params($count = false) {
        [$filter_where, $filter_params] = $this->get_filter_where(SQL_PARAMS_NAMED);

        if ($count) {
            $select = "COUNT(1)";
            $sql = "SELECT $select FROM {local_delivery} delivery
                LEFT JOIN {files} signedfiles
                    ON signedfiles.itemid = delivery.fileid
                    AND signedfiles.filesize > 0
                    AND signedfiles.filearea = 'delivery_files'
                    AND signedfiles.filepath = '/signed/'
                LEFT JOIN {user} u
                    ON u.id=delivery.relateduserid
                JOIN {files} originalfiles
                    ON originalfiles.itemid = delivery.fileid
                    AND originalfiles.filesize > 0
                    AND originalfiles.filearea = 'delivery_files'
                    AND originalfiles.filepath = '/original/'
                WHERE delivery.orgid = :orgid AND {$filter_where}";
        } else {
            $select = "delivery.*,
                delivery.status AS status_value,
                CASE signedfiles.id IS NULL WHEN true THEN originalfiles.contextid ELSE signedfiles.contextid END as contextid,
                CASE signedfiles.id IS NULL WHEN true THEN originalfiles.component ELSE signedfiles.component END as component,
                CASE signedfiles.id IS NULL WHEN true THEN originalfiles.filearea ELSE signedfiles.filearea END as filearea,
                CASE signedfiles.id IS NULL WHEN true THEN originalfiles.itemid ELSE signedfiles.itemid END as itemid,
                CASE signedfiles.id IS NULL WHEN true THEN originalfiles.filepath ELSE signedfiles.filepath END as filepath,
                CASE signedfiles.id IS NULL WHEN true THEN originalfiles.filename ELSE signedfiles.filename END as filename";
            $sql = "SELECT $select FROM {local_delivery} delivery
                LEFT JOIN {files} signedfiles
                    ON signedfiles.itemid = delivery.fileid
                    AND signedfiles.filesize > 0
                    AND signedfiles.filearea = 'delivery_files'
                    AND signedfiles.filepath = '/signed/'
                LEFT JOIN {user} u
                    ON u.id=delivery.relateduserid
                JOIN {files} originalfiles
                    ON originalfiles.itemid = delivery.fileid
                    AND originalfiles.filesize > 0
                    AND originalfiles.filearea = 'delivery_files'
                    AND originalfiles.filepath = '/original/'
                WHERE delivery.orgid = :orgid AND {$filter_where}";
        }

        if (isset($this->filters)) {
            foreach ($this->filters as $name => $value) {
                if (is_array($value)) {
                    $sql .= " AND delivery.$name IN ('" . implode("','", $value) . "')";
                } else {
                    $sql .= " AND delivery.$name = '$value'";
                }
            }
        }
        $params = array('orgid' => $this->orgid, ...$filter_params);

        $sql .= $this->get_order_by($count);

        return array($sql, $params);
    }

    public function col_checkbox($row) {
        return html_writer::empty_tag('input', array('type' => 'checkbox', 'name' => 'selecteddeliveries[]', 'value' => $row->id, 'style' => 'width:1em;height:1em'));
    }

    public function col_timecreated($row) {
        $recenttimestr = get_string('strftimedatetimeaccurate', 'core_langconfig');
        return userdate($row->timecreated, $recenttimestr);
    }

    public function col_setto($row) {
        return $row->setto ? get_string('enable') : get_string('disable');
    }

    public function col_approval($row) {
        if ($row->approvalmaintainer) {
            return get_string('maintainer', 'local_delivery');
        }
        if ($row->approvalpartner) {
            return get_string('partner', 'local_delivery');
        }
    }

    // protected function get_sql_column($column) {
    //     global $DB;
    //
    //     if ($column == 'relateduserid') {
    //         return $DB->sql_concat_join("' '", ['user.id', 'user.firstname', 'user.lastname']);
    //     } else {
    //         return parent::get_sql_column($column);
    //     }
    // }

    public function col_relateduserid($log) {
        $user = \core_user::get_user($log->relateduserid);
        $fullname = \fullname($user);

        // $fullname = \html_writer::link(
        //     new \moodle_url('/user/profile.php', array('id' => $user->id)),
        //     $fullname
        // );

        return $this->format_col_content(
            $fullname,
            link: new \moodle_url('/user/profile.php', array('id' => $user->id))
        );
    }

    public function col_recipientuserid($log) {
        $user = \core_user::get_user($log->recipientuserid);
        $fullname = \fullname($user);

        $fullname = \html_writer::link(
            new \moodle_url('/user/profile.php', array('id' => $user->id)),
            $fullname
        );

        return $fullname;
    }

    public function col_sendinguserid($log) {
        $user = \core_user::get_user($log->sendinguserid);
        $fullname = \fullname($user);

        if (!isset($user->id)) {
            return get_string('userdeleted');
        }


        $fullname = \html_writer::link(
            new \moodle_url('/user/profile.php', array('id' => $user->id)),
            $fullname
        );

        return $fullname;
    }

    public function col_rsa($log) {
        if (!isset($log->rsa)) {
            return 'Not set';
        }
        if ($log->rsa == 1) {
            return get_string('yes');
        } else {
            return get_string('no');
        }
    }

    public function col_deliveryway($log) {
        switch ($log->deliveryway) {
            case lib::DELIVERY_WAY_MANUAL:
                return get_string('manualdelivery', 'local_delivery');
            case lib::DELIVERY_WAY_DIGITAL_ONLY:
                return get_string('digitaldelivery', 'local_delivery');
            case lib::DELIVERY_WAY_DUAL:
                return get_string('dualdelivery', 'local_delivery');
        }
    }

    public function col_createdat($log) {
        $datetime = new DateTime();
        $datetime->setTimestamp($log->createdat);
        return $datetime->format('Y-m-d');
    }

    public function col_filename($log) {
        $url = \moodle_url::make_pluginfile_url($log->contextid, $log->component, $log->filearea, $log->itemid, $log->filepath, $log->filename, true);
        return \html_writer::link($url, $log->filename);
    }

    public function col_status_string($row) {
        return \local_delivery\delivery\lib::get_status_html($row->status);
    }

    public function col_actions($row) {
        $fa_style = 'font-size:19px';
        $fa_class = 'float-left';
        $html = '';
        $html .= html_writer::start_div('dropdown');
        $html .= html_writer::start_tag('a', array('class' => 'btn btn-primary dropdown-toggle', 'type' => 'button', 'data-toggle' => 'dropdown', 'data-flip' => 'true', 'data-reference' => 'parent', 'data-popperConfig' => '{"modifiers": {"flip": {"enabled": true}, "restoreTopPosition": {"enabled": false } }')) . html_writer::end_tag('a');
        $html .= html_writer::start_div('dropdown-menu');
        $button_class = 'dropdown-item d-flex flex-row';

        // $unavailable_style = 'text-decoration:line-through';
        $unavailable_style = 'display:none !important';

        //DOWNLOAD
        $html .= html_writer::nonempty_tag('a', html_writer::nonempty_tag('i', '&nbsp', array('class' => "fa-solid fa-download $fa_class", 'style' => $fa_style)) . html_writer::nonempty_tag('div', get_string('delivery_action_download_as_zip', 'local_delivery')), array('href' => "/local/delivery/multiaction.php?orgid=$this->orgid&selecteddeliveries[]=$row->id&action=" . \local_delivery\delivery\lib::DELIVERY_ACTION_DOWNLOAD, 'class' => $button_class));

        if (has_capability('local/delivery:setdeliveryparameters', $this->context)) {
            $setParameterIcon = html_writer::nonempty_tag('i', '&nbsp', array('class' => "fa-solid fa-pen-to-square $fa_class", 'style' => $fa_style));
            $setParameterText = html_writer::nonempty_tag('div', get_string('delivery_action_set_parameters', 'local_delivery'));
            if ($row->status == lib::DELIVERY_STATUS_SIGNED) {
                //SET PARAMETERS
                $html .= html_writer::nonempty_tag('a', $setParameterIcon . $setParameterText, array('href' => "/local/delivery/multiaction.php?orgid=$this->orgid&selecteddeliveries[]=$row->id&action=" . \local_delivery\delivery\lib::DELIVERY_ACTION_SET_PARAMETERS, 'class' => $button_class));
            } else {
                $html .= html_writer::nonempty_tag('div', $setParameterIcon . $setParameterText, array('style' => $unavailable_style, 'class' => $button_class));
            }
        }

        if (has_capability('local/delivery:startdeliveryprocess', $this->context)) {
            $startDeliveryIcon = html_writer::nonempty_tag('i', '&nbsp', array('class' => "fa-solid fa-paper-plane $fa_class", 'style' => $fa_style));
            $startDeliveryText = html_writer::nonempty_tag('div', get_string('delivery_action_start_delivery', 'local_delivery'));
            //START DELIVERY
            if ($row->status == lib::DELIVERY_STATUS_READY_FOR_DELIVERY) {
                $html .= html_writer::nonempty_tag('a', $startDeliveryIcon . $startDeliveryText, array('href' => "/local/delivery/multiaction.php?orgid=$this->orgid&selecteddeliveries[]=$row->id&action=" . \local_delivery\delivery\lib::DELIVERY_ACTION_START_DELIVERY, 'class' => $button_class));
            } else {
                $html .= html_writer::nonempty_tag('div', $startDeliveryIcon . $startDeliveryText, array('style' => $unavailable_style, 'class' => $button_class));
            }

            $markDeliveredIcon = html_writer::nonempty_tag('i', '&nbsp', array('class' => "fa-solid fa-check $fa_class", 'style' => $fa_style));
            $markDeliveredText = html_writer::nonempty_tag('div', get_string('delivery_action_mark_as_delivered', 'local_delivery'));
            //MARK AS DELIVERED
            if ($row->status == lib::DELIVERY_STATUS_READY_FOR_DELIVERY && $row->deliveryway == lib::DELIVERY_WAY_MANUAL) {
                $html .= html_writer::nonempty_tag('a', $markDeliveredIcon . $markDeliveredText, array('href' => "/local/delivery/multiaction.php?orgid=$this->orgid&selecteddeliveries[]=$row->id&action=" . \local_delivery\delivery\lib::DELIVERY_ACTION_MARK_AS_DELIVERED, 'class' => $button_class));
            } else {
                $html .= html_writer::nonempty_tag('div', $markDeliveredIcon . $markDeliveredText, array('style' => $unavailable_style, 'class' => $button_class));
            }

            $cancelIcon = html_writer::nonempty_tag('i', '&nbsp', array('class' => "fa-solid fa-xmark $fa_class", 'style' => $fa_style));
            $cancelText = html_writer::nonempty_tag('div', get_string('delivery_action_cancel', 'local_delivery'));
            //CANCEL
            if ($row->status == lib::DELIVERY_STATUS_READY_FOR_DELIVERY || $row->deliveryway == lib::DELIVERY_WAY_MANUAL) {
                $html .= html_writer::nonempty_tag('a', $cancelIcon . $cancelText, array('href' => "/local/delivery/multiaction.php?orgid=$this->orgid&selecteddeliveries[]=$row->id&action=" . \local_delivery\delivery\lib::DELIVERY_ACTION_CANCEL, 'class' => $button_class));

            } else {
                $html .= html_writer::nonempty_tag('div', $cancelIcon . $cancelText, array('style' => $unavailable_style, 'class' => $button_class));
            }

            $revokeIcon = html_writer::nonempty_tag('i', '&nbsp', array('class' => "fa-solid fa-ban $fa_class", 'style' => $fa_style));
            $revokeText = html_writer::nonempty_tag('div', get_string('delivery_action_revoke', 'local_delivery'));
            //REVOKE
            if ($row->status != lib::DELIVERY_STATUS_REVOKED) {
                $html .= html_writer::nonempty_tag('a', $revokeIcon . $revokeText, array('href' => "/local/delivery/multiaction.php?orgid=$this->orgid&selecteddeliveries[]=$row->id&action=" . \local_delivery\delivery\lib::DELIVERY_ACTION_REVOKE, 'class' => $button_class));
            } else {
                $html .= html_writer::nonempty_tag('div', $revokeIcon . $revokeText, array('style' => $unavailable_style, 'class' => $button_class));
            }
        }
        //dropdown-menu
        $html .= html_writer::end_div();
        //dropup
        $html .= html_writer::end_div();
        return $html;
    }
}


$log_table = new delivery_table('selecteddeliveries', $orgid, '', []);

echo $OUTPUT->header();

$html = optional_param('tablesql_html', '', PARAM_BOOL);
if ($html) {
    echo '<b>Version: html</b>&nbsp;&nbsp;&nbsp;&nbsp;<a href="' . $PAGE->url->out(true, ['tablesql_html' => 0]) . '">wechseln</a>';
    $log_table->outHtml(5);
} else {
    echo '<b>Version: dynamisch</b>&nbsp;&nbsp;&nbsp;&nbsp;<a href="' . $PAGE->url->out(true, ['tablesql_html' => 1]) . '">wechseln</a>';
    $log_table->out();
}

// $log_table->clear_selection();

// var_dump($log_table->get_selected_rowids());

echo $OUTPUT->footer();
