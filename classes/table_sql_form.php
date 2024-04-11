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

namespace local_table_sql;

use local_table_sql\table_sql;
use local_table_sql\table_sql_subform;

defined('MOODLE_INTERNAL') || die;

require_once("$CFG->libdir/tablelib.php");

/**
 * This is a subclass of table_sql that adds capabilities to act as form like a moodle_form does.
 */

abstract class table_sql_form extends table_sql {
    static protected array $caches = [];

    protected array $forms = []; // Holds subforms of this table.
    protected array $forms_actions = []; // Data for form actions.
    protected array $formnames = []; // Headings of modals, human readable form names.

    public function __construct(
        protected array $constructorids,
        protected array $constructornames
    ) {
        parent::__construct($this->constructorids);
    }

    /**
     * This function MUST be implemented in each subclass. It has to do any permission checking to enable ajax-based storing of values.
     * If the function does not throw an exception, it is considered to be fine.
     * @return void
     * @throws \moodle_exception
     */
    abstract public function ajax_permission_check();

    /**
     * @param string $formid
     * @param string $col the name of the column, used for getting the correct showvalue upon changes.
     * @param array $rowids Data how this form identifies a single row., eg. 'id' => 2
     * @param array $fields Form fields to be edited within this form.
     * @param string $showtitle An optional title for the modal.
     * @param string $showvalue An optional value to be shown besides the edit icon.
     * @param string $icon the font awesome icon to use, defaults to 'fa fa-edit'
     * @return string
     */
    function as_modal_formfield(
        string $formid, string $col = '*', array $rowids = [], array $fields = [], string $showvalue = '', string $icon = 'fa fa-edit',
        string $btnclass = '', string $btnlabel = null, string $btnlabelclass = 'sr-only', bool $btnlabelisvalue = false
    ): string {
        global $OUTPUT;
        // Prove that this form exists.
        $form = $this->get_form($formid);
        $params = (object) [
            'btnclass' => $btnclass,
            'btnlabel' => $btnlabel ?? get_string('edit'),
            'btnlabelclass' => $btnlabelclass,
            'btnlabelisvalue' => $btnlabelisvalue ? 1 : 0,
            'col' => $col,
            'formid' => $formid,
            'fields' => base64_encode(json_encode($fields)),
            'fingerprint' => '',
            'icon' => $icon,
            'rowids' => [],
            'showtitle' => $this->formnames[$formid],
            'showvalue' => $showvalue,
            'table' => $this->htmluniqueid(),
        ];
        foreach ($rowids as $rowid => $value) {
            $params->rowids[] = (object)[ 'field' => $rowid, 'value' => $value ];
        }
        $params->fingerprint = md5(json_encode($params));
        return $OUTPUT->render_from_template('local_table_sql/as_modal_formfield', $params);
    }

    protected function add_form_action(
        string $formid, string $col = '*', array $rowids = [], array $showfields = [], string $type = 'other',
        string $label = '', string $id = '', bool $disabled = false, string $icon = 'fa fa-edit',
        string $btnclass = '', string $btnlabel = null, string $btnlabelclass = 'sr-only', bool $btnlabelisvalue = false
    ) {
        global $PAGE;
        $PAGE->requires->css('/local/table_sql/style/form_action.css');
        $PAGE->requires->js('/local/table_sql/js/form_action.js');
        // Prove that this form exists.
        $this->get_form($formid);
        if (empty($this->columns['__form_actions'])) {
            $this->define_column('__form_actions');
            $this->define_header('__form_actions');
        }

        $params = (object) [
            'btnclass' => $btnclass,
            'btnlabel' => $btnlabel ?? get_string('edit'),
            'btnlabelclass' => $btnlabelclass,
            'btnlabelisvalue' => $btnlabelisvalue ? 1 : 0,
            'col' => $col,
            'formid' => $formid,
            'fields' => base64_encode(json_encode($showfields)),
            'fingerprint' => '',
            'icon' => $icon,
            'rowids' => [],
            'showtitle' => $this->formnames[$formid],
            'showvalue' => $label,
            'table' => $this->htmluniqueid(),
        ];

        foreach ($rowids as $rowid) {
            $params->rowids[] = (object)[ 'field' => $rowid, 'value' => '{' . $rowid . '}' ];
        }
        $params->fingerprint = md5(json_encode($params));
        $this->forms_actions[] = $params;

        $onclick = "local_table_sql_form_action('{$params->fingerprint}-{id}', '{$formid}')";
        $href = "javascript:$onclick";
        $onclick = "";
        $this->add_row_action($href, $type, $label, $id, $disabled, $icon, $onclick);
    }

    function col___form_actions($row) {
        global $OUTPUT;
        $html = [];
        foreach ($this->forms_actions as $params) {
            $params = clone $params;
            foreach($params->rowids as $rowid) {
                if (!empty($row->{$rowid->field})) {
                    $rowid->value = $row->{$rowid->field};
                } else if (!empty($this->{$rowid->field})) {
                    $rowid->value = $this->{$rowid->field};
                }
            }
            $params->fingerprint = $params->fingerprint . "-" . $row->id;
            $html[] = $OUTPUT->render_from_template('local_table_sql/as_modal_formfield', $params);
            unset($params);
        }
        return implode("\n", $html);
    }

    /**
     * Add a table_sql_subform of this table_sql_form.
     * @param string $formid the id this is referenced
     * @param string $formname the visible name for the modal title
     * @param table_sql_subform $form the table_sql_subform object.
     * @return void
     * @throws \moodle_exception
     */
    function add_form(string $formid, string $formname, \local_table_sql\table_sql_subform $form): void {
        if (empty($this->forms[$formid])) {
            $this->forms[$formid] = $form;
            $this->formnames[$formid] = $formname;
        } else {
            throw new \moodle_exception("form $formid was added twice");
        }
    }
    function form_prefix(): string {
        return md5($this->htmluniqueid());
    }
    function define_column($column) {
        if (!is_array($this->columns)) { $this->define_columns([$column]); }
        if (!empty($this->columns[$column])) { throw new \moodle_exception('Duplicate column definition for ' . $column); }
        $this->columns[$column]         = count($this->columns);
        $this->column_style[$column]    = array();
        $this->column_class[$column]    = '';
        $this->columnsattributes[$column] = [];
        $this->column_suppress[$column] = false;
    }
    function get_class(): string {
        $cn = explode('\\', get_class($this));
        return end($cn);
    }
    public function get_form(string $formid): object {
        if (!isset($this->forms[$formid])) {
            throw new \moodle_exception('no form "' . $formid . '" found');
        }
        return $this->forms[$formid];
    }

    /**
     * Get the table initialization-code for a reload.
     * @return string
     */
    public function get_table_js_init(): string {
        return "start_table_sql(" . json_encode(array_merge([
                '__info' => is_siteadmin() ? 'Pretty print is only for admin!' : '',
                'container' => '#' . $this->htmluniqueid(),
            ], (array)$this->get_config()
            ), is_siteadmin() ? JSON_PRETTY_PRINT : 0) . ")";
    }

    function wrap_html_start() {
        global $OUTPUT, $PAGE;
        $uniqid = md5($this->htmluniqueid());
        $constructor = base64_encode(json_encode($this->constructorids, JSON_NUMERIC_CHECK));
        $classname = base64_encode(get_class($this));
        echo "<div id=\"{$uniqid}\" class=\"table_sql_form_controlbox\" data-classname=\"{$classname}\" data-constructor=\"{$constructor}\">\n";
    }
    function wrap_html_finish() {
        echo \html_writer::end_tag('div');
    }
}
