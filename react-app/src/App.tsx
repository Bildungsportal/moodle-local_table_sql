// beispiele:
// https://www.material-react-table.dev/?path=/story/features-search-examples--search-enabled-default

import React, { useContext, useEffect, useMemo, useState } from 'react';
import {
  MaterialReactTable,
  MRT_RowSelectionState,
  type MRT_ColumnDef,
  type MRT_ColumnFiltersState,
  type MRT_FilterFnsState,
  type MRT_PaginationState,
  type MRT_SortingState,
  MRT_TopToolbar,
  MRT_VisibilityState,
} from 'material-react-table';
import { MenuItem, Box, IconButton, Button, Alert, Collapse, Paper } from '@mui/material';
import { Edit as EditIcon, Delete as DeleteIcon } from '@mui/icons-material';
import { useQuery } from '@tanstack/react-query';
import './App.scss';
import { useAppConfig, useFetchWithParams } from 'lib/hooks';
import { convertSnakeCaseToCamelCase, debounce, replacePlaceholders, stringToFunction } from 'lib/helpers';
import { useLocalization } from 'lib/localization';

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

function useShowStickyColumns(tableConfig) {
  const [sticky, setSticky] = useState(false);

  const check = useMemo(
    () =>
      debounce(() => {
        console.log('check');
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

type TData = any;

const App = (props) => {
  const fetchWithParams = useFetchWithParams();

  const tableConfig = useAppConfig();

  const tableConfigError = false;

  const [columnFilters, setColumnFilters] = useState<MRT_ColumnFiltersState>([]);
  const [columnFilterFns, setColumnFilterFns] = useState<MRT_FilterFnsState>([] as any);
  const [globalFilter, setGlobalFilter] = useState('');
  const [sorting, setSorting] = useState<MRT_SortingState>([]);
  const [columnVisibility, setColumnVisibility] = useState<MRT_VisibilityState>({});
  const setColumnVisibilityByUser = (cb) => {
    setColumnVisibility((old) => {
      const newState = cb(old);
      sessionStorage.setItem('local_table_sql-' + tableConfig.uniqueid + '-' + 'columnVisibility', JSON.stringify(newState));
      return newState;
    });
  };
  const [pagination, setPagination] = useState<MRT_PaginationState>({
    pageIndex: 0,
    pageSize: 10,
  });
  const [showTable, setShowTable] = useState(false);
  const [selectedRowsCount, setSelectedRowsCount] = useState(0);

  const localization = useLocalization();
  const { getString } = localization;

  // Check Table config loaded
  useEffect(() => {
    if (!tableConfig) {
      return;
    }

    setPagination({ ...pagination, pageSize: tableConfig.pagesize });
    setSorting([
      {
        id: tableConfig.sort_default_column,
        desc: tableConfig.sort_default_order == 'desc',
      },
    ]);

    // set all columns to filter "contains"
    const columnFilterFns = {};
    tableConfig.columns.forEach((column) => {
      columnFilterFns[column.key] = 'contains';
    });
    setColumnFilterFns(columnFilterFns);

    try {
      // try to load from session
      const columnVisibility = JSON.parse(sessionStorage.getItem('local_table_sql-' + tableConfig.uniqueid + '-' + 'columnVisibility') || '') as MRT_VisibilityState;
      setColumnVisibility(columnVisibility);
    } catch (e) {
      // else load defaults
      const columnVisibility = {} as MRT_VisibilityState;
      tableConfig.columns.forEach((column) => {
        if (column.visible === false) {
          columnVisibility[column.key] = false;
        }
      });

      setColumnVisibility(columnVisibility);
    }

    setShowTable(true);
  }, [tableConfig]);

  function getColumn(key) {
    return tableConfig?.columns?.filter((column) => column.key == key)[0];
  }

  function getRowId(originalRow) {
    return originalRow?.id;
    // return originalRow ? originalRow.id || originalRow._data?.id : null;
  }

  const fetchListParams = {
    page: pagination.pageIndex,
    // fetchURL.searchParams.set(
    //   'start',
    //   `${pagination.pageIndex * pagination.pageSize}`,
    // );
    page_size: pagination.pageSize,

    filters: JSON.stringify(
      (columnFilters ?? []).map((filter) => {
        return {
          ...filter,
          fn: columnFilterFns[filter.id],
        };
      })
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
    queryKey: ['table-data', JSON.stringify(fetchListParams)],
    enabled: !!tableConfig && showTable,
    queryFn: async () => {
      const result = await fetchWithParams(fetchListParams);

      if (result && result.type != 'success') {
        throw result;
      }

      // format rows from array to key=>value
      // result.data = result.data.map((row) => {
      //   let resultRow = {};

      //   tableConfig.columns.forEach((column, i) => {
      //     // if (typeof row[i] == 'string' && row[i].match(/<a/i)) {
      //     //   // link inside row
      //     //   resultRow[column.key] = stripTags(row[i]);
      //     //   resultRow[column.key + '_table_sql_link'] = 'url';
      //     // } else {
      //     resultRow[column.key] = row[i];
      //     // }
      //   });

      //   return resultRow;
      // });

      return result;
    },
    refetchOnWindowFocus: false,
    keepPreviousData: true,
    retry: process.env.NODE_ENV === 'development' ? 0 : 3,
  });

  const rows = data?.data;

  // events
  React.useEffect(() => {
    // @ts-ignore
    document.addEventListener('local_table_sql:reload', refetchList);

    return () => {
      // @ts-ignore
      document.removeEventListener('local_table_sql:reload', refetchList);
    };
  }, [refetchList]);

  const showStickyColumns = useShowStickyColumns(tableConfig);

  // update download form
  useEffect(
    function () {
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
    },
    [JSON.stringify(fetchListParams), selectedRowsCount, data]
  );

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

  const columns = useMemo<MRT_ColumnDef<TData>[]>(() => {
    if (!tableConfig) {
      return [];
    }

    return (
      tableConfig.columns
        .filter((column) => column.key != 'edit' && column.key != 'delete')
        // column "id" is hidden for example
        .filter((column) => !column.internal)
        .map(
          (column) =>
            ({
              id: column.key,
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

              Cell: ({ renderedCellValue, row, column }) => {
                let content;

                if (row.original[column.id] === '' || row.original[column.id] === null) {
                  // original content is empty => no content
                  return null;
                }
                if (typeof row.original[column.id] == 'string' && row.original[column.id].match(/[<>]/)) {
                  // is html, no highlighting possible
                  content = <span dangerouslySetInnerHTML={{ __html: row.original[column.id] }} />;
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
                column.data_type == 'number'
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

              // filterVariant: 'multi-select',
              // filterSelectOptions: [
              //   { text: 'Male', value: 'Male' },
              //   { text: 'Female', value: 'Female' },
              //   { text: 'Other', value: 'Other' },
              // ],
              // geht nicht:
              // filterComponent: (props) => <div>Filte r123</div>

              ...column,
            } as MRT_ColumnDef<TData>)
        )
    );
  }, [tableConfig]);

  const rowsPerPageOptions = useMemo(() => {
    let pageSizes = [10, 20, 50, 100];

    if (!tableConfig?.pagesize) {
      // config not yet loaded
      return pageSizes;
    }

    const defaultPageSize = parseInt(tableConfig.pagesize);
    if (pageSizes.includes(defaultPageSize)) {
      return pageSizes;
    } else {
      pageSizes.push(defaultPageSize);
      return pageSizes.sort((a, b) => a - b);
    }
  }, [pagination.pageSize]);

  const [rowSelection, setRowSelection] = useState<MRT_RowSelectionState>({});

  if (tableConfigError) {
    return (
      <Paper>
        <div style={{ minHeight: 200, padding: 20 }}>
          {getString('loading_error')} {(tableConfigError as any)?.error ? '(' + (tableConfigError as any)?.error + ')' : ''}
        </div>
      </Paper>
    );
  }

  if (!showTable) {
    return (
      <Paper>
        <div style={{ minHeight: 200, padding: 20 }}>{getString('loading')}</div>
      </Paper>
    );
  }

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

  if (tableConfig.row_actions?.length || tableConfig.row_actions_callback) {
    const getRowActions = ({ row }) => {
      if (tableConfig.row_actions_callback) {
        return tableConfig.row_actions_callback({ row: row.original, row_actions: deepClone(tableConfig.row_actions) }) || [];
        // return produce({ row, row_actions: tableConfig.row_actions }, (globalState_hack.row_actions_callback) || [];
      } else {
        return tableConfig.row_actions;
      }
    };

    const getRowActionProps = (row, action) => {
      if (action.disabled) {
        return {};
      }

      let url = action.url;
      if (typeof url === 'string') {
        url = replacePlaceholders(url, row.original);
      }

      // in moodle onclick is named lowercase!
      if (action.onclick) {
        return {
          onClick: (e) => {
            const ret = stringToFunction(action.onclick)(e);

            if (ret === false || e.isPropagationStopped()) {
              if (url) {
                document.location.href = url;
              }
            }
          },
        };
      }

      if (action.type == 'delete') {
        url += '&confirm=1';

        return {
          onClick: () => {
            if (window.confirm('Wirklick löschen?')) {
              document.location.href = url;
            }
          },
        };
      }

      return {
        href: url || undefined,
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
      renderRowActionMenuItems = ({ row }) =>
        getRowActions({ row }).map((action, i) => {
          const icon = renderActionIcon(action);

          return (
            <a key={i} {...getRowActionProps(row, action)} style={{ color: 'inherit', textDecoration: 'none' }}>
              <MenuItem
                disabled={action.disabled}
                // disableGutters={true}
              >
                {!!icon && <span style={{ marginRight: 5 }}>{icon}</span>}
                {action.label}
              </MenuItem>
            </a>
          );
        });
    } else {
      // direkt als Buttons anzeigen

      renderRowActions = ({ row }) => (
        <Box>
          {getRowActions({ row }).map((action, i) => {
            const icon = renderActionIcon(action);

            return (
              <a key={i} {...getRowActionProps(row, action)} style={{ color: 'inherit' }}>
                {icon ? (
                  <IconButton disabled={action.disabled}>{icon}</IconButton>
                ) : (
                  <Button disabled={action.disabled} variant="outlined" style={{ textTransform: 'none', padding: '0 2px', borderColor: '#777', color: 'inherit' }}>
                    {action.label || 'no-label'}
                  </Button>
                )}
              </a>
            );
          })}
        </Box>
      );
    }
  }

  const enableRowActions = editColumn || deleteColumn || tableConfig.row_actions?.length;

  return (
    <>
      <MaterialReactTable
        columns={columns}
        data={data?.data ?? []} //data is undefined on first render
        positionPagination="both"
        enableSortingRemoval={false} // man kann nicht in einen "unsortierten" Zustand wechseln
        initialState={{
          // only in dev start with filters visible
          showColumnFilters: false, // process.env.NODE_ENV == 'development',
          density: 'compact',
        }}
        muiTablePaginationProps={{
          rowsPerPageOptions,
        }}
        enableDensityToggle={false}
        enableGlobalFilter={tableConfig.enable_global_filter || false}
        enableRowActions={enableRowActions}
        // You can modify the default column widths by setting the defaultColumn prop on the table.
        defaultColumn={{
          minSize: 10, //allow columns to get smaller than default
          maxSize: 9001, //allow columns to get larger than default
          size: 10, //make columns wider by default
        }}
        // https://codesandbox.io/p/sandbox/github/KevinVandy/material-react-table/tree/main/apps/material-react-table-docs/examples/customize-filter-modes/sandbox?file=%2Fsrc%2FJS.js%3A55%2C7-55%2C30
        enableColumnFilterModes
        positionActionsColumn="last"
        renderRowActions={renderRowActions}
        renderRowActionMenuItems={renderRowActionMenuItems}
        manualFiltering
        manualPagination
        manualSorting
        // muiToolbarAlertBannerProps={
        //   isError
        //     ? {
        //         color: 'error',
        //         children: 'Error loading data',
        //       }
        //     : undefined
        // }
        onColumnFiltersChange={setColumnFilters}
        onColumnFilterFnsChange={setColumnFilterFns}
        onGlobalFilterChange={setGlobalFilter}
        onPaginationChange={setPagination}
        onSortingChange={setSorting}
        onColumnVisibilityChange={setColumnVisibilityByUser}
        renderTopToolbarCustomActions={() => (
          <div></div>
          // <Tooltip arrow title="Refresh Data">
          //   <IconButton onClick={() => refetchList()}>
          //     <RefreshIcon />
          //   </IconButton>
          // </Tooltip>
        )}
        rowCount={data?.meta?.total ?? 0}
        state={{
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
        }}
        muiTableBodyProps={{
          sx: {
            //stripe the rows, make odd rows a darker color
            '& tr:nth-of-type(odd):not(.Mui-selected)': {
              backgroundColor: '#f9f9f9',
            },
          },
        }}
        muiTableProps={{
          className: showStickyColumns && enableRowActions && tableConfig.row_actions_display_as_menu ? 'has-sticky-action-menu' : '',
        }}
        // disable "5 von 26 Zeile(n) ausgewählt"
        positionToolbarAlertBanner="none"
        renderTopToolbar={({ table }) => {
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
        }}
        enableRowSelection={tableConfig.enable_row_selection}
        enableMultiRowSelection={tableConfig.enable_row_selection}
        enableSelectAll={tableConfig.enable_row_selection}
        localization={localization.reactTable}
        onRowSelectionChange={async (mutator) => {
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
        }}
        // renderColumnFilterModeMenuItems={() => <div>item3</div>}
        // components={{
        //   FilterRow: (props) => <div>Filter</div>,
        // }}
        muiSelectCheckboxProps={({ row }) => ({
          name: tableConfig.uniqueid + '[]',
          value: getRowId(row.original),
        })}
        getRowId={tableConfig.enable_row_selection ? (originalRow) => getRowId(originalRow) : undefined}
        muiTableBodyRowProps={
          tableConfig.enable_row_selection
            ? ({ row }) => ({
                onClick: row.getToggleSelectedHandler(),
                sx: {
                  cursor: 'pointer',
                },
              })
            : undefined
        }
        muiTableBodyCellProps={({ column }) => {
          let userStyles = getColumn(column.id)?.style || undefined;
          if (userStyles) {
            userStyles = convertSnakeCaseToCamelCase(userStyles);
          }

          return {
            style: {
              ...userStyles,
            },
          };
        }}
      />
    </>
  );
};

export default App;
