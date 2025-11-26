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
require_admin();

use local_table_sql\local\demo\demo_table;

$PAGE->set_context(\context_system::instance());
$PAGE->set_url('/local/table_sql/demo/', []);
$PAGE->set_heading("Demo Table for local_table_sql");
$PAGE->set_title("Demo Table for local_table_sql");

$table = new demo_table();
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_table_sql/demo/navigation', []);
$table->out();
echo $OUTPUT->footer();
