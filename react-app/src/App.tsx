// beispiele:
// https://www.material-react-table.dev/?path=/story/features-search-examples--search-enabled-default

import React, { useContext, useEffect, useMemo, useRef, useState } from 'react';
import {
  MaterialReactTable,
  MRT_RowSelectionState,
  type MRT_ColumnDef,
  type MRT_ColumnFiltersState,
  type MRT_ColumnFilterFnsState,
  type MRT_PaginationState,
  type MRT_SortingState,
  MRT_TopToolbar,
  MRT_VisibilityState,
  useMaterialReactTable,
  MRT_Row,
  MRT_Icons,
} from 'material-react-table';
import { MenuItem, Box, IconButton, Button, Alert, Collapse, Paper, CircularProgress, Stack } from '@mui/material';
import { Edit as EditIcon, Delete as DeleteIcon } from '@mui/icons-material';
import { useQuery } from '@tanstack/react-query';
import './App.scss';
import { useAppConfig, useFetchWithParams } from 'lib/hooks';
import { convertSnakeCaseToCamelCase, debounce, highlightNodes, replacePlaceholders, sleep, stringToFunction } from 'lib/helpers';
import { useLocalization } from 'lib/localization';
import dayjs from 'dayjs';
import SearchIcon from '@mui/icons-material/Search';
import SearchOffIcon from '@mui/icons-material/SearchOff';
import FullscreenIcon from '@mui/icons-material/Fullscreen';
import FullscreenExitIcon from '@mui/icons-material/FullscreenExit';
import FilterListIcon from '@mui/icons-material/FilterList';
import FilterListOffIcon from '@mui/icons-material/FilterListOff';
import ViewColumnIcon from '@mui/icons-material/ViewColumn';

function deepClone(obj) {
  return JSON.parse(JSON.stringify(obj));
}

function stripTags(input) {
  if (typeof input !== 'string') {
    return input;
  }

  // Remove HTML tags using a regular expression
  return input.replace(/<[^>]*>/g, '');
}

// chatGPT
function hasScrollbar(div) {
  return div.scrollWidth > div.clientWidth;
}

function HtmlHighlight({ content, highlight }) {
  const divRef = useRef(null as any);

  useEffect(() => {
    if (divRef.current) {
      // Set the initial HTML
      divRef.current.innerHTML = content;

      if (highlight) {
        highlightNodes(divRef.current, highlight);
      }

      // Manipulate the DOM here (e.g., add a class to all paragraphs)
      // const paragraphs = divRef.current.querySelectorAll('p');
      // paragraphs.forEach(p => p.classList.add('custom-class'));
    }
  }, [content, highlight]);

  return (
    <span
      ref={divRef}
      // contentEditable
      // className="editable-html"
      // suppressContentEditableWarning={true}
    />
  );
}

function useShowStickyColumns(tableConfig) {
  const [sticky, setSticky] = useState(false);

  const check = useMemo(
    () =>
      debounce(() => {
        // Function to check if the div has a horizontal scrollbar
        const div = tableConfig.containerElement.querySelector('.MuiTableContainer-root');
        if (div) {
          const sticky = div.scrollWidth > div.clientWidth;
          setSticky(sticky);
        }
      }, 250),
    []
  );

  useEffect(() => {
    function handleResize() {
      // check scrollbar when resizing the window
      check();
    }

    window.addEventListener('resize', handleResize);
    handleResize();
    return () => window.removeEventListener('resize', handleResize);
  }, []);

  // check scrollbar on each render
  check();

  return sticky;
}

