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
 * @package    local_classregister
 * @copyright  2024 Austrian Federal Ministry of Education
 * @author     GTN solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_table_sql;

use moodleform;
use local_classregister\lesson\incidents_table;
use local_classregister\locallib;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . "/formslib.php");

abstract class table_sql_subform extends moodleform {
    protected array $errors = [];
    protected bool $requirereload = false;

    public function __construct(protected $action=null, $customdata=null, protected $method='post', protected $target='', protected $attributes=null,
                                protected $editable=true, protected $ajaxformdata=null, protected array $rowselector = []) {
        parent::__construct($action, $customdata, $method, $target, $attributes, $editable, $ajaxformdata);
    }
    function definition() {}

    protected function definition_reset() {

        $this->_form = new \MoodleQuickForm($this->_formname, $this->method, $this->action, $this->target, $this->attributes, $this->ajaxformdata);
        if (!$this->editable){
            $this->_form->hardFreeze();
        }

        $this->definition();

        $this->_form->addElement('hidden', 'sesskey', null); // automatic sesskey protection
        $this->_form->setType('sesskey', PARAM_RAW);
        $this->_form->setDefault('sesskey', sesskey());
        $this->_form->addElement('hidden', '_qf__'.$this->_formname, null);   // form submission marker
        $this->_form->setType('_qf__'.$this->_formname, PARAM_RAW);
        $this->_form->setDefault('_qf__'.$this->_formname, 1);
        $this->_form->_setDefaultRuleMessages();

        // Hook to inject logic after the definition was provided.
        $this->after_definition();

        // we have to know all input types before processing submission ;-)
        $this->_process_submission($this->method);
    }

    /**
     * Transform the data transmitted via ajax to the receiver into a format,
     * that the moodleform supports.
     * @param object $rowdata
     * @return null
     */
    public static function prepare_ajax_data(object $rowdata) {
        foreach ($rowdata->rowids as $field => $value) {
            $_POST[$field] = $value;
        }
        if (!empty($rowdata->formdata)) {
            foreach ($rowdata->formdata as $field => $value) {
                $matches_unnamed = [];
                $matches_named = [];
                // Unnamed arrays
                preg_match('/^(.+)\[\]$/', $field, $matches_unnamed);
                preg_match('/^(.+)\[(.+)\]$/', $field, $matches_named);
                // Normal values
                if (empty($matches_unnamed[0]) && empty($matches_named[0])) {
                    $_POST[$field] = $value;
                    continue;
                }
                if ($matches_unnamed[0] == $field) {
                    if (!is_array($_POST[$matches_unnamed[1]])) {
                        $_POST[$matches_unnamed[1]] = [];
                    }
                    $_POST[$matches_unnamed[1]][] = $value;
                    continue;
                }
                // Named arrays
                if ($matches_named[0] == $field) {
                    if (!is_array($_POST[$matches_named[1]])) {
                        $_POST[$matches_named[1]] = [];
                    }
                    $_POST[$matches_named[1]][$matches_named[2]] = $value;
                    continue;
                }
            }
        }
    }

    /**
     * Return all errors after a validation from the protected _form-object.
     * @return void
     */
    function get_errors(): array {
        return $this->_form->_errors;
    }

    /**
     * Provide a function to get a single object for this form.
     * The protected $rowselector contains additional fields to ensure, that only
     * such values are loaded from the database, that the containing table_sql provides access to.
     * An implementation can be e.g.
     *      global $DB;
     *      $selector = array_merge($selector, $this->rowselector);
     *      return $DB->get_record('local_myplugin', $selector);
     * @param array $selector
     * @return object|null
     */
    abstract public function get_row(array $selector): ?object;

    /**
     * Indicate if a change within a form requires a reload of the table.
     * @return bool
     */
    public function requires_reload(): bool {
        return $this->requirereload;
    }

    /**
     * Provide a function to store a single object based in this form.
     * @param array $data
     * @return object|null
     */
    abstract public function store_row(object $data): ?object;

    /**
     * Checks if the row-identifying values $this->rowselectore have not changed.
     * @param array $data
     * @return bool
     */
    protected function store_row_check(object $data, bool $exception = false): bool {
        foreach ($this->rowselector as $requiredfield => $requiredvalue) {
            if ($data->{$requiredfield} != $requiredvalue) {
                if ($exception) {
                    if (!isset($data->{$requiredfield})) {
                        $data->{$requiredfield} = 'NULL';
                    }
                    $debuginfo = "$requiredfield from $requiredvalue to " . $data->{$requiredfield};
                    throw new \moodle_exception('exception:key_value_change_prohibited', 'local_table_sql', '', [], $debuginfo);
                }
                return false;
            }
        }
        return true;
    }
}
