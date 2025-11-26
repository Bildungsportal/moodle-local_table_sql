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
use local_table_sql\table_sql_subform;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . "/formslib.php");

class demo_table_form extends table_sql_form {
    protected function define_table_configs() {
        $sql = "SELECT * FROM {local_table_sql_demo}";
        $this->set_sql_query($sql);

        // this is needed, so local_table_sql knows where to store the rows
        $this->set_sql_table('local_table_sql_demo');

        // Define headers and columns.
        $cols = [
            'id' => 'id',
            'groupid' => 'groupid',
            'label1' => 'label1 (maximum 5 chars)',
            'label2' => 'label2 (minimum 5 chars)',
            'content' => 'editor',
            // 'files' => 'files',
            'files_advanced' => 'files_advanced',
            'valuelist' => 'multiselect',
        ];
        $this->set_table_columns($cols);

        $this->sortable(true, 'id', SORT_ASC);

        // $this->set_column_options('files', no_sorting: true, no_filter: true);
        $this->set_column_options('files_advanced', no_sorting: true, no_filter: true);

        // enable the crud functionality by providing a table_sql_subform
        $this->enable_crud(new class extends table_sql_subform {
            function definition() {
                $mform = $this->_form;

                $mform->addElement('select', 'groupid', get_string('group'), static::get_groups());
                $mform->setType('groupid', PARAM_INT);

                $mform->addElement('text', 'label1', 'Label 1');
                $mform->setType('label1', PARAM_TEXT);

                $mform->addElement('text', 'label2', 'Label 2');
                $mform->setType('label2', PARAM_CLEANHTML);

                $mform->addElement('editor', 'content', 'editor');
                $mform->setType('editor', PARAM_RAW);

                /*
                $mform->addElement('filemanager', 'files', 'files', null, [
                    'subdirs' => 0,
                    'maxbytes' => 1024 * 1024,
                    'maxfiles' => 10,
                    'accepted_types' => ['document', 'image'],
                ]);
                */
                $mform->addElement('filemanager', 'files_advanced', 'files_advanced', null, [
                    'subdirs' => 0,
                    'maxbytes' => 1024 * 1024,
                    'maxfiles' => 10,
                    'accepted_types' => ['document', 'image'],
                ]);

                // Add a multiselect element
                $options = [
                    'option1' => 'Option 1',
                    'option2' => 'Option 2',
                    'option3' => 'Option 3',
                    'option4' => 'Option 4',
                ];
                $mform->addElement('select', 'valuelist', 'Multiselect test:', $options, ['multiple' => 'multiple']);
                $mform->setType('valuelist', PARAM_TEXT); // Set data type
                $mform->addRule('valuelist', null, 'required', null, 'client'); // Add validation
            }

            public static function get_group($id): string {
                $groups = static::get_groups();
                return $groups[$id] ?? '';
            }

            public static function get_groups(): array {
                return [
                    get_string('none'), 'Group 1', 'Group 2', 'Group 3', 'Group 4', 'Group 5', 'Group 6', 'Group 7',
                    'Group 8', 'Group 9', 'Group 10', 'Group 11', 'Group 12', 'Group 13', 'Group 14', 'Group 15', 'Group 16',
                ];
            }

            // only needed, for custom validation rules
            // normally use $mform->addRule() instead
            function validation($data, $files): array {
                $errors = [];
                if (strlen($data['label1']) > 5) {
                    $errors['label1'] = 'Maximum 5 chars';
                }
                if (strlen($data['label2']) < 6) {
                    $errors['label2'] = 'Minimum 5 chars';
                }
                return $errors;
            }
        });

        // second demo mit custom form, this form is used to edit only specific fields
        $this->add_form('demo', new class extends table_sql_subform {
            function definition() {
                $mform = $this->_form;

                $mform->addElement('select', 'groupid', 'CUSTOM FORM', static::get_groups());
                $mform->setType('groupid', PARAM_INT);

                $mform->addElement('text', 'label1', 'Label 1');
                $mform->setType('label1', PARAM_TEXT);

                $mform->addElement('text', 'label2', 'Label 2');
                $mform->setType('label2', PARAM_CLEANHTML);

                // Add a multiselect element
                $options = [
                    'option1' => 'Option 1',
                    'option2' => 'Option 2',
                    'option3' => 'Option 3',
                    'option4' => 'Option 4',
                ];
                $mform->addElement('select', 'multiselect', 'Multiselect test:', $options, ['multiple' => 'multiple']);
                $mform->setType('multiselect', PARAM_TEXT); // Set data type
                $mform->addRule('multiselect', null, 'required', null, 'client'); // Add validation
            }

            public static function get_group($id): string {
                $groups = static::get_groups();
                return $groups[$id] ?? '';
            }

            public static function get_groups(): array {
                return [
                    get_string('none'), 'Group 1', 'Group 2', 'Group 3', 'Group 4', 'Group 5', 'Group 6', 'Group 7',
                    'Group 8', 'Group 9', 'Group 10', 'Group 11', 'Group 12', 'Group 13', 'Group 14', 'Group 15', 'Group 16',
                ];
            }

            function validation($data, $files): array {
                $errors = [];
                if (strlen($data['label1']) > 5) {
                    $errors['label1'] = 'Maximum 5 chars';
                }
                if (strlen($data['label2']) < 6) {
                    $errors['label2'] = 'Minimum 5 chars';
                }
                return $errors;
            }
        });

        /*
        $this->add_form_action(
            formid: 'demo',
            label: get_string('edit'),
            type: 'edit'
        );

        $this->add_form('form_elements_test', new class extends table_sql_subform {
            function definition() {
                $mform = $this->_form;

                $mform->addElement('html', 'Demonstration of different form elements');

                $mform->addElement('filemanager', 'filemanager', 'filemanager');
                $mform->setType('filemanager', PARAM_TEXT);
            }
        });

        $this->enable_create_form(formid: 'form_elements_test', btnlabel: 'Test Moodle Form Elements');
        */

        // demo: enable download functionlality
        $this->is_downloadable(true);
    }

