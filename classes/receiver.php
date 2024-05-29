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

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;

defined('MOODLE_INTERNAL') || die;

require_once("$CFG->libdir/tablelib.php");

class receiver extends \external_api {
    public static function receive_parameters() {
        return new external_function_parameters([
            'rowdata' => new external_value(PARAM_RAW, 'Row as JSON'),
        ]);
    }
    public static function receive($rowdata) {
        global $OUTPUT, $PAGE;
        $PAGE->set_context(\context_system::instance());
        $params = static::validate_parameters(static::receive_parameters(), [
            'rowdata' => $rowdata,
        ]);
        $rowdata = json_decode($params['rowdata']);
        if (!class_exists($rowdata->classname)) {throw new \moodle_exception('invalid class ' . $rowdata->classname); }
        \local_table_sql\table_sql_subform::prepare_ajax_data($rowdata);

        $result = (object) [
            'colreplace' => '',
            'errors' => '',
            'form' => '',
            'pageendcode' => '',
        ];
        $table = new $rowdata->classname(...$rowdata->constructor);
        $table->ajax_permission_check();
        $form = $table->get_form($rowdata->formid);
        $data = $form->get_row((array) $rowdata->rowids);
        if ($sentdata = $form->get_data()) {
            foreach ($sentdata as $field => $value) {
                $data->{$field} = $value;
            }
            $data = $form->store_row($data);
            $funcname = "col_{$rowdata->col}";
            if ($form->requires_reload() || $rowdata->col == '*') {
                $result->pageendcode .= $table->get_table_js_init();
            } else if (method_exists($table, $funcname)) {
                $result->colreplace = $table->{$funcname}($data);
            } else {
                $result->colreplace = $table->other_cols($rowdata->col, $data);
            }
        } else {
            $form->set_data($data);

            /* It seems weird, as these lines do not produce any output.
             * However, they are absolutely required, so that get_head_code and  get_end_code provides all required
             * javascripts so that the form fields work (e.g. file picker).
             */
            ob_start();
            $OUTPUT->header();
            ob_get_clean();
            ob_start();
            $result->form = $form->render();
            $OUTPUT->footer();
            ob_get_clean();

            $headcode = $PAGE->requires->get_head_code($PAGE, $OUTPUT);
            $loadpos = strpos($headcode, 'M.yui.loader');
            $cfgpos = strpos($headcode, 'M.cfg');
            $script = substr($headcode, $loadpos, $cfgpos-$loadpos);
            $endcode = $PAGE->requires->get_end_code();
            $script .= preg_replace('/<\/?(script|link)[^>]*>|\/\/<!\[CDATA\[|\/\/\]\]>/', '', $endcode);
            // The default output overwrites require and destroys functionality of the current page. Therefore, we rename it.
            $script = str_replace('var require =', 'var __require = ', $script);

            $result->pageendcode = $script;
        }
        return $result;
    }
    public static function receive_returns() {
        return new external_single_structure(
            array(
                'colreplace' => new external_value(PARAM_RAW, 'new html code to replace the cell'),
                'errors' => new external_value(PARAM_TEXT, 'if an error occurred contains error information '),
                'form' => new external_value(PARAM_RAW, 'the form as html.'),
                'pageendcode' => new external_value(PARAM_RAW, 'the javascript code of the page end.'),
            )
        );
    }
}

