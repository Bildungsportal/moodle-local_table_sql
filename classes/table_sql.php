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
 * @copyright  2022 Austrian Federal Ministry of Education
 * @author     GTN solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_table_sql;

use core_user;
use dml_exception;
use moodle_exception;
use moodle_url;
use table_sql as moodle_table_sql;
use function fullname;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/tablelib.php');

class table_sql extends moodle_table_sql {

    private bool $enable_row_selection = false;

    private ?bool $enable_global_filter = null;
    private ?bool $enable_column_filters = null;
    private bool $enable_page_size_selector = true;
    private int $initial_page_index = 0;

    // private bool $has_tile_view = false;
    private array $row_actions = [];
    private string $row_actions_js_callback = '';

    private bool $enable_detail_panel = false;
    private string $render_detail_panel_js_callback = '';
    private ?bool $row_actions_display_as_menu = null;
    private array $column_nofilter = [];
    private array $column_options = [];

    private bool $immediately_call_actions = true;

    private bool $is_xhr = false;
    private array $xhr_formatted_data = [];

    private array $full_text_search_columns = [];

    var $showdownloadbuttonsat = [TABLE_P_BOTTOM];
    private string $download_name = '';

    const PARAM_TEXT = 'text';
    const PARAM_NUMBER = 'number';
    const PARAM_TIMESTAMP = 'timestamp';
    const PARAM_UNKNOWN = 'unknown';

    private $datetime_format = '%d.%m.%Y %H:%M';

    public function __construct($uniqueid = null) {
        global $PAGE;

        if ($uniqueid === null) {
            // use the whole classname and namespace, then create an md5 to hide implementation details to the client
            $uniqueid = str_replace('\\', '.', get_class($this));
        } elseif (is_array($uniqueid)) {
            // create a uniqueid of the parameters and the current class
            // don't use __CLASS__, because it will give you local_table_sql/sql_table
            $uniqueid = join('-', array_merge([str_replace('\\', '.', get_class($this))], $uniqueid));
        } elseif (is_string($uniqueid)) {
            // ok
        } else {
            throw new \moodle_exception("incorrect uniqueid '{$uniqueid}'");
        }

        if (optional_param('is_xhr', false, PARAM_BOOL)) {
            // falls mehrere table_sql auf einer Seite sind, dann die xhr actions nur ausführen, wenn die korrekte uniqueid übergeben wurde
            $request_uniqueid = optional_param('uniqueid', '', PARAM_TEXT);
            if (!$request_uniqueid || $uniqueid === $request_uniqueid) {
                $this->is_xhr = true;
            }
        }

        parent::__construct($uniqueid);

        $this->attributes['id'] = $uniqueid;

        $this->collapsible(false);
        $this->sortable(true, '_change_default_column_later_', SORT_ASC);
        $this->pageable(true);

        // first set is_downloading to true if the user requested a download
        // this way is_downloading() returns true in the table_config() function to eg. use different columns when downloading
        $request_uniqueid = optional_param('uniqueid', '', PARAM_TEXT);
        $download = optional_param('download', '', PARAM_ALPHA);
        if ($download && $this->uniqueid === $request_uniqueid) {
            $this->download = $download;
            // don't use is_downloading(true), because this already sends the headers!
            // $this->is_downloading(true);
        }

        // Define columns in the table.
        $this->define_table_columns();

        // Define configs.
        $this->define_table_configs();

        if ($this->sort_default_column == '_change_default_column_later_') {
            // first column is default column (if not set)
            $this->sort_default_column = array_keys($this->columns)[0];
            if (strpos($this->sort_default_column, 'time') !== false) {
                // for time columns sort desc by default
                $this->sort_default_order = SORT_DESC;
            }
        }

        // move delete closer to edit button
        if (@$this->columns['edit'] && @$this->columns['delete']) {
            $this->column_style('delete', 'padding-left', '0.25rem');
        }

        if (@$this->columns['edit']) {
            $this->column_class('edit', 'edit');
            $this->no_filter('edit');
            $this->no_sorting('edit');
        }
        if (@$this->columns['delete']) {
            $this->column_class('delete', 'delete');
            $this->no_filter('delete');
            $this->no_sorting('delete');
        }

        if (empty($this->baseurl)) {
            // debugging('You should set baseurl when using flexible_table.');
            global $PAGE;
            $this->baseurl = $PAGE->url;
        }

        if (headers_sent()) {
            throw new moodle_exception('headers already sent and output already started! create a new table_sql before calling $OUTPUT->header()!');
        }

        if ($PAGE->requires) {
            // For Typo3 Integration: in typo3 $PAGE->requires is not available

            $PAGE->requires->css('/local/table_sql/style/main.css');
        }

        $this->out_actions();
    }

    /**
     * Add a single header.
     */
    function define_header(string $header): void {
        if (empty($this->headers)) {
            $this->define_headers([$header]);
        } else {
            $this->headers[] = $header;
        }
    }

    /**
     * Setup the headers for the table.
     */
    protected function define_table_columns() {
        // Define headers and columns.
        $cols = [
            'id' => 'id',
        ];

        $this->define_columns(array_keys($cols));
        $this->define_headers(array_values($cols));
    }

