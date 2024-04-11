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

require_once('../../../config.php');

use local_table_sql\demo\demo_table_form;

$PAGE->set_url('/local/table_sql/demo/form_table.php', []);
$PAGE->set_context(\context_system::instance());
$PAGE->set_title("Demo for table_sql_form");
$PAGE->set_heading("Demo for table_sql_form");

require_admin();

$table = new demo_table_form();
echo $OUTPUT->header();
$table->out();
echo $OUTPUT->footer();
