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

defined('MOODLE_INTERNAL') || die;

require_once("$CFG->libdir/tablelib.php");

/**
 * This is a subclass of table_sql that adds capabilities to act as form like a moodleform does.
 */
abstract class table_sql_form extends table_sql {
    /**
     * @var table_sql_subform[]
     */
    protected array $forms = []; // Holds subforms of this table.

    public function __construct($uniqueid = null) {
        global $PAGE;

        parent::__construct($uniqueid);

        // preload form.js
        $PAGE->requires->js_call_amd('local_table_sql/form');
    }

    protected function handle_xhr_action($action) {
        global $CFG, $OUTPUT, $PAGE;

        if ($action == 'form_show' || $action == 'form_save') {
            // $rowid = required_param('rowid', PARAM_RAW);
            $rowid = $_REQUEST['rowid'] ?? null; // can be scalar or array
            $formid = required_param('formid', PARAM_TEXT);

            $form = $this->get_form($formid);

            if ($rowid) {
                // if ($this->isMethodOverridden($form, table_sql_subform::class, 'get_row')) {
                if (method_exists($form, 'get_row')) {
                    $target = $form;
                } else {
                    $target = $this;
                }
                if ($this->parameterIsType($target, 'get_row', 0, 'array') && !is_array($rowid)) {
                    // for scalar
                    $rowid = ['id' => $rowid];
                }
                $row = $target->get_row($rowid);
            } else {
                $row = (object)[];
            }

            $result = (object)[
                'colreplace' => '',
                'errors' => '',
                'form' => '',
                'pageendcode' => '',
                'modal_title' => $form->get_title() ?: get_string('edit'),
            ];

            if ($sentdata = $form->get_data()) {
                if (is_array($rowid)) {
                    $data = (object)array_merge($rowid, (array)$sentdata);
                } else {
                    $data = (object)array_merge([
                        'id' => $rowid,
                    ], (array)$sentdata);
                }

                // if ($this->isMethodOverridden($form, table_sql_subform::class, 'store_row')) {
                if (method_exists($form, 'store_row')) {
                    $form->store_row($data);
                } else {
                    $this->store_row($data);
                }

                /*
                $funcname = "col_{$rowdata->col}";
                if (method_exists($this, $funcname)) {
                    $result->colreplace = $this->{$funcname}($data);
                } else {
                    $result->colreplace = $this->other_cols($rowdata->col, $data);
                }
                */
            } else {
                $form->set_data($row);
                $oldDefaultValues = $form->_getDefaultValues();

                // TODO: wie ginge das anders?
                // alte version
                /* It seems weird, as these lines do not produce any output.
                 * However, they are absolutely required, so that get_head_code and  get_end_code provides all required
                 * javascripts so that the form fields work (e.g. file picker).
                 */
                ob_start();
                $OUTPUT->header();
                $result->form = $form->render();
                $OUTPUT->footer();

                // Check if the default values have changed in defintion_after_data() (called inside render)
                // It is not supported to use setDefault() inside definition_after_data, because it would override the values from set_data()
                if ($oldDefaultValues !== $form->_getDefaultValues()) {
                    throw new \moodle_exception('default values in definition_after_data() changed using $form->setDefault()! This would override the values of set_data(). Set default values in definition() only!');
                }

                // this prevents in moodle 4.5 that an extra </body></html> is printed in the json after $OUTPUT->header()
                $CFG->closingtags = '';


                $headcode = $PAGE->requires->get_head_code($PAGE, $OUTPUT);
                ob_get_clean();
                $loadpos = strpos($headcode, 'M.yui.loader');
                $cfgpos = strpos($headcode, 'M.cfg');
                $script = substr($headcode, $loadpos, $cfgpos - $loadpos);
                $endcode = $PAGE->requires->get_end_code();
                $script .= preg_replace('/<\/?(script|link)[^>]*>|\/\/<!\[CDATA\[|\/\/\]\]>/', '', $endcode);
                // The default output overwrites require and destroys functionality of the current page. Therefore, we rename it.
                $script = str_replace('var require =', 'var __disabled_require = ', $script);


                // neue version: geht aber nicht, weil z.B. die strings nicht geladen werden
                /*
                ob_start();
                echo $OUTPUT->header();

                $PAGE->requires->js_init_code("// <table_sql-head-code>\n");
                $result->form = $form->render();
                $PAGE->requires->js_init_code("// </table_sql-head-code>\n");

                echo $OUTPUT->footer();

                $output = ob_get_clean();

                // this prevents in moodle 4.5 that an extra </body></html> is printed in the json after $OUTPUT->header()
                $CFG->closingtags = '';

                $script = preg_replace('!.*<table_sql-head-code>|// </table_sql-head-code>.*!s', '', $output);
                */

                $result->pageendcode = $script;
            }

            return $result;
        }

        return parent::handle_xhr_action($action);
    }