    function store_row(object $data): void {
        // demo how to set default values when inserting a new record
        if (empty($data->id)) {
            $data->timecreated = time();
        }

        parent::store_row($data);

        // demo: store the files
        $context = \context_system::instance();
        \file_save_draft_area_files(
            $data->files_advanced, $context->id, 'local_table_sql', 'demo_files_advanced',
            $data->id
        );
    }

    function get_row($id): ?object {
        $row = parent::get_row($id);

        // demo: prepare the filemanager draft area
        $context = \context_system::instance();
        $row->files_advanced = file_get_submitted_draft_itemid('files_advanced');
        file_prepare_draft_area(
            $row->files_advanced,
            $context->id,
            'local_table_sql',
            'demo_files_advanced',
            $row->id,
        );

        return $row;
    }

    function delete_row(object $row) {
        parent::delete_row($row);

        // demo: delete the files when the row was deleted
        $context = \context_system::instance();
        $fs = get_file_storage();
        $fs->delete_area_files(
            $context->id,
            'local_table_sql',
            'demo_files_advanced',
            $row->id
        );
    }

    function col_groupid($row) {
        return $this->as_modal_formfield(
            'demo', 'groupid',
            content: $this->get_form('demo')::get_group($row->groupid),
            fields: ['groupid'],
        );
    }

    function col_label2($row) {
        return $this->as_modal_formfield(
            'demo', 'label2',
            content: '<b data="test-html">' . s($row->label2) . '</b>'
        );
    }

    // demo: content of the files column
    function col_files_advanced($row) {
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            \context_system::instance()->id,
            'local_table_sql',
            'demo_files_advanced',
            $row->id,
            includedirs: false
        );

        return join('<br/>', array_map(fn($file) => $file->get_filename(), $files)) ?: '-';
    }
}
