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


defined('MOODLE_INTERNAL') || die;

function xmldb_local_table_sql_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();
    if ($oldversion < 2024013100) {
        $table = new xmldb_table('local_table_sql_demo');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('groupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('label1', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $table->add_field('label2', XMLDB_TYPE_CHAR, '50', null, null, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // TODO: add this into install.php
        for ($a = 0; $a < 60; $a++) {
            $record = [
                'timecreated' => strtotime("-{$a} weeks"),
                'groupid' => $a % 5,
                'label1' => substr(uniqid(), 0, rand(2, 4)),
                'label2' => substr(uniqid(), 0, rand(6, 10)),
            ];
            $DB->insert_record('local_table_sql_demo', $record);
        }
        upgrade_plugin_savepoint(true, 2024013100, 'local', 'table_sql');
    }

    // always check timezone after upgrade (TODO: add this to install.php)
    // correct timezone conversion is needed, so the fulltext search on timestamp columns works in mysql
    if ($DB->get_dbfamily() == 'mysql') {
        $result = $DB->get_field_sql("SELECT CONVERT_TZ(FROM_UNIXTIME(?), 'SYSTEM', 'Europe/Berlin')", [time()]);
        if (!$result || str_starts_with($result, '1970')) {
            // empty conversion, or converted to 1970-01-01 01:00:00

            $error = 'Timezone Europe/Berlin not installed, check result: ' . $result;

            // don't throw the error, else the upgrade won't run through
            // throw new \moodle_exception($error);

            // instead print it
            ?>
            <div class="box py-3 errorbox alert alert-danger">
                <p class="errormessage"><?=$error?></p>
            </div>
            <?php
        }
    }

    return true;
}