    /**
     * @param string $formid
     * @param string $column the name of the column, used for getting the correct showvalue upon changes.
     * @param int $rowid Data how this form identifies a single row., eg. 'id' => 2
     * @param string|null $content An optional value to be shown besides the edit icon. Security Warning: this should be html escaped with s()!
     * @param string $icon the font awesome icon to use, defaults to 'fa fa-edit'
     * @param string $btnclass
     * @param string|null $btnlabel
     * @param string $btnlabelclass
     * @return string
     */
    function as_modal_formfield(
        string $formid = '__default_form', string $column = '', int $rowid = 0, ?string $content = null, string $icon = 'fa fa-edit',
        array $fields = [],
        string $btnclass = '', string $btnlabel = null, string $btnlabelclass = 'sr-only'
    ): string {
        global $OUTPUT;

        $row = $this->get_current_row();

        if (!$rowid && $row) {
            $rowid = $row->id;
        }

        if ($content === null && $column) {
            // other_cols also escapes the value
            $content = $this->other_cols($column, $row);
        }

        if ($this->is_downloading()) {
            return $content;
        }

        // make sure form exists
        $form = $this->get_form($formid);

        $params = (object)[
            'btnclass' => $btnclass,
            'btnlabel' => $btnlabel ?? get_string('edit'),
            'btnlabelclass' => $btnlabelclass,
            'icon' => $icon,
            'content' => $content,
            'modaldata' => json_encode([
                'showfields' => $fields,
                'xhrdata' => [
                    'uniqueid' => $this->uniqueid,
                    'formid' => $formid,
                    'rowid' => $rowid,
                    'column' => $column,
                ],
            ]),
        ];

        return $OUTPUT->render_from_template('local_table_sql/as_modal_formfield', $params);
    }

    protected function add_form_action(
        table_sql_subform|string $formid, string $type = 'edit',
        string $label = '', string $id = '', bool $disabled = false, string $icon = '',
        array $rowid = [], array $fields = [],
    ) {
        if ($formid instanceof table_sql_subform) {
            $form = $formid;
            $formid = '__edit_form';
            $this->add_form($formid, $form);
        }

        // Check that this form exists.
        $this->get_form($formid);

        $href = "#";
        $onclick = ''; // will be filled in get_row_actions_v2()

        $customdata = (object)[
            'is_form_action' => true,
            'formid' => $formid,
            'rowid' => $rowid,
            'fields' => $fields,
        ];
        $this->add_row_action($href, $type, $label, $id, $disabled, $icon, $onclick, $customdata);
    }

