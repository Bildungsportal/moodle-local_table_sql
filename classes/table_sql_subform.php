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

defined('MOODLE_INTERNAL') || die;

global $CFG;
require_once($CFG->libdir . "/formslib.php");

/**
 * Base class for subforms of table_sql.\
 * @method void store_row(object $data) Implement this method to store the data of the subform.
 * @method object get_row(int $id) Implement this method to get the data of the subform.
 */
abstract class table_sql_subform extends moodleform {
    public function __construct(protected $action = null, $customdata = null, protected $method = 'post', protected $target = '', protected $attributes = null,
        protected $editable = true, protected $ajaxformdata = null, protected array $rowselector = [], protected string $title = '') {
        parent::__construct($action, $customdata, $method, $target, $attributes, $editable, $ajaxformdata);
    }

    // Info: buttons get removed in javascript, so actually a moodleform can be used for the subform
    // public function table_sql_form_prepare_output($fields) {
    //     // remove buttons for xhr form, buttons are printed in the popup window
    //     if ($this->_form->elementExists('buttonar')) {
    //         $this->_form->removeElement('buttonar');
    //     }
    //     if ($this->_form->elementExists('submitbutton')) {
    //         $this->_form->removeElement('submitbutton');
    //     }
    // }

    /**
     * Reset the definition of a form, so that it can be customized for a particular record.
     * this function is copied from moodleform::__construct()
     * @return void
     * @throws \moodle_exception
     */
    protected function definition_reset() {
        // this function is copied from moodleform::__construct()
        $this->_form = new \MoodleQuickForm($this->_formname, $this->method, $this->action, $this->target, $this->attributes, $this->ajaxformdata);
        if (!$this->editable) {
            $this->_form->hardFreeze();
        }

        $this->definition();

        $this->_form->addElement('hidden', 'sesskey', null); // automatic sesskey protection
        $this->_form->setType('sesskey', PARAM_RAW);
        $this->_form->setDefault('sesskey', sesskey());
        $this->_form->addElement('hidden', '_qf__' . $this->_formname, null);   // form submission marker
        $this->_form->setType('_qf__' . $this->_formname, PARAM_RAW);
        $this->_form->setDefault('_qf__' . $this->_formname, 1);
        $this->_form->_setDefaultRuleMessages();

        // Hook to inject logic after the definition was provided.
        $this->after_definition();

        // we have to know all input types before processing submission ;-)
        $this->_process_submission($this->method);
    }

    protected function get_form_identifier() {
        // for anonymous clases the classname changes on each reload and so the formid changes too
        // and thus the POST request is not recognized as a form submission
        $reflectionClass = new \ReflectionClass($this);
        if ($reflectionClass->isAnonymous()) {
            // Get the file and line where the anonymous class is defined
            $file = $reflectionClass->getFileName();
            $startLine = $reflectionClass->getStartLine();
            return 'anonymous_' . md5($file . ':' . $startLine);
        }

        return parent::get_form_identifier();
    }

    /**
     * Checks if the row-identifying values $this->rowselectore have not changed.
     * @param array $data
     * @return bool
     */
    protected function store_row_check(object $data, bool $exception = true): bool {
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

    /**
     * @param $title
     * @return void
     */
    public function set_title($title): void {
        $this->title = $title;
    }

    public function get_title(): string {
        return $this->title;
    }

    public function _getDefaultValues() {
        return $this->_form->_defaultValues;
    }
}