    protected function set_column_options($column, string $sql_column = null, array $select_options = null, string $data_type = null, bool $hidden = null, bool $visible = null, bool $internal = null, bool $no_filter = null, bool $no_sorting = null, string $onclick = null) {
        global $CFG;

        if (!isset($this->columns[$column])) {
            throw new \moodle_exception("column $column not found");
        }

        if (!isset($this->column_options[$column])) {
            $this->column_options[$column] = [];
        }

        if ($select_options !== null) {
            $this->column_options[$column]['select_options'] = $select_options;
        }
        if ($sql_column !== null) {
            $this->column_options[$column]['sql_column'] = $sql_column;
        }
        if ($data_type !== null) {
            if (!in_array($data_type, [static::PARAM_NUMBER, static::PARAM_TEXT, static::PARAM_TIMESTAMP])) {
                throw new \moodle_exception("data_type {$data_type} is unknown, use the PARAM_* constants!");
            }
            $this->column_options[$column]['data_type'] = $data_type;
        }

        if ($hidden !== null) {
            if (!empty($CFG->developermode)) {
                throw new \moodle_exception("column option 'hidden' was changed to 'internal'");
            }
            // old logic
            $this->column_options[$column]['internal'] = $hidden;
            if ($hidden) {
                // internal columns can not be filtered, searched or sorted
                $this->no_filter($column);
                $this->no_sorting($column);
            }
        }
        if ($internal !== null) {
            $this->column_options[$column]['internal'] = $internal;
            if ($internal) {
                // internal columns can not be filtered, searched or sorted
                $this->no_filter($column);
                $this->no_sorting($column);
            }
        }
        if ($visible !== null) {
            $this->column_options[$column]['visible'] = $visible;
        }
        if ($no_sorting) {
            $this->no_sorting($column);
        }
        if ($no_filter) {
            $this->no_filter($column);
        }

        if ($onclick !== null) {
            $this->column_options[$column]['onclick'] = $onclick;
        }
    }


    /**
     * Define table configs.
     */
    protected function define_table_configs() {
    }

    /**
     * Prevent table_sql from calling download and xhr actions directly in constructor and possibly exiting the script there
     * Instead you have to call it manually with $table->out_actions();
     * @param bool $immediately_call_actions
     * @return void
     */
    protected function set_immediately_call_actions(bool $immediately_call_actions) {
        $this->immediately_call_actions = $immediately_call_actions;
    }

    protected function set_table_columns($cols) {
        $this->define_columns(array_keys($cols));
        $this->define_headers(array_values($cols));
    }

    protected function set_pagesize($pagesize) {
        $this->pagesize = $pagesize;
    }

    /**
     * should be protected, but parent is public
     */
    function set_sql($fields, $from, $where = '', array $params = array()) {
        $from = trim($from);
        if ($from[0] != '{') {
            if (preg_match('!\s!', $from)) {
                // propbably is a select query
            } else {
                $from = '{' . $from . '}';
            }
        }

        if (!$where) {
            $where = '1=1';
        }

        parent::set_sql($fields, $from, $where, $params);
    }

    protected function set_sql_query(string $query, array $params = []) {
        parent::set_sql('*', "({$query}) AS results", '1=1', $params);
    }

    /**
     * join an array [1 => 'name1', 2 => 'name2'] (which will be aggregated into a single column) into the main sql
     * @param array $values
     * @param string $join
     * @return array
     */
    protected function sql_aggregated_column_from_array(array $values, string $join, $param_type = SQL_PARAMS_QM) {
        global $DB;

        // allowed types
        if (!in_array($param_type, [SQL_PARAMS_NAMED, SQL_PARAMS_QM])) {
            throw new dml_exception('typenotimplement: ' . $param_type);
        }

        if ($param_type != SQL_PARAMS_QM) {
            throw new dml_exception('named parameters not implemented yet');
        }

        if (!$values) {
            return ["''", []];
        }

        $select = [];
        $params = [];
        foreach ($values as $id => $name) {
            $select[] = '(SELECT ' .
                // casting needed for postgres!
                $DB->sql_cast_char2int('?') . ' as id,
                    ? as name)' . "\n";
            $params[] = $id;
            $params[] = $name;
        }

        $select = "(SELECT " . $DB->sql_group_concat('aggregated_table.name') . "
            FROM (" . join(' UNION ', $select) . ") aggregated_table
            JOIN " . str_replace('?', 'aggregated_table.id', $join) . ')';