function useColumnDef({ getColumn, tableConfig }) {
  return useMemo<MRT_ColumnDef<TableRow>[]>(() => {
    return (
      tableConfig.columns
        .filter((column) => column.key != 'edit' && column.key != 'delete')
        // column "id" is hidden for example
        .filter((column) => !column.internal)
        .map((column) => {
          const configColumn = column;

          return {
            id: column.key,
            header: column.header
              // der text wird auch im Suchfeld angezeigt, dort ist kein Newline möglich
              .replace(/\s*\n\s*/g, ' '),
            accessorFn: (originalRow) => {
              // this allows a cell to have a object {"content": "..."} as value
              if (typeof originalRow[column.key] == 'object') {
                return originalRow[column.key]?.content;
              } else {
                return originalRow[column.key];
              }
            },
            // accessorKey: column.key, // String(i),

            // allow newlines in headers
            Header: column.header.split('\n').map((line, key) => <div key={key}>{line}</div>),

            Cell: ({ renderedCellValue, row, column, table }) => {
              let content;

              if (row.original[column.id] === '' || row.original[column.id] === null) {
                // original content is empty => no content
                return null;
              }
              if (typeof row.original[column.id] == 'string') {
                const tableState = table.getState();
                const columnSearch = column.getFilterValue() || tableState.globalFilter;

                if (configColumn.filter && columnSearch && typeof columnSearch == 'string') {
                  // custom highlight algorithm
                  content = <HtmlHighlight content={row.original[column.id]} highlight={columnSearch} />;
                } else {
                  content = <span dangerouslySetInnerHTML={{ __html: row.original[column.id] }} />;
                }
              } else {
                content = typeof renderedCellValue == 'object' ? renderedCellValue : <span dangerouslySetInnerHTML={{ __html: String(renderedCellValue) }} />;
              }

              let asLink = false;
              let href = '';
              let target = '';
              if (typeof row.original[column.id] == 'object' && row.original[column.id]?.link) {
                asLink = true;
                href = row.original[column.id]?.link;
                target = row.original[column.id]?.target;
              }
              if (getColumn(column.id)?.onclick) {
                asLink = true;
              }
              if (asLink) {
                content = (
                  <a
                    href={href || '#'}
                    target={target}
                    className="as-link"
                    onClick={(e) => {
                      // prevent selection
                      e.stopPropagation();

                      if (!href) {
                        // prevent going to '#'
                        e.preventDefault();
                      }

                      // call js callback if available
                      stringToFunction(getColumn(column.id)?.onclick)?.({ row: row.original });
                    }}
                  >
                    {content}
                  </a>
                );
              }

              return content;

              // let url = row.original[column.id + '_table_sql_link'];
              // if (url) {
              //   return <a href={url}>{content}</a>;
              // } else {
              //   return content;
              // }
            },
            enableSorting: column.sorting !== false,
            enableColumnFilter: column.filter !== false,
            minSize: 10,
            columnFilterModeOptions:
              column.mrtOptions.filterVariant == 'multi-select'
                ? ['equals']
                : column.mrtOptions.filterVariant == 'range-slider'
                ? ['between']
                : column.data_type == 'timestamp'
                ? ['between']
                : column.data_type == 'number'
                ? [
                    // 'fuzzy',
                    // 'contains',
                    // 'startsWith',
                    // 'endsWith',
                    'equals',
                    'notEquals',
                    'between',
                    // 'betweenInclusive',
                    'greaterThan',
                    'greaterThanOrEqualTo',
                    'lessThan',
                    'lessThanOrEqualTo',
                    // 'empty',
                    // 'notEmpty',

                    // 'includesString',
                    // 'includesStringSensitive',
                    // 'equalsString',
                    // 'equalsStringSensitive',
                    // 'arrIncludes',
                    // 'arrIncludesAll',
                    // 'arrIncludesSome',
                    // 'weakEquals',
                    // 'inNumberRange',
                  ]
                : [
                    // 'fuzzy',
                    'contains',
                    'startsWith',
                    'endsWith',
                    'equals',
                    'notEquals',
                    // 'between',
                    // 'betweenInclusive',
                    // 'greaterThan',
                    // 'greaterThanOrEqualTo',
                    // 'lessThan',
                    // 'lessThanOrEqualTo',
                    'empty',
                    'notEmpty',

                    // 'includesString',
                    // 'includesStringSensitive',
                    // 'equalsString',
                    // 'equalsStringSensitive',
                    // 'arrIncludes',
                    // 'arrIncludesAll',
                    // 'arrIncludesSome',
                    // 'weakEquals',
                    // 'inNumberRange',
                  ],

            filterVariant: column.data_type == 'timestamp' ? 'date-range' : 'text',
            // disable filtermodes for timestamp (date-range selector), because it only shows 'between' and 'betweenIncludes' options
            enableColumnFilterModes: column.data_type != 'timestamp',
            // filterVariant: 'multi-select',
            // filterSelectOptions: [
            //   { label: 'Male', value: 'Male' },
            //   { label: 'Female', value: 'Female' },
            //   { label: 'Other', value: 'Other' },
            // ],
            // geht nicht:
            // filterComponent: (props) => <div>Filte r123</div>

            ...column.mrtOptions,
          } as MRT_ColumnDef<TableRow>;
        })
    );

    // test column.mrtOptions (kommt von PHP), ob die props korrekt sind (ggf. haben sie sich bei einer neuen Version von material-react-table geändert)
    if (process.env.NODE_ENV == 'development') {
      const tmp = {
        filterVariant: 'multi-select',
        filterSelectOptions: [{ label: 'label', value: 1 }],
      } as MRT_ColumnDef<TableRow>;
    }
  }, [tableConfig]);
}

interface RowAction {
  id: string;
  disabled?: boolean;
}

interface Data {
  selected: boolean;
  detail_panel_content: string;
  row_actions: RowAction[];
}
interface TableRow {
  _data: Data;
  [key: string]: any;
}

const DetailPanel = ({ row }: { row: MRT_Row<TableRow> }) => {
  const tableConfig = useAppConfig();

  // https://www.material-react-table.com/docs/examples/lazy-detail-panel

  const content = useMemo(() => {
    if (!row.getIsExpanded()) {
      return '';
    }

    let content = '';
    if (tableConfig.render_detail_panel_js_callback) {
      content = tableConfig.render_detail_panel_js_callback({ row });
    } else {
      content = row.original._data.detail_panel_content;
    }

    return content;
  }, [row, row.getIsExpanded()]);

  return (
    <Stack gap="0.5rem" minHeight="0px">
      <div dangerouslySetInnerHTML={{ __html: content }} />
    </Stack>
  );
};