    protected function get_row_actions_v2(object $row): array {
        $row_actions = parent::get_row_actions_v2($row);

        foreach ($row_actions as $row_action) {
            if ($row_action->customdata?->is_form_action) {
                $formid = $row_action->customdata->formid;
                $rowid = $row_action->customdata->rowid;
                $fields = $row_action->customdata->fields;

                // remove customdata in row_action output, not needed on the client
                unset($row_action->customdata);

                if (!$rowid) {
                    $rowid = $row->id;
                } else {
                    $orig_rowid = $rowid;
                    $rowid = (object)[];
                    foreach ($orig_rowid as $field) {
                        if (!empty($row->{$field})) {
                            $rowid->{$field} = $row->{$field};
                        } else if (!empty($this->{$field})) {
                            $rowid->{$field} = $this->{$field};
                        }
                    }
                }

                $modaldata = (object)[
                    'showfields' => $fields,
                    'xhrdata' => (object)[
                        'uniqueid' => $this->uniqueid,
                        'formid' => $formid,
                        'rowid' => $rowid,
                    ],
                ];

                ob_start();
                ?>
                <script>
                    function (e, row) {
                        e.preventDefault();

                        var modaldata = <?=json_encode($modaldata)?>;
                        require(['local_table_sql/form'], function (f) {
                            f.loadModal(e.target, modaldata);
                        });
                    }
                </script>
                <?php
                $row_action->onclick = trim(preg_replace('!</?script>!', '', ob_get_clean()));
            }
        }

        return $row_actions;
    }


    /**
     * Add a table_sql_subform of this table_sql_form.
     * @param string|table_sql_subform $formid the id this is referenced
     * @param table_sql_subform $form the table_sql_subform object.
     * @param string $title @deprecated the title of the form.
     * @return void
     * @throws \moodle_exception
     */
    function add_form(string|table_sql_subform $formid, table_sql_subform $form = null, string $title = ''): void {
        if ($formid instanceof table_sql_subform) {
            $form = $formid;
            $formid = '__default_form';
        }

        if ($title) {
            $form->set_title($title);
        }

        if (empty($this->forms[$formid])) {
            $this->forms[$formid] = $form;
        } else {
            throw new \moodle_exception("form $formid was added twice");
        }
    }

    protected function get_row($id): ?object {
        global $DB;

        $this->setup();
        list($sql, $params) = $this->get_sql_and_params();

        $sql = "select * from ($sql) as form_rows WHERE id=?";
        $row = $DB->get_record_sql($sql, array_merge($params, [$id]));

        return $row ?: null;
    }


    protected function store_row(object $data): void {
        global $DB;

        $table = $this->sql->table ?? $this->sql->from;
        if (!preg_match('!^[^\s]+$!', $table)) {
            throw new \moodle_exception('table name not set, use table_sql->set_table_name()');
        }

        if (!empty($data->id)) {
            $DB->update_record($table, $data);
        } else {
            $DB->insert_record($table, $data);
        }
    }

    public function get_form(string $formid): table_sql_subform {
        if (!isset($this->forms[$formid])) {
            throw new \moodle_exception("form '{$formid}' not found");
        }

        return $this->forms[$formid];
    }

    /*
    protected function isMethodOverridden($childClass, $parentClass, $methodName) {
        // Check if the method exists in both the parent and child class
        if (method_exists($childClass, $methodName) && method_exists($parentClass, $methodName)) {
            // Use Reflection to get detailed information about the methods
            $parentMethod = new \ReflectionMethod($parentClass, $methodName);
            $childMethod = new \ReflectionMethod($childClass, $methodName);

            // Compare the class where the method was declared
            if ($parentMethod->getDeclaringClass()->getName() !== $childMethod->getDeclaringClass()->getName()) {
                return true; // Method has been overridden in the child class
            }
        }

        return false; // Method has NOT been overridden
    }
    */

    protected function parameterIsType($className, $methodName, $num, $type) {
        $reflectionMethod = new \ReflectionMethod($className, $methodName);
        $parameters = $reflectionMethod->getParameters();

        if (isset($parameters[$num])) {
            return $parameters[$num]->hasType() && $parameters[$num]->getType()->getName() === $type;
        }

        return false;
    }
}
