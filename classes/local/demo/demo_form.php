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

defined('MOODLE_INTERNAL') || die;

class demo_form extends \local_table_sql\table_sql_subform {
    function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('select', 'groupid', get_string('group'), static::get_groups());
        $mform->setType('groupid', PARAM_INT);

        $mform->addElement('text', 'label1', 'Label 1');
        $mform->setType('label1', PARAM_TEXT);

        $mform->addElement('text', 'label2', 'Label 2');
        $mform->setType('label2', PARAM_TEXT);

        $this->add_action_buttons();
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

    function get_row(array $selector): object {
        global $DB;
        $selector = array_merge($selector, $this->rowselector);
        return $DB->get_record('local_table_sql_demo', $selector);
    }

    function store_row(object $data): ?object {
        global $DB;
        $this->store_row_check($data, true);
        if (!empty($data->id)) {
            $DB->update_record('local_table_sql_demo', $data);
        } else {
            $data->id = $DB->insert_record('local_table_sql_demo', $data);
        }
        return $data;
    }

    function validation($data, $files): array {
        global $DB;
        $errors = [];
        if (strlen($data['label1']) > 5) {
            $errors['label1'] = 'Maximum 5 chars';
        }
        if (strlen($data['label2']) < 6) {
            $errors['label2'] = 'Minimum 5 chars';
        }
        return $errors;
    }
}