const App = (props) => {
  const fetchWithParams = useFetchWithParams();

  const tableConfig = useAppConfig();

  function getColumn(key) {
    return tableConfig?.columns?.filter((column) => column.key == key)[0];
  }

  function getRowId(originalRow) {
    return originalRow?.id;
    // return originalRow ? originalRow.id || originalRow._data?.id : null;
  }

  const mrtColumns = useColumnDef({ getColumn, tableConfig });

  function getMrtColumn(key) {
    return mrtColumns.filter((column) => column.id == key)[0];
  }

  const sessionDataId = 'local_table_sql-' + tableConfig.uniqueid + '-session-config-0001';
  const initialSessionConfig = useMemo(() => {
    let config: any = sessionStorage.getItem(sessionDataId);
    if (config) {
      config = JSON.parse(config);
    } else {
      config = {};
    }

    // default filtering setzen
    if (!config.columnFilterFns) {
      config.columnFilterFns = {};
    }

    tableConfig.columns.forEach((column) => {
      if (column.internal) {
        return;
      }

      const mrtColumn = getMrtColumn(column.key);
      config.columnFilterFns[column.key] = config.columnFilterFns[column.key] || mrtColumn.filterFn || mrtColumn.columnFilterModeOptions?.[0];
    });

    if (!config.columnVisibility) {
      config.columnVisibility = {};
    }
    tableConfig.columns.forEach((column) => {
      if (column.visible === false && config.columnVisibility[column.key] === undefined) {
        config.columnVisibility[column.key] = false;
      }
    });

    if (config.columnFilters) {
      config.columnFilters.forEach((filter) => {
        const mrtColumn = getMrtColumn(filter.id);
        if (mrtColumn?.filterVariant == 'date-range' && Array.isArray(filter?.value)) {
          if (filter.value[0] && typeof filter.value[0] == 'string' && filter.value[0].match(/^[0-9]{4}-[0-9]{2}-[0-9]{2}/) /* is a date format */) {
            filter.value[0] = dayjs(filter.value[0]);
          }
          if (filter.value[1] && typeof filter.value[1] == 'string' && filter.value[1].match(/^[0-9]{4}-[0-9]{2}-[0-9]{2}/) /* is a date format */) {
            filter.value[1] = dayjs(filter.value[1]);
          }
        }
      });
    }

    return config;
  }, [tableConfig]);

  // const tableConfigError = false;
  const [columnFilters, _setColumnFilters] = useState<MRT_ColumnFiltersState>(initialSessionConfig.columnFilters || []);
  const [columnFilterFns, setColumnFilterFns] = useState<MRT_ColumnFilterFnsState>(initialSessionConfig.columnFilterFns || []);
  const [globalFilter, setGlobalFilter] = useState(initialSessionConfig.globalFilter || '');
  const [showGlobalFilter, setShowGlobalFilter] = useState(initialSessionConfig.showGlobalFilter || !!initialSessionConfig.globalFilter || false);
  const [showColumnFilters, setShowColumnFilters] = useState(initialSessionConfig.showColumnFilters || initialSessionConfig.columnFilters?.length > 0 || false);

  const setColumnFilters = function (mutator) {
    _setColumnFilters((old) => {
      let columnFilters = mutator(old);
      columnFilters = columnFilters.filter((filter) => {
        const mrtColumn = getMrtColumn(filter.id);
        return !(mrtColumn?.filterVariant == 'multi-select' && filter.value?.length == 0);
      });
      return columnFilters;
    });
  };

  const [detailPanelExpanded, setDetailPanelExpanded] = useState(initialSessionConfig.detailPanelExpanded || false);
  const [sorting, setSorting] = useState<MRT_SortingState>(
    initialSessionConfig.sorting || [
      {
        id: tableConfig.sort_default_column,
        desc: tableConfig.sort_default_order == 'desc',
      },
    ]
  );
  const [columnVisibility, setColumnVisibility] = useState<MRT_VisibilityState>(initialSessionConfig.columnVisibility || {});
  const [pagination, setPagination] = useState<MRT_PaginationState>(
    initialSessionConfig.pagination || {
      pageIndex: tableConfig.initial_page_index,
      pageSize: tableConfig.pagesize,
    }
  );
  const [selectedRowsCount, setSelectedRowsCount] = useState(0);

  // FIXES START: all fixes for material-react-table are here

  // reset the value of range-slider on table load
  // this is needed so the selected values get displayed correctly in the slide component in PROD
  // mrt table bug (only in PROD or without the React.StrictMode)
  useEffect(() => {
    setColumnFilters((columnFilters) =>
      columnFilters.map((filter) => {
        const mrtColumn = getMrtColumn(filter.id);

        if (mrtColumn?.filterVariant == 'range-slider' && Array.isArray(filter.value)) {
          return { ...filter, value: [...filter.value] };
        } else {
          return filter;
        }
      })
    );
  }, []);

  // Accessibility: wenn eine Zelle markiert ist, dann bei Enter und Space den Link / Button klicken
  useEffect(() => {
    const handleKeyDown = (event) => {
      const target = event.target as HTMLElement;
      const isEnterOrSpace = event.key === 'Enter' || event.key === ' ';

      // check if is a table cell
      if (target.classList.contains('MuiTableCell-root') && isEnterOrSpace) {
        // get button / link
        const elements = target.querySelectorAll('a, button, input[type="button"]');
        if (elements.length === 1) {
          // only one element
          (elements[0] as HTMLElement).click();
        }
      }
    };

    tableConfig.containerElement.addEventListener('keydown', handleKeyDown);

    // Cleanup routine
    return () => {
      tableConfig.containerElement.removeEventListener('keydown', handleKeyDown);
    };
  }, []);

  // Accessibility: Bug: bei Filter button in Header wird das Menü nicht geöffnet, stattdessen wird die Tabelle sortiert
  useEffect(() => {
    function handleKeyDown(event) {
      const target = event.target as HTMLElement;
      const isEnterOrSpace = event.key === 'Enter' || event.key === ' ';

      if (target.closest('.Mui-TableHeadCell-Content-Actions') && isEnterOrSpace) {
        event.stopImmediatePropagation();
      }
    }

    tableConfig.containerElement.addEventListener('keydown', handleKeyDown, true);

    // Cleanup routine
    return () => {
      tableConfig.containerElement.removeEventListener('keydown', handleKeyDown, true);
    };
  }, []);

  // Accessibility: Bug: switches (Spalten ein/ausblenden) funktionieren mit dem Keyboard nicht
  useEffect(() => {
    function handleKeyDown(event) {
      const target = event.target as HTMLElement;
      const isEnterOrSpace = event.key === 'Enter' || event.key === ' ';

      if (target.classList.contains('MuiMenuItem-root') && target.closest('.MuiPopover-root') && target.querySelector('input[type="checkbox"]') && isEnterOrSpace) {
        (target.querySelector('input[type="checkbox"]') as any).click();
      }
    }

    document.addEventListener('keydown', handleKeyDown);

    // Cleanup routine
    return () => {
      document.removeEventListener('keydown', handleKeyDown);
    };
  }, []);

  // Accessibility: Bug: Sortierung mittels space geht nicht, weil der event zwei mal feuert?!?
  // https://github.com/BiP-org/moodle-local_table_sql/issues/118#issuecomment-2903472037
  useEffect(() => {
    function handleKeyDown(event) {
      const target = event.target as HTMLElement;

      if (target.closest('.MuiTableSortLabel-root') && event.key === ' ') {
        event.preventDefault();
        event.stopImmediatePropagation();
      }
    }

    document.addEventListener('keydown', handleKeyDown, true);

    // Cleanup routine
    return () => {
      document.removeEventListener('keydown', handleKeyDown, true);
    };
  }, []);

  // Accessibility: Bug: das popup menü im header löst bei enter/space auch die Sortierung aus
  // https://github.com/BiP-org/moodle-local_table_sql/issues/118#issuecomment-2903472037
  // Lösung: bei enter/space im popup menü wird die Sortierung 300ms verhindert
  const shouldpreventResorting = useRef(false);
  useEffect(() => {
    async function handleKeyDown(event) {
      const target = event.target as HTMLElement;
      const isEnterOrSpace = event.key === 'Enter' || event.key === ' ';

      if (target.closest('.MuiMenu-list') && isEnterOrSpace && !target.innerHTML.match(/sort/)) {
        // ist eine liste, und kein Sortieren-Button in der Liste
        shouldpreventResorting.current = true;
        await sleep(300);
        shouldpreventResorting.current = false;
      }
    }

    document.addEventListener('keydown', handleKeyDown, true);

    // Cleanup routine
    return () => {
      document.removeEventListener('keydown', handleKeyDown, true);
    };
  }, []);

  // FIXES END

  const localization = useLocalization();
  const { getString } = localization;

  const fetchListParams = {
    page: pagination.pageIndex,
    // fetchURL.searchParams.set(
    //   'start',
    //   `${pagination.pageIndex * pagination.pageSize}`,
    // );
    page_size: pagination.pageSize,

    filters: JSON.stringify(
      columnFilters
        .map((filter: any) => {
          filter = {
            ...filter,
            fn: columnFilterFns[filter.id],
          } as any;

          if (filter.fn == 'contains' && !filter.value) {
            return null;
          }

          if (Array.isArray(filter.value) && filter.value.filter((val) => val !== null && val !== undefined && !(typeof val === 'number' && Number.isNaN(val))).length == 0) {
            return null;
          }

          const mrtColumn = getMrtColumn(filter.id);

          // convert date filter to timestamps
          if (mrtColumn?.filterVariant == 'date-range' && Array.isArray(filter.value)) {
            // don't change original array, so make a copy!
            filter.value = [...filter.value];
            if (filter.value[0] && typeof filter.value[0] == 'object') {
              // i guess it is a dayjs-object
              filter.value[0] = Math.round(
                filter.value[0]
                  .tz(tableConfig.user_timezone, true) // convert to user_timezone (from the current moodle user)
                  .valueOf() / 1000
              );
            }
            if (filter.value[1] && typeof filter.value[1] == 'object') {
              // i guess it is a dayjs-object
              filter.value[1] =
                Math.round(
                  filter.value[1]
                    .tz(tableConfig.user_timezone, true) // convert to user_timezone (from the current moodle user)
                    .valueOf() / 1000
                ) +
                60 * 60 * 24 - // add extra day to search until end of day
                1;
            }
          }

          return filter;
        })
        // filter null values (from map above)
        .filter((filter) => !!filter)
    ),
    s: globalFilter ?? '',
    tsort: sorting && sorting.length > 0 ? sorting[0].id : null,
    tdir: sorting && sorting.length > 0 ? (sorting[0].desc ? 3 : 4) : null,
  };

  const {
    data,
    isError,
    error: loadDataError,
    isFetching,
    isLoading,
    isRefetching,
    refetch: refetchList,
  } = useQuery({
    queryKey: [
      'table-data',
      tableConfig.uniqueid, // add uniqueid, in case the table_sql is displayed multiple times at the page, the query results get shared by useQuery
      JSON.stringify(fetchListParams),
    ],
    // enabled: showTable,
    queryFn: async () => {
      const result = await fetchWithParams({
        ...fetchListParams,
        table_sql_action: 'list',
      });

      if (result && result.type != 'success') {
        throw result;
      }

      // fix data:
      result?.data?.forEach((row) => {
        // add _data, because it is a mandatory field
        if (!row._data) {
          row._data = {};
        }
      });

      return result;
    },
    refetchOnWindowFocus: false,
    keepPreviousData: true,
    retry: 0, // process.env.NODE_ENV === 'development' ? 0 : 3,
    // don't check network connection, directly try to start loading
    networkMode: 'always',
  });

  const rows: TableRow[] = data?.data;

  // events
  useEffect(() => {
    // @ts-ignore
    document.addEventListener('local_table_sql:reload', refetchList);

    return () => {
      // @ts-ignore
      document.removeEventListener('local_table_sql:reload', refetchList);
    };
  }, [refetchList]);

  const showStickyColumns = useShowStickyColumns(tableConfig);

  // update download form
  useEffect(() => {
    const filtersAndSorting = { ...fetchListParams } as any;
    delete filtersAndSorting.page;
    delete filtersAndSorting.page_size;

    const downloadUrl = new URL(window.location.href);

    // Add the parameters from the object to the URL object
    for (const [key, value] of Object.entries(filtersAndSorting)) {
      downloadUrl.searchParams.set(key, value as any);
    }

    // Get the updated URL as a string
    const newURL = downloadUrl.toString();

    tableConfig.containerElement.querySelectorAll('.table-sql-download-form-container form').forEach(function (form) {
      // Formular auf post umstellen (ist normal get) und die parameter in die action mit der neuen url inkl. Filter überschreiben
      form.method = 'post';
      form.action = newURL;

      var label = form.querySelector('label[for="downloadtype_download"]');
      if (label) {
        if (selectedRowsCount > 0) {
          label.innerHTML = getString('download_selected', selectedRowsCount);
        } else if (filtersAndSorting.s?.trim() || (filtersAndSorting.filters && filtersAndSorting.filters != '[]')) {
          label.innerHTML = getString('download_filtered', data?.meta?.total);
        } else {
          label.innerHTML = getString('download_all');
        }
      }

      const submitButton = form.querySelector('button[type="submit"]');
      if (submitButton) {
        submitButton.disabled = data?.meta?.total == 0;
      }
    });
  }, [JSON.stringify(fetchListParams), selectedRowsCount, data]);

  // Check data loaded
  useEffect(() => {
    if (!rows?.length) {
      return;
    }

    const selected = {} as any;
    rows.forEach((row) => {
      if (row._data.selected) {
        selected[getRowId(row)] = true;
      }
    });

    setRowSelection(selected);
  }, [rows]);

  // Check data loaded
  useEffect(() => {
    if (!rows?.length) {
      return;
    }

    if (!isLoading && !isRefetching) {
      // only set selectedRowsCount after loading is finished
      setSelectedRowsCount(data?.meta?.selected_rows_count);
    }
  }, [rows, isLoading, isRefetching]);

  const [rowSelection, setRowSelection] = useState<MRT_RowSelectionState>(initialSessionConfig.rowSelection || {});

  // if (tableConfigError) {
  //   return (
  //     <Paper>
  //       <div style={{ minHeight: 200, padding: 20 }}>
  //         {getString('loading_error')} {(tableConfigError as any)?.error ? '(' + (tableConfigError as any)?.error + ')' : ''}
  //       </div>
  //     </Paper>
  //   );
  // }

  const editColumn = getColumn('edit');
  const deleteColumn = getColumn('delete');

  // let row_actions = tableConfig.row_actions || [];
  // if (editColumn) {
  //   row_actions = {{
  //     ...
  //   }, ...row_actions };
  // }

  let renderRowActions: any = undefined;
  let renderRowActionMenuItems: any = undefined;

  if (tableConfig.row_actions?.length || tableConfig.row_actions_js_callback) {
    const getRowActions = ({ row }: { row: MRT_Row<TableRow> }) => {
      let row_actions: any[];

      if (row.original._data.row_actions) {
        // reconstruct the full row_action object from the partial row.row_actions
        row_actions = row.original._data.row_actions;
        row_actions = row_actions.map((row_action) => {
          const base_row_action = tableConfig.row_actions.find((base_row_action) => base_row_action.id == row_action.id);
          return { ...base_row_action, ...row_action };
        });
      } else {
        row_actions = deepClone(tableConfig.row_actions);
      }

      if (tableConfig.row_actions_js_callback) {
        row_actions = tableConfig.row_actions_js_callback({ row: row.original, row_actions }) || [];
      }

      return row_actions;
    };

    const getRowActionProps = (row, action, closeMenu) => {
      if (action.disabled) {
        return {};
      }

      let url = action.url;
      if (typeof url === 'string') {
        url = replacePlaceholders(url, row.original);
      }

      if (action.type == 'delete' && !action.onclick) {
        // fallback message if no onclick is set
        // this is actually not used anywhere for now
        return {
          href: url,
          onClick: (e) => {
            e.preventDefault();

            if (window.confirm('Eintrag wirklich löschen?')) {
              document.location.href = url + '&confirm=1';
            }
          },
        };
      }

      return {
        href: url || undefined,
        target: action.target,
        onClick: (e) => {
          let ret = null;
          if (action.onclick) {
            ret = stringToFunction(action.onclick)(e, row);
          }

          if (ret === false) {
            // weiterleitung zum href verhindern
            e.preventDefault();
            // "return ret" funktioniert nicht?
          }

          closeMenu?.();
        },
      };
    };

    const renderActionIcon = (action) => {
      let icon = null as any;
      if (action.icon) {
        icon = (
          <i className={action.icon} style={{ fontSize: 19 }}>
            &nbsp;
          </i>
        );
      } else if (action.type == 'edit') {
        icon = <EditIcon />;
      } else if (action.type == 'delete') {
        icon = <DeleteIcon />;
      }

      return icon;
    };

    if (tableConfig.row_actions_display_as_menu) {
      // als dropdown anzeigen
      renderRowActionMenuItems = ({ closeMenu, row }) =>
        getRowActions({ row }).map((action, i) => {
          const icon = renderActionIcon(action);

          return (
            <MenuItem
              {...getRowActionProps(row, action, closeMenu)}
              key={i}
              disabled={action.disabled}
              component="a"
              className="menu-item"
              // style={{ color: 'inherit', textDecoration: 'none' }}
              // disableGutters={true}
            >
              {!!icon && <span style={{ marginRight: 5 }}>{icon}</span>}
              {action.label}
            </MenuItem>
          );
        });
    } else {
      // direkt als Buttons anzeigen

      renderRowActions = ({ row }) => (
        <>
          {getRowActions({ row }).map((action, i) => {
            const icon = renderActionIcon(action);

            const props = getRowActionProps(row, action, null);

            return icon ? (
              <IconButton key={i} disabled={action.disabled} {...props} className={action.class}>
                {icon}
              </IconButton>
            ) : action.disabled ? (
              <button key={i} disabled={action.disabled} className={action.class ?? 'btn btn-secondary btn-sm'} {...props}>
                {action.label || 'no-label'}
              </button>
            ) : (
              <a key={i} className={action.class ?? 'btn btn-secondary btn-sm'} {...props}>
                {action.label || 'no-label'}
              </a>
            );
          })}
        </>
      );
    }
  }

  const enableRowActions = editColumn || deleteColumn || tableConfig.row_actions?.length;

  const maxLinesInHeader = Math.max(
    ...tableConfig.columns
      .filter((column) => !column.internal)
      // search for newlines in header
      .map((column) => column.header.split('\n').length)
  );

  const cellProps =
    (type) =>
    ({ column }) => {
      let userStyles = getColumn(column.id)?.style || undefined;
      if (userStyles) {
        userStyles = convertSnakeCaseToCamelCase(userStyles);
      }

      let columnDefintion = getColumn(column.id);
      let className = `local_table_sql-column-${column.id}`;
      if (columnDefintion?.data_type && columnDefintion?.data_type != 'unknown') {
        className += ' ' + `local_table_sql-column-type-${columnDefintion?.data_type}`;
      }
      if (columnDefintion?.class) {
        className += ' ' + getColumn(column.id)?.class;
      }

      if (type == 'head' && maxLinesInHeader > 1) {
        className += ' multilineHeader-' + maxLinesInHeader;
      }

      return {
        style: {
          ...userStyles,
        },
        className,
      };
    };

  // const hasStickyActionMenu = enableRowActions && tableConfig.row_actions_display_as_menu; // && showStickyColumns;

  // save all to session

  // sort
  const lastSessionData = useRef<any>();
  {
    const sessionData = {
      sorting,
      pagination,
      globalFilter,
      columnFilters,
      columnFilterFns,
      columnVisibility,
      showGlobalFilter,
      showColumnFilters,
      // rowSelection, // not needed here, it is stored in the moodle session
      detailPanelExpanded,
    };

    const newSessionData = JSON.stringify(sessionData);
    if (lastSessionData.current != newSessionData) {
      lastSessionData.current = newSessionData;
      sessionStorage.setItem(sessionDataId, newSessionData);
    }
  }

  const eventData = {
    isFetching,
    isError,
    selectedRowsCount,
  };

  // const fireEvent = useMemo(
  //     () =>
  //         debounce((eventData) => {
  //           console.log(eventData, tableConfig.containerElement);
  //           // tableConfig.containerElement
  //         }, 250),
  //     [tableConfig.containerElement]
  // );

  const fireEvent = (state) => {
    // Dispatch the event
    tableConfig.containerElement.dispatchEvent(
      new CustomEvent('local_table_sql:state', {
        detail: state,
        bubbles: true, // Set this to true to allow bubbling
        cancelable: true, // Optional: allows the event to be canceled
      })
    );
  };

  useEffect(() => {
    fireEvent(eventData);
  }, [JSON.stringify(eventData)]);

  // hack für accessibility: um mittels polling den aria-expanded zu ändern
  const icons: Partial<MRT_Icons> = {
    SearchIcon: (props: any) => <SearchIcon {...props} data-accessibility-hackid="SearchIcon" />,
    SearchOffIcon: () => <SearchOffIcon data-accessibility-hackid="SearchOffIcon" />,
    FullscreenIcon: () => <FullscreenIcon data-accessibility-hackid="FullscreenIcon" />,
    FullscreenExitIcon: () => <FullscreenExitIcon data-accessibility-hackid="FullscreenExitIcon" />,
    FilterListIcon: () => <FilterListIcon data-accessibility-hackid="FilterListIcon" />,
    FilterListOffIcon: () => <FilterListOffIcon data-accessibility-hackid="FilterListOffIcon" />,
    ViewColumnIcon: () => <ViewColumnIcon data-accessibility-hackid="ViewColumnIcon" />,
  };
  useEffect(() => {
    // hack für accessibility: um mittels polling den aria-expanded zu ändern
    const interval = setInterval(() => {
      tableConfig.containerElement
        ?.querySelectorAll('[data-accessibility-hackid="SearchIcon"], [data-accessibility-hackid="FullscreenIcon"], [data-accessibility-hackid="FilterListIcon"]')
        .forEach((el) => {
          // Only set aria-expanded if parentNode is a button and is not in the table (so it is probably a button in the header right)
          if (el.parentNode && el.parentNode.nodeName === 'BUTTON' && !el.closest('.MuiTable-root')) {
            el.parentNode.setAttribute('aria-expanded', 'false');
          } else if (el.matches('[data-accessibility-hackid="FilterListIcon"]') && el.parentNode.nodeName === 'BUTTON' && el.closest('.MuiTable-root')) {
            // it is the filter button for a specific table column
            el.parentNode.setAttribute('aria-haspopup', 'true');
          }
        });
      tableConfig.containerElement
        ?.querySelectorAll('[data-accessibility-hackid="SearchOffIcon"], [data-accessibility-hackid="FullscreenExitIcon"], [data-accessibility-hackid="FilterListOffIcon"]')
        .forEach((el) => {
          // Only set aria-expanded if parentNode is a button
          if (el.parentNode && el.parentNode.nodeName === 'BUTTON' && !el.closest('.MuiTable-root')) {
            el.parentNode.setAttribute('aria-expanded', 'true');
          }
        });

      tableConfig.containerElement?.querySelector('[data-accessibility-hackid="ViewColumnIcon"]')?.parentNode.setAttribute('aria-haspopup', 'true');
    }, 500);

    return () => clearInterval(interval);
  }, [tableConfig.containerElement]);

  const table = useMaterialReactTable<TableRow>({
    // enableKeyboardShortcuts: false,
    columns: mrtColumns,
    data: data?.data ?? [], //data is undefined on first render
    positionPagination: 'both',
    enableSortingRemoval: false, // man kann nicht in einen "unsortierten" Zustand wechseln
    initialState: {
      // only in dev start with filters visible
      showColumnFilters: false, // process.env.NODE_ENV == 'development',
      density: 'compact',
      // columnPinning: hasStickyActionMenu ? { right: ['mrt-row-actions'] } : {},
    },
    // enablePinning: hasStickyActionMenu,
    muiPaginationProps: {
      rowsPerPageOptions: tableConfig.page_size_options,
      showRowsPerPage: tableConfig.enable_page_size_selector !== false,
    },
    enableDensityToggle: false,
    enableGlobalFilter: tableConfig.enable_global_filter,
    enableFilters: tableConfig.enable_column_filters,
    enableRowActions,
    // You can modify the default column widths by setting the defaultColumn prop on the table.
    defaultColumn: {
      minSize: 10, //allow columns to get smaller than default
      maxSize: 9001, //allow columns to get larger than default
      size: 10, //make columns wider by default
    },
    // https://codesandbox.io/p/sandbox/github/KevinVandy/material-react-table/tree/main/apps/material-react-table-docs/examples/customize-filter-modes/sandbox?file=%2Fsrc%2FJS.js%3A55%2C7-55%2C30
    enableColumnFilterModes: true,
    positionActionsColumn: 'last',
    renderRowActions: renderRowActions,
    renderRowActionMenuItems: renderRowActionMenuItems,
    manualFiltering: true,
    manualPagination: true,
    manualSorting: true,
    // muiToolbarAlertBannerProps={
    //   isError
    //     ? {
    //         color: 'error',
    //         children: 'Error loading data',
    //       }
    //     : undefined
    // }
    onColumnFiltersChange: setColumnFilters,
    onColumnFilterFnsChange: setColumnFilterFns,
    onGlobalFilterChange: setGlobalFilter,
    onShowGlobalFilterChange: setShowGlobalFilter,
    onShowColumnFiltersChange: setShowColumnFilters,
    onPaginationChange: setPagination,
    onSortingChange: (...params) => {
      if (shouldpreventResorting.current) {
        return;
      }
      setSorting(...params);
    },
    onColumnVisibilityChange: setColumnVisibility,
    renderTopToolbarCustomActions: () => (
      <div></div>
      // <Tooltip arrow title="Refresh Data">
      //   <IconButton onClick={() => refetchList()}>
      //     <RefreshIcon />
      //   </IconButton>
      // </Tooltip>
    ),
    renderDetailPanel: tableConfig.enable_detail_panel ? ({ row }) => <DetailPanel row={row} /> : undefined,
    onExpandedChange: setDetailPanelExpanded,
    rowCount: data?.meta?.total ?? 0,
    state: {
      columnFilters,
      columnFilterFns,
      columnVisibility,
      globalFilter,
      isLoading,
      pagination,
      showAlertBanner: isError,
      showProgressBars: isFetching,
      sorting,
      rowSelection,
      showGlobalFilter,
      showColumnFilters,
      expanded: detailPanelExpanded,
    },
    muiTableBodyProps: {
      sx: {
        //stripe the rows, make odd rows a darker color
        '& > tr:nth-of-type(odd):not(.Mui-selected)': {
          backgroundColor: '#f9f9f9',
        },
      },
    },
    muiTableProps: {
      // alternative zu has-sticky-action-menu wäre columnPinning+enablePinning
      className: showStickyColumns && enableRowActions && tableConfig.row_actions_display_as_menu ? 'has-sticky-action-menu' : '',
    },
    // disable "5 von 26 Zeile(n) ausgewählt"
    positionToolbarAlertBanner: 'none',
    renderTopToolbar: ({ table }) => {
      const infoText =
        selectedRowsCount > 0 ? (
          <>
            {getString('num_rows_selected', selectedRowsCount)}
            <Button
              variant="outlined"
              style={{ textTransform: 'none', minWidth: 0, marginLeft: 10, padding: '0 4px', borderColor: '#777', color: 'inherit' }}
              onClick={async () => {
                const ret = await fetchWithParams({
                  ...fetchListParams,
                  table_sql_action: 'select_all',
                });

                refetchList();
                setSelectedRowsCount(ret?.meta?.selected_rows_count);
              }}
            >
              {getString('select_all_rows_on_all_pages')}
            </Button>
            <Button
              variant="outlined"
              style={{ textTransform: 'none', minWidth: 0, marginLeft: 10, padding: '0 4px', borderColor: '#777', color: 'inherit' }}
              onClick={async () => {
                const ret = await fetchWithParams({
                  ...fetchListParams,
                  table_sql_action: 'select_none',
                });

                refetchList();
                setSelectedRowsCount(0);
              }}
            >
              {getString('select_none')}
            </Button>
          </>
        ) : (
          ''
        );

      const errorText = !!loadDataError && ((loadDataError as any)?.error || getString('loading_error'));

      return (
        <>
          <Collapse in={!!infoText || !!errorText} timeout={200}>
            {!!errorText && (
              <Alert severity="error" icon={false}>
                <div style={{ whiteSpace: 'pre-wrap' }}>{errorText}</div>
              </Alert>
            )}
            {!!infoText && (
              <Alert severity="info" icon={false}>
                {infoText}
              </Alert>
            )}
          </Collapse>
          <MRT_TopToolbar table={table} />
        </>
      );
    },
    enableRowSelection: tableConfig.enable_row_selection,
    enableMultiRowSelection: tableConfig.enable_row_selection,
    enableSelectAll: tableConfig.enable_row_selection,
    localization: localization.reactTable,
    onRowSelectionChange: async (mutator) => {
      // actually first parameter should be the new values, but is a mutator function?!?
      const oldValues = rowSelection;

      // @ts-ignore
      const newValues = mutator(rowSelection);

      const row_ids_selected = [] as any[];
      const row_ids_unselected = [] as any[];

      rows?.map((row) => {
        const row_id = getRowId(row);
        if (newValues[row_id] && !oldValues[row_id]) {
          row_ids_selected.push(row_id);

          // hack: update cached value
          // wenn die während die liste erneut geladen wird (z.B. bei seiten-navigation), soll bereits der geänderte wert angezeigt werden
          row._data.selected = true;
        } else if (oldValues[row_id] && !newValues[row_id]) {
          row_ids_unselected.push(row_id);

          // hack: update cached value
          // wenn die während die liste erneut geladen wird (z.B. bei seiten-navigation), soll bereits der geänderte wert angezeigt werden
          row._data.selected = false;
        }
      });

      setRowSelection(newValues);

      const ret = await fetchWithParams({
        table_sql_action: 'set_selected',
        row_ids_selected,
        row_ids_unselected,
      });

      setSelectedRowsCount(ret?.meta?.selected_rows_count);
    },
    // renderColumnFilterModeMenuItems={() => <div>item3</div>}
    // components={{
    //   FilterRow: (props) => <div>Filter</div>,
    // }}
    muiSelectCheckboxProps: ({ row }) => ({
      name: tableConfig.uniqueid + '[]',
      value: getRowId(row.original),
    }),
    getRowId: tableConfig.enable_row_selection ? (originalRow) => getRowId(originalRow) : undefined,
    muiTableBodyRowProps: ({ row, isDetailPanel }) => {
      let props = {
        sx: {},
      } as any;

      if (isDetailPanel) {
        // ist aufgeklappte detail panel, keine props hier
        return;
      }

      if (tableConfig.enable_row_selection) {
        props.onClick = function (e) {
          // don't handle expand click, if clicked on select-box, action menu, links, inputs or buttons
          if (e.target.closest('a, button, input, .local_table_sql-column-mrt-row-expand, .local_table_sql-column-mrt-row-actions, .local_table_sql-ignore-click-row-action')) {
            return;
          }

          row.getToggleSelectedHandler()(e);
        };
        props.className = 'clickable-row';
      } else if (tableConfig.enable_detail_panel) {
        props.onClick = function (e) {
          // don't handle expand click, if clicked on select-box, action menu, links, inputs or buttons
          if (e.target.closest('a, button, input, .local_table_sql-column-mrt-row-expand, .local_table_sql-column-mrt-row-actions, .local_table_sql-ignore-click-row-action')) {
            return;
          }

          // geht nicht:
          // row.getToggleExpandedHandler()();
          // geht:
          e.target.closest('tr').querySelector('td.local_table_sql-column-mrt-row-expand button')?.click();
        };
        props.className = 'clickable-row';
      }

      return props;
    },
    muiTableHeadCellProps: cellProps('head'),
    muiTableBodyCellProps: cellProps('body'),

    muiFilterDatePickerProps: {
      // ohne dem kann man nur Jahr und Tag wählen
      // mit dieser Option kann Jahr, Monat und Tag gewählt werden
      views: ['year', 'month', 'day'],
    },

    // turn on row virtualization for tables with a long list, this makes scrolling much smoother
    enableRowVirtualization: pagination.pageSize >= 500,

    icons,

    ...tableConfig.mrtOptions,
  });

  return <MaterialReactTable table={table} />;
};

export default App;