        return [$select, $params];
    }

    /**
     * join an sql statement (which will be aggregated into a single column) into the main sql
     * @param array $values
     * @param string $join
     * @return array
     */
    protected function sql_aggregated_column_from_query(string $select, string $from, string $sort = '') {
        global $DB;

        $select = "(SELECT " . $DB->sql_group_concat($select, ', ', $sort ?: $select) . "
            FROM " . $from . ")";

        return $select;
    }

    protected function sql_case(string $column, array $values, ?string $default = '') {
        global $DB;

        if ($default === null) {
            $default = 'NULL';
        } else {
            $default = "'" . addslashes(strip_tags($default)) . "'";
        }

        $sql = "CASE " .
            join("\n", array_map(function($key, $value) use ($column, $DB) {
                return "WHEN {$column}=" . (is_string($key) ? "'" . addslashes($key) . "'" : $key) . " THEN '" .
                    // doppelpunkte werfen einen sql fehler?!?
                    str_replace(':', '',
                        stripslashes(strip_tags($value))) . "'";
            }, array_keys($values), array_values($values))) .
            " ELSE {$default} END";

        return $sql;
    }

    protected function sql_format_timestamp(string $column, $format = '') {
        global $DB;

        if (!$format) {
            $format = $this->datetime_format;
        }

        if ($DB instanceof \pgsql_native_moodle_database) {
            // postgres:

            // convert to postgres format
            $substitutions = [
                '%Y' => 'YYYY',
                '%d' => 'DD',
                '%m' => 'MM',
                '%H' => 'HH24',
                '%M' => 'MI',
                '%s' => 'SS',
                '%w' => 'D', // TODO: postgres sunday is 1, mysql sunday is 0!
            ];
            $format = strtr($format, $substitutions);

            if (strpos($format, '%') !== false) {
                throw new dml_exception('date format contains unknown identifiers: ' . $format);
            }

            $timezone = str_replace(['"', '\''], '', get_user_timezone());
            return "TO_CHAR(TO_TIMESTAMP({$column}) AT TIME ZONE '{$timezone}', '{$format}')";
        } else {
            // mysql:

            $substitutions = [
                '%M' => '%i', // %M is used as Minute in php, but month in mysql?!?
            ];
            $format = strtr($format, $substitutions);

            static $mysql_timezone = null;
            if ($mysql_timezone === null) {
                $mysql_timezone = $DB->get_field_sql('SELECT @@global.time_zone');
            }

            // mysql:
            $timezone = str_replace(['"', '\''], '', get_user_timezone());

            // convert from mysql internal timezone to user timezone
            return "DATE_FORMAT(CONVERT_TZ(FROM_UNIXTIME({$column}), '{$mysql_timezone}', '{$timezone}'), '{$format}')";
        }
    }

    private function _uniqueid(string $prefix = 'unique') {
        static $uniqueid = 0;

        $uniqueid++;

        return $prefix . $uniqueid;
    }

    protected function no_filter($column) {
        $this->column_nofilter[] = $column;
    }

    protected function get_filter_where($param_type = SQL_PARAMS_QM) {
        global $DB;

        if (!$this->is_downloading() || !$this->get_selected_rowids()) {
            list($column_filter_where, $column_filter_params) = $this->get_column_filter_where($param_type);
            list($global_filter_where, $global_filter_params) = $this->get_global_filter_where($param_type);

            return ['(' . $column_filter_where . ' AND ' . $global_filter_where . ')', array_merge($column_filter_params, $global_filter_params)];
        } else {
            $column = $this->get_sql_column('id');

            list ($insql, $inparams) = $DB->get_in_or_equal($this->get_selected_rowids(), $param_type, 'id_filter_');
            return [$column . ' ' . $insql, $inparams];
        }
    }

    private function get_global_filter_where($param_type = SQL_PARAMS_QM) {
        global $DB;

        $search = optional_param('s', '', PARAM_TEXT);
        $search = trim($search);

        if (!$search) {
            return ['1=1', []];
        }

        $search_parts = preg_split('!\s+!', $search);

        $filter_where = [];
        $params = [];
        $get_param_name = function() use (&$params, $param_type) {
            if ($param_type === SQL_PARAMS_QM) {
                return '?';
            } else {
                return ':global_filter_' . count($params);
            }
        };
        $get_param_key = function() use (&$params, $param_type) {
            if ($param_type === SQL_PARAMS_QM) {
                return count($params);
            } else {
                return 'global_filter_' . count($params);
            }
        };

        // loop through each searched word (join them with AND)
        foreach ($search_parts as $search_part_i => $search_part) {
            // each word should be contained in at least one column (joined with OR)
            $filter_where_for_search_part = [];
            foreach ($this->columns as $column => $index) {
                if (in_array($column, $this->column_nofilter)) {
                    continue;
                }

                $sql_column = $this->get_sql_column($column);
                // timestamp columns need to formated as a string, so it can be searched (eg. 08.02.2024)
                if (($this->column_options[$column]['data_type'] ?? '') == static::PARAM_TIMESTAMP) {
                    $sql_column = $this->sql_format_timestamp($sql_column);
                }

                // column needs to be casted to string for comparison in postgres!
                $filter_where_for_search_part[] = $DB->sql_like($DB->sql_cast_to_char($sql_column), $get_param_name(), false, false);
                $params[$get_param_key()] = '%' . $DB->sql_like_escape($search_part) . '%';
            }

            foreach ($this->full_text_search_columns as $sql_column) {
                $filter_where_for_search_part[] = $DB->sql_like($DB->sql_cast_to_char($sql_column), $get_param_name(), false, false);
                $params[$get_param_key()] = '%' . $DB->sql_like_escape($search_part) . '%';
            }

            $filter_where[] = '(' . join(' OR ', $filter_where_for_search_part) . ')';
        }

        return ['(' . join(' AND ', $filter_where) . ')', $params];
    }

    private function get_column_filter_where($param_type = SQL_PARAMS_QM) {
        global $DB;

        if (!in_array($param_type, [SQL_PARAMS_NAMED, SQL_PARAMS_QM])) {
            throw new dml_exception('typenotimplement: ' . $param_type);
        }

        $filters = optional_param('filters', '', PARAM_RAW);

        if (!$filters) {
            return ['1=1', []];
        }
        $filters = json_decode($filters);
        if (!is_array($filters)) {
            return ['1=1', []];
        }

        $filter_where = [];
        $params = [];
        $get_param_name = function() use (&$params, $param_type) {
            if ($param_type === SQL_PARAMS_QM) {
                return '?';
            } else {
                return ':filter_' . count($params);
            }
        };
        $get_param_key = function() use (&$params, $param_type) {
            if ($param_type === SQL_PARAMS_QM) {
                return count($params);
            } else {
                return 'filter_' . count($params);
            }
        };

        foreach ($filters as $filter) {
            $column = $filter->id;

            if (!array_key_exists($column, $this->columns)) {
                continue;
            }

            if (in_array($column, $this->column_nofilter)) {
                continue;
            }

            $sql_column = $this->get_sql_column($column);

            if (($this->column_options[$column]['data_type'] ?? '') == static::PARAM_TIMESTAMP) {
                $sql_column = $this->sql_format_timestamp($sql_column);
            }

            if (empty($filter->fn) || $filter->fn == 'contains') {
                if ($filter->value) {
                    if (is_array($filter->value)) {
                        // list of possible values
                        list ($insql, $inparams) = $DB->get_in_or_equal($filter->value, $param_type, 'like_filter_' . str_replace('.', '', microtime(true)));
                        $filter_where[] = $sql_column . ' ' . $insql;
                        $params = array_merge($params, $inparams);
                    } else {
                        // text suche
                        $filter_where[] = $DB->sql_like($DB->sql_cast_to_char($sql_column), $get_param_name(), false, false);
                        $params[$get_param_key()] = '%' . preg_replace('!\s+!', '%', $DB->sql_like_escape($filter->value)) . '%';
                    }
                }
            } elseif ($filter->fn == 'equals') {
                // case insensitive compare:
                $filter_where[] = $DB->sql_like($DB->sql_cast_to_char($sql_column), $get_param_name(), false, false);
                $params[$get_param_key()] = $DB->sql_like_escape($filter->value);
            } elseif ($filter->fn == 'notEquals') {
                // case insensitive compare
                $filter_where[] = $DB->sql_like($DB->sql_cast_to_char($sql_column), $get_param_name(), false, false, true);
                $params[$get_param_key()] = $DB->sql_like_escape($filter->value);
            } elseif ($filter->fn == 'startsWith') {
                // case insensitive compare
                $filter_where[] = $DB->sql_like($sql_column, $get_param_name(), false, false);
                $params[$get_param_key()] = $DB->sql_like_escape($filter->value) . '%';
            } elseif ($filter->fn == 'endsWith') {
                $filter_where[] = $DB->sql_like($sql_column, $get_param_name(), false, false);
                $params[$get_param_key()] = '%' . $DB->sql_like_escape($filter->value);
            } elseif ($filter->fn == 'greaterThan' && strlen($filter->value) > 0) {
                $filter_where[] = $sql_column . " > " . $get_param_name();
                $params[$get_param_key()] = (float)$filter->value;
            } elseif ($filter->fn == 'greaterThanOrEqualTo' && strlen($filter->value) > 0) {
                $filter_where[] = $sql_column . " >= " . $get_param_name();
                $params[$get_param_key()] = (float)$filter->value;
            } elseif ($filter->fn == 'lessThan' && strlen($filter->value) > 0) {
                $filter_where[] = $sql_column . " < " . $get_param_name();
                $params[$get_param_key()] = (float)$filter->value;
            } elseif ($filter->fn == 'lessThanOrEqualTo' && strlen($filter->value) > 0) {
                $filter_where[] = $sql_column . " <= " . $get_param_name();
                $params[$get_param_key()] = (float)$filter->value;
            } elseif ($filter->fn == 'empty') {
                $filter_where[] = "COALESCE($sql_column, '') = ''";
            } elseif ($filter->fn == 'notEmpty') {
                $filter_where[] = "COALESCE($sql_column, '') <> ''";
            } elseif ($filter->fn == 'between') {
                if (strlen($filter->value[0] ?: '') > 0) {
                    $filter_where[] = $sql_column . " >= " . $get_param_name();
                    $params[$get_param_key()] = (float)$filter->value[0];
                }
                if (strlen($filter->value[1] ?: '') > 0) {
                    $filter_where[] = $sql_column . " <= " . $get_param_name();
                    $params[$get_param_key()] = (float)$filter->value[1];
                }
            } else {
                var_dump($filter);
                die('filter function not understood: ' . $filter->fn);
            }
        }

        if (!$filter_where) {
            return ['1=1', []];
        }

        return ['(' . join(' AND ', $filter_where) . ')', $params];
    }

    private function get_sql_column($column) {
        if (!empty($this->column_options[$column]['sql_column'])) {
            return $this->column_options[$column]['sql_column'];
        }

        // get sql_column from query
        if (preg_match('!(^|[\s,()])(?<column>[^.\s,()]+\.[^.\s,]+)\s+as\s+?' . preg_quote($column, '!') . '([,\s]|$)!i', $this->sql->fields ?? '', $matches)) {
            // matches u.some_col AS username
            // matches u.some_col username
            $sql_column = $matches['column'];
        } elseif (preg_match('!(^|[\s,])(?<column>[^.\s,()]+\.' . preg_quote($column, '!') . ')([,\s]|$)!i', $this->sql->fields ?? '', $matches)) {
            // matches u.username
            $sql_column = $matches['column'];
        } else {
            // dont't allow brackets () in table name, because sql->from could be a select statement!
            if (preg_match('!^[^\s,()]+\s+(as\s+)?(?<table>[^\s,()]+)!i', $this->sql->from ?? '', $matches)) {
                // matches long_table AS short_table
                // matches long_table short_table
                $sql_column = $matches['table'] . '.' . $column;
            } else {
                $sql_column = $column;
            }
        }

        return $sql_column;
    }


    protected function add_row_action(string|\moodle_url $url = '', string $type = 'other', string $label = '', string $id = '', bool $disabled = false, string $icon = '', string $onclick = '') {
        if (!$url) {
            if ($type == 'edit') {
                $url = new \moodle_url($this->baseurl, ['action' => 'edit', 'id' => '{id}']);
            } elseif ($type == 'delete') {
                $url = new \moodle_url($this->baseurl, ['action' => 'delete', 'id' => '{id}']);
            }
        }

        if (!$label) {
            if ($type == 'edit') {
                $label = get_string('edit');
            } elseif ($type == 'delete') {
                $label = get_string('delete');
            }
        }

        $this->row_actions[] = (object)[
            'url' => $url,
            'type' => $type,
            'label' => $label,
            // dies erlaubt auch numerische ids, welche zu string convertiert werden
            // und erlaubt auch eine id '0'
            'id' => $id === '' ? 'action-' . (count($this->row_actions) + 1) : $id,
            'disabled' => $disabled,
            'icon' => $icon,
            'onclick' => trim($onclick),
        ];
    }

    protected function set_row_actions_display_as_menu(bool $as_menu) {
        $this->row_actions_display_as_menu = $as_menu;
    }

    protected function set_row_actions_js_callback(string $row_actions_js_callback) {
        $this->row_actions_js_callback = $row_actions_js_callback;
    }

    protected function set_render_detail_panel_js_callback(string $render_detail_panel_js_callback) {
        $this->enable_detail_panel = true;
        $this->render_detail_panel_js_callback = $render_detail_panel_js_callback;
    }

    protected function enable_detail_panel(bool $enabled = true) {
        $this->enable_detail_panel = $enabled;
    }

    protected function enable_row_selection(bool $enabled = true) {
        $this->enable_row_selection = $enabled;
    }

    protected function enable_global_filter(bool $enabled = true) {
        $this->enable_global_filter = $enabled;
    }

    protected function enable_column_filters(bool $enabled = true) {
        $this->enable_column_filters = $enabled;
    }

    protected function enable_page_size_selector(bool $enabled = true) {
        $this->enable_page_size_selector = $enabled;
    }

    protected function set_initial_page_index(int $page) {
        $this->initial_page_index = $page;
    }

    protected function add_full_text_search_column(string $column) {
        $this->full_text_search_columns[] = $column;
    }

    protected function get_row_actions(object $row, array $row_actions): ?array {
        return null;
    }

    /**
     * Query the db. Store results in the table object for use by build_table.
     *
     * @param int $pagesize size of page for paginated displayed table.
     * @param bool $useinitialsbar do you want to use the initials bar. Bar
     * will only be used if there is a fullname column defined for the table.
     * @throws dml_exception
     */
    public function query_db($pagesize, $useinitialsbar = true) {
        global $DB;

        if (!$this->is_downloading()) {
            list($countsql, $countparams) = $this->get_sql_and_params(true);
            list($sql, $params) = $this->get_sql_and_params();
            $total = $DB->count_records_sql($countsql, $countparams);
            $this->pagesize($pagesize, $total);
            $this->rawdata = $DB->get_records_sql($sql, $params, $this->get_page_start(), $this->get_page_size());

            // Set initial bars.
            if ($useinitialsbar) {
                $this->initialbars($total > $pagesize);
            }
        } else {
            // when downloading, just get the raw data
            list($sql, $params) = $this->get_sql_and_params();
            $this->rawdata = $DB->get_records_sql($sql, $params);
        }
    }

    /**
     * Builds the SQL query.
     *
     * @param bool $count When true, return the count SQL.
     * @return array containing sql to use and an array of params.
     */
    protected function get_sql_and_params($countOnly = false) {
        if ($countOnly) {
            $select = "COUNT(1)";
        } else {
            $select = @$this->sql->fields ?: "*";
        }

        [$sql, $params] = $this->get_sql_and_params_simple($select);

        [$filter_where, $filter_params] = $this->get_filter_where($this->get_sql_param_type_from_param_array($params));
        $params = array_merge($params, $filter_params);
        $sql .= ' AND ' . $filter_where;

        // Add order by if needed.
        $order_by = $this->get_order_by($countOnly);
        $sql .= $order_by;

        return [$sql, $params];
    }

    /**
     * @param string $select
     * @param string $order_by
     * @return array
     */
    protected function get_sql_and_params_simple(string $select): array {
        if (!$this->sql) {
            throw new moodle_exception('get_sql_and_params_simple_not_implemented and $this->sql not set');
        }

        $where = $this->sql->where;
        $params = $this->sql->params;

        $sql = "
            SELECT {$select}
            FROM {$this->sql->from}
            WHERE {$where}
        ";

        return [$sql, $params];
    }

    protected function get_order_by($countOnly) {
        if (!$countOnly && $sqlsort = $this->get_sql_sort()) {
            return " ORDER BY " . $sqlsort;
        } else {
            return '';
        }
    }

    private function get_sql_param_type_from_param_array($params) {
        return $params && !isset($params[0]) ? SQL_PARAMS_NAMED : SQL_PARAMS_QM;
    }

    /**
     * Override parent behavior
     */
    public function get_sort_columns() {
        $columns = parent::get_sort_columns();

        // override the default behavior to use the configured sql_column attribute
        $ret = [];
        foreach ($columns as $column => $sort) {
            $ret[$this->get_sql_column($column)] = $sort;
        }

        return $ret;
    }

    /**
     * Override parent behavior
     * Avoid the output of the reset button.
     */
    protected function can_be_reset() {
        return false;
    }

    /**
     * Override parent behavior
     */
    public function print_nothing_to_display() {
        if ($this->is_xhr) {
            return;
        } else {
            parent::print_nothing_to_display();
        }
    }

    protected function format_col_content(string $content, string|\moodle_url $link = null, string $target = '') {
        if ($link) {
            if ($this->is_xhr) {
                return [
                    'content' => $content,
                    'link' => $link instanceof \moodle_url ? $link->out(false) : (string)$link,
                    'target' => $target,
                ];
            } else {
                return '<a href="' . s($link) . '">' . s($content) . '</a>';
            }
        } else {
            return $content;
        }
    }

    public function col_userid($row) {
        if (!$row->userid) {
            return '';
        }

        $user = core_user::get_user($row->userid);

        if ($user) {
            return $this->format_col_content(fullname($user), new moodle_url('/user/profile.php', array('id' => $user->id)));
        } else {
            return get_string('unknownuser');
        }
    }

    public function other_cols($column, $row) {
        if (($this->column_options[$column]['data_type'] ?? '') == static::PARAM_TIMESTAMP) {
            return $this->format_timestamp($row->{$column});
        }

        if (preg_match('!^time!', $column) && ctype_digit((string)$row->{$column})) {
            return $this->format_timestamp($row->{$column});
        }

        if (empty($this->column_options[$column]['internal']) && (!$this->is_downloading() || $this->export_class_instance()->supports_html())) {
            // escape content if not downloading
            return s($row->{$column});
        } else {
            return $row->{$column};
        }
    }

    protected function format_timestamp($timestamp) {
        return userdate($timestamp, $this->datetime_format, 99, false);
    }

    public function out_actions() {
        if (!$this->immediately_call_actions) {
            return;
        }

        if ($this->is_downloading()) {
            if (!$this->is_downloadable()) {
                // when is_downloadable was not set in the configuration
                // then prevent downloading
                throw new \moodle_exception('downloading not allowed');
            }

            // set the correct filename
            $name = $this->download_name ?: str_replace('.php', '', basename($_SERVER['PHP_SELF']));
            $this->is_downloading($this->download, $name, $name);

            $this->out();
            exit;
        }

        if ($this->is_xhr) {
            $this->handle_xhr();
        }
    }

    public function is_downloadable($downloadable = null, $download_name = '') {
        if ($download_name) {
            $this->download_name = $download_name;
        }

        return parent::is_downloadable($downloadable);
    }

    protected function htmluniqueid() {
        return 'table-sql-' . preg_replace('![^a-z0-9\-]!i', '', $this->uniqueid);
    }

    /**
     * Override parent behavior
     */
    public function out($pagesize = null, $useinitialsbar = null, $downloadhelpbutton = '') {
        if ($this->is_downloading()) {
            return $this->_out();
        }

        if ($pagesize !== null) {
            debugging('Use of pagesize in $table->out($pagesize) is currently not supported in class local_table_sql/table_sql', DEBUG_DEVELOPER);
        }

        global $PAGE;

        if ($PAGE->requires) {
            // For Typo3 Integration: in typo3 $PAGE->requires is not available

            // load all plugin strings for javascript app
            $sm = get_string_manager();
            $strings = $sm->load_component_strings('local_table_sql', 'en');
            $PAGE->requires->strings_for_js(array_keys($strings), 'local_table_sql');

            $PAGE->requires->js('/local/table_sql/js/main.js');
            $PAGE->requires->js_init_code("start_table_sql(" . json_encode(array_merge([
                    '__info' => is_siteadmin() ? 'Pretty print is only for admin!' : '',
                    'container' => '#' . $this->htmluniqueid(),
                ], (array)$this->get_config()
                // TODO later: add pagesize as parameter for the app?
                ), is_siteadmin() ? JSON_PRETTY_PRINT : 0) . ")");
        }

        echo '<div id="' . $this->htmluniqueid() . '">';
        if (in_array(TABLE_P_TOP, $this->showdownloadbuttonsat)) {
            echo $this->download_buttons();
        }

        $this->wrap_html_start();

        echo '<div class="table-sql-container"></div>';

        $this->wrap_html_finish();

        if (in_array(TABLE_P_BOTTOM, $this->showdownloadbuttonsat)) {
            echo $this->download_buttons();
        }

        echo '</div>';
    }

    private function _out() {
        parent::out($this->pagesize, null, '');
    }

    /**
     * Add a row of data
     * This is called during out() when printing the rows
     * @param array $row a numerically keyed row of data to add to the table.
     * @param string $classname CSS class name to add to this row's tr tag.
     * @return bool success.
     */
    public function add_data_keyed($row, $classname = '') {
        if ($this->is_xhr) {
            $this->xhr_formatted_data[] = $row;
            return true;
        } else {
            return parent::add_data_keyed($row, $classname);
        }
    }

    protected function get_config() {
        $headers = $this->headers;
        $columns = [];
        foreach ($this->columns as $column => $num) {
            $data_type = $this->get_column_data_type($column);

            $columns[] = $columnConfig = (object)[
                'key' => $column,
                'class' => trim($this->column_class[$column]),
                'style' => (object)$this->column_style[$column],
                'header' => $headers[$num],
                'data_type' => $data_type,
                'sorting' => $this->is_sortable && !in_array($column, $this->column_nosort) && !in_array($column, ['edit', 'delete']),
                'filter' => !in_array($column, $this->column_nofilter) && !in_array($column, ['edit', 'delete']),
                'internal' => $this->column_options[$column]['internal'] ?? false,
                'visible' => $this->column_options[$column]['visible'] ?? true,
                'onclick' => $this->column_options[$column]['onclick'] ?? null,
            ];

            if (!empty($this->column_options[$column]['select_options'])) {
                $columnConfig->filterVariant = 'multi-select';

                $parse_select_options = function($select_options) {
                    $result = [];
                    foreach ($select_options as $key => $value) {
                        if (is_scalar($value)) {
                            $result[] = ['text' => $value, 'value' => $key];
                        } else {
                            $result[] = $value;
                        }
                    }
                    return $result;
                };

                $columnConfig->filterSelectOptions = $parse_select_options($this->column_options[$column]['select_options']);
                $columnConfig->columnFilterModeOptions = [];
            }
        }

        $row_actions_display_as_menu = $this->row_actions_display_as_menu;
        if ($row_actions_display_as_menu === null) {
            if (count($this->row_actions) > 5) {
                $row_actions_display_as_menu = true;
            } else {
                // wenn nicht definiert ist ob die action buttons als Buttons oder Dropdownmenü angezeigt werden
                // dann die Länge aller Labels (exkl. edit und delte button, weil die als Button angezeigt werden) berechnen
                $row_action_labels = join('', array_map(function($action) {
                    if ($action->type == 'edit' || $action->type == 'delete' || $action->icon) {
                        return '';
                    } else {
                        return $action->label;
                    }
                }, $this->row_actions));
                $row_actions_display_as_menu = strlen($row_action_labels) >= 22;
            }
        }
        $row_actions = array_map(function($action) {
            if ($action->url instanceof \moodle_url) {
                $action->url = $action->url->out(false);
                // replace encoded placeholders '{id}'
                $action->url = preg_replace('!%7B([a-z0-9_]+)%7D!i', '{$1}', $action->url);
            }
            return $action;
        }, $this->row_actions);

        // das entsprechende setting verwenden, oder per default prüfen, ob es filterbare columns gibt
        $enable_column_filters = $this->enable_column_filters ?? !!array_filter($columns, function($column) {
            return $column->filter;
        });

        return (object)[
            'htmluniqueid' => $this->htmluniqueid(),
            'uniqueid' => $this->uniqueid,
            'current_language' => current_language(),
            'pagesize' => $this->pagesize,
            'initial_page_index' => $this->initial_page_index,
            'enable_row_selection' => $this->enable_row_selection,
            'is_sortable' => $this->is_sortable,
            'sort_default_column' => $this->sort_default_column,
            'sort_default_order' => $this->sort_default_order == SORT_DESC ? 'desc' : 'asc',
            'columns' => $columns,
            'row_actions' => $row_actions,
            'row_actions_display_as_menu' => $row_actions_display_as_menu,
            'enable_global_filter' => ($this->enable_global_filter ?? $this->is_sortable),
            'enable_column_filters' => $enable_column_filters,
            'enable_page_size_selector' => $this->enable_page_size_selector,
            'row_actions_js_callback' => trim($this->row_actions_js_callback) ?: null,
            'enable_detail_panel' => $this->enable_detail_panel,
            'render_detail_panel_js_callback' => trim($this->render_detail_panel_js_callback) ?: null,
        ];
    }

    private function get_column_data_type($column) {
        global $DB;

        if (!empty($this->column_options[$column]['data_type'])) {
            return $this->column_options[$column]['data_type'];
        }

        $sql_column = $this->get_sql_column($column);

        $sql_column = explode('.', $sql_column);
        if (count($sql_column) >= 2) {
            // dont't allow brackets () in table name, because sql->from could be a select statement!
            if ($this->sql && preg_match('!(?<table>[^\s,()]+)\s+' . preg_quote($sql_column[0], '!') . '(\s|$)!', $this->sql->from, $matches)) {
                $table = trim($matches['table'], '{}');
            } else {
                $table = $sql_column[0];
            }
            $column = $sql_column[1];
            // dont't allow brackets () in table name, because sql->from could be a select statement!
        } elseif (!empty($this->sql->from) && preg_match('!^([^\s()]+)!i', $this->sql->from, $matches)) {
            // extract "localeduportal_app_log" from "{local_eduportal_app_log} log JOIN {user} u ON log.userid=u.id"
            $table = $matches[1];
            $column = $sql_column[0];
        } else {
            $table = '';
        }

        if ($table) {
            $columns = $DB->get_columns($table);
        } else {
            $columns = [];
        }

        if (!$columns) {
            // table was not found
            return static::PARAM_UNKNOWN;
        }
        if (empty($columns[$column])) {
            // column was not found
            return static::PARAM_UNKNOWN;
        }

        $type = $columns[$column]->type;
        if ($type == 'int' || $type == 'bigint' || $type == 'tinyint') {
            if (preg_match('!^time!', $column)) {
                return 'timestamp';
            } else {
                return 'number';
            }
        } elseif ($type == 'text' || $type == 'longtext' || $type == 'varchar') {
            return 'string';
        } else {
            throw new \moodle_exception('unknown db column type: ' . $type);
        }
    }

    private function handle_xhr(): void {
        global $DB, $PAGE;

        header('Cache-Control: private, must-revalidate, pre-check=0, post-check=0, max-age=0');
        header('Pragma: no-cache');
        header('Expires: ' . gmdate('D, d M Y H:i:s', 0) . ' GMT');
        header("Content-Type: application/json\n");

        $json_output = function($data): void {
            echo json_encode($data, JSON_UNESCAPED_SLASHES
                | (optional_param('pretty', false, PARAM_BOOL) || (is_array($data) && !empty($data['type']) && $data['type'] == 'error') ? JSON_PRETTY_PRINT : 0));
            exit;
        };

        try {
            $table_sql_action = optional_param('table_sql_action', '', PARAM_TEXT);

            // only needed in dev!
            if ($table_sql_action == 'get_config') {
                $json_output([
                    'type' => 'success',
                    'data' => $this->get_config(),
                ]);
                return;
            }

            if ($table_sql_action == 'set_selected') {
                $row_ids_selected = array_filter(explode(',', required_param('row_ids_selected', PARAM_TEXT)));
                $row_ids_unselected = array_filter(explode(',', required_param('row_ids_unselected', PARAM_TEXT)));

                $session_info = $this->get_session_info();

                // add new selections
                $selected_rowids = $session_info->selected_rowids ?: [];
                foreach ($row_ids_selected as $id) {
                    $selected_rowids[] = $id;
                }

                // remove newly unselected items
                $selected_rowids = array_filter($selected_rowids, function($id) use ($row_ids_unselected) {
                    return !in_array($id, $row_ids_unselected);
                });

                // remove duplicates, if added twice
                $selected_rowids = array_unique($selected_rowids);
                $selected_rowids = array_values($selected_rowids);

                $session_info = $this->get_session_info();
                $session_info->selected_rowids = $selected_rowids;
                $session_info->selection_last_changed = time();

                $json_output([
                    'meta' => [
                        'selected_rows_count' => count($selected_rowids),
                    ],
                ]);
                return;
            }

            if ($table_sql_action == 'select_all') {
                $this->setup();

                $this->currpage = 0;
                $this->pagesize = 999999;

                $this->query_db(999999, false);

                $session_info = $this->get_session_info();

                $selected_rowids = $session_info->selected_rowids;
                foreach ($this->rawdata as $row) {
                    $selected_rowids[] = $row->id;
                }

                // remove duplicates, if added twice
                $selected_rowids = array_unique($selected_rowids);
                $selected_rowids = array_values($selected_rowids);

                $session_info->selected_rowids = $selected_rowids;
                $session_info->selection_last_changed = time();

                $json_output([
                    'meta' => [
                        'selected_rows_count' => count($selected_rowids),
                    ],
                ]);
                return;
            }

            if ($table_sql_action == 'select_none') {
                $session_info = $this->get_session_info();
                $session_info->selected_rowids = [];
                $session_info->selection_last_changed = time();

                $json_output(true);
                return;
            }

            $this->pagesize = optional_param('page_size', 0, PARAM_INT) ?: $this->pagesize;

            $this->_out();
            $rows = $this->xhr_formatted_data;

            $session_info = $this->get_session_info();
            $selected_rowids = $session_info->selected_rowids;

            // $this->rawdata is indexed by id
            $rawdata = array_values($this->rawdata);

            // fix rows
            foreach ($rows as $row_i => &$row) {
                $originalRow = $rawdata[$row_i];

                foreach ($row as &$col_value) {
                    // if value is a link, parse it for React Table
                    if (is_string($col_value) && preg_match('!^<a\s[^>]*href=("(?<link1>[^"]*)"|\'(?<link2>[^\']*)\')[^>]*>(?<content>[^<]*)</a>$!i', trim($col_value), $matches)) {
                        if (preg_match('!^\s*<a\s[^>]*target=("(?<target1>[^"]*)"|\'(?<target2>[^\']*)\')!i', trim($col_value), $matches_target)) {
                            $target = $matches_target['target1'] ?? $matches_target['target2'] ?? '';
                        } else {
                            $target = '';
                        }

                        $col_value = [
                            'link' => html_entity_decode($matches['link1'] ?? $matches['link2'] ?? ''),
                            'content' => $matches['content'],
                            'target' => $target,
                        ];
                    }
                }

                if (!isset($this->columns['id']) && !empty($originalRow->id)) {
                    $row['id'] = $originalRow->id;
                }

                // add _data column
                $row['_data'] = (object)[];
                if ($this->enable_row_selection) {
                    $row['_data']->selected = isset($row['id']) && in_array($row['id'], $selected_rowids);
                }

                if ($this->enable_detail_panel) {
                    $row['_data']->detail_panel_content = $this->render_detail_panel_content($originalRow);
                }

                // clone everything, so the original objects don't get modified!
                $row_actions = unserialize(serialize($this->row_actions));

                $row_actions = $this->get_row_actions($originalRow, $row_actions);

                if ($row_actions !== null) {
                    // remove all attributes, which are the same
                    foreach ($row_actions as $row_action) {
                        if (empty($row_action->id)) {
                            continue;
                        }

                        $base_row_action = current(array_filter($this->row_actions, function($base_row_action) use ($row_action) {
                            return $base_row_action->id == $row_action->id;
                        }));

                        if (!$base_row_action) {
                            continue;
                        }

                        foreach (get_object_vars($row_action) as $name => $value) {
                            if ($name == 'id') {
                                continue;
                            }

                            // Check if the attribute exists in both objects and has the same value
                            if (property_exists($base_row_action, $name) && (
                                    ($row_action->$name === $base_row_action->$name) ||
                                    // for url compare the moodle_url to string and compare
                                    ($name == 'url' && (string)$row_action->$name === (string)$base_row_action->$name))
                            ) {
                                unset($row_action->$name); // Remove the attribute from the first object
                            }
                        }
                    }

                    $row_actions = array_map(function($action) {
                        if (!empty($action->url) && $action->url instanceof \moodle_url) {
                            $action->url = $action->url->out(false);
                            // replace encoded placeholders '{id}'
                            $action->url = preg_replace('!%7B([a-z0-9_]+)%7D!i', '{$1}', $action->url);
                        }

                        if (!empty($action->onclick)) {
                            $action->onclick = trim($action->onclick);
                        }

                        return $action;
                    }, $row_actions);

                    $row['_data']->row_actions = $row_actions;
                }
            }

            $json_output([
                'type' => 'success',
                'meta' => [
                    'total' => $this->totalrows,
                    'page_size' => $this->pagesize,
                    'current_page' => $this->currpage,
                    'selected_rows_count' => count($selected_rowids),
                ],
                'data' => $rows,
                // 'rawdata' => $this->rawdata,
            ]);
        } catch (\Exception $e) {
            if (has_capability('moodle/site:config', \context_system::instance())) {
                // more debug output for admin
                $json_output([
                    'type' => 'error',
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'exception' => $e,
                ]);
            } else {
                $json_output([
                    'type' => 'error',
                    'error' => 'Leider ist ein Fehler beim Laden der Daten aufgetreten!',
                    'exception' => get_class($e),
                ]);
            }
        }
    }

    private function get_session_info() {
        if (empty($_SESSION['local_table_sql_info'])) {
            $_SESSION['local_table_sql_info'] = [];
        }
        if (empty($_SESSION['local_table_sql_info'][$this->uniqueid]) || !is_object($_SESSION['local_table_sql_info'][$this->uniqueid])) {
            $_SESSION['local_table_sql_info'][$this->uniqueid] = (object)[
                'selected_rowids' => [],
                'selection_last_changed' => 0,
            ];
        }

        return $_SESSION['local_table_sql_info'][$this->uniqueid];
    }

    public function clear_selection() {
        $session_info = $this->get_session_info();
        $session_info->selected_rowids = [];
        $session_info->selection_last_changed = time();
    }

    public function get_selected_rowids() {
        $session_info = $this->get_session_info();

        return $session_info->selected_rowids;
    }

    protected function render_detail_panel_content(object $row) {
        return null;
    }

    /**
     * Override parent behavior
     * This method should be protected
     */
    public function set_default_per_page(int $defaultperpage): void {
        parent::set_default_per_page($defaultperpage);
        $this->pagesize = $defaultperpage;
    }

    /**
     * Override parent behavior
     * This method should be protected
     */
    public function download_buttons() {
        // uniqueid bei baseurl dazugeben, damit der download nur bei dieser tabelle startet (falls mehrere Tabellen auf der Seite wären)
        $orig_baseurl = $this->baseurl;
        $this->baseurl = new \moodle_url($orig_baseurl, ['uniqueid' => $this->uniqueid]);

        $output = '<div class="table-sql-download-form-container">';
        $output .= parent::download_buttons();
        $output .= '</div>';

        // baseurl zurücksetzen
        $this->baseurl = $orig_baseurl;

        return $output;
    }
}
