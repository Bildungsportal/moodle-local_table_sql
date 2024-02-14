import React from 'react';
import ReactDOM from 'react-dom/client';
import './index.css';
import App from './App';
import reportWebVitals from './reportWebVitals';
import { AppContext } from 'lib/context';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fetchWithParams } from 'lib/helpers';

const queryClient = new QueryClient();

function start_table_sql(config) {
  let { container } = config;
  let containerElement;

  if (typeof container == 'string') {
    containerElement = document.querySelector(container);
    if (!containerElement) {
      throw `container for table_sql "${container}" not found`;
    }
  } else {
    containerElement = container;
  }

  config.containerElement = containerElement;

  let tableElement = containerElement.querySelector('.table-sql-container') || container;

  const root = ReactDOM.createRoot(tableElement as HTMLElement);
  root.render(
    <React.StrictMode>
      <AppContext.Provider value={{ config }}>
        <QueryClientProvider client={queryClient}>
          <App />
        </QueryClientProvider>
      </AppContext.Provider>
    </React.StrictMode>
  );
}

(window as any).start_table_sql = start_table_sql;

if (process.env.NODE_ENV == 'development') {
  if (true) {
    (async () => {
      let result = await fetchWithParams({
        uniqueid: '',
        table_sql_action: 'get_config',
      });

      if (result && result.type == 'success') {
        start_table_sql({
          container: '#table-sql-dev',
          ...result.data,
        });
        return result.data;
      } else {
        throw result;
      }
    })();
  } else {
    start_table_sql({
      container: '#table-sql-dev',
      uniqueid: 'selecteddeliveries',
      pagesize: 8,
      enable_row_selection: true,
      is_sortable: true,
      sort_default_column: 'id',
      sort_default_order: 'desc',
      columns: [
        { key: 'id', class: '', style: { 'vertical-align': 'middle' }, header: 'Zustell-ID', data_type: 'number', sorting: true, filter: true },
        { key: 'description', class: '', style: { 'vertical-align': 'middle' }, header: 'Beschreibung test', data_type: 'string', sorting: true, filter: true },
        { key: 'relateduserid', class: '', style: { 'vertical-align': 'middle' }, header: 'Betroffene Person', data_type: 'unknown', sorting: true, filter: true },
        { key: 'class', class: '', style: { 'vertical-align': 'middle' }, header: 'Klasse', data_type: 'string', sorting: true, filter: true },
        { key: 'rsa', class: '', style: { 'vertical-align': 'middle' }, header: 'Empfangsbest\u00e4tigung erforderlich (RSa)', data_type: 'number', sorting: true, filter: true },
        { key: 'deliveryway', class: '', style: { 'vertical-align': 'middle' }, header: 'Zustellweg', data_type: 'number', sorting: true, filter: true },
        { key: 'filename', class: '', style: { 'vertical-align': 'middle' }, header: 'Dokument', data_type: 'unknown', sorting: true, filter: true },
        {
          key: 'status_string',
          class: '',
          style: { 'vertical-align': 'middle' },
          header: 'Status',
          data_type: 'number',
          sorting: true,
          filter: true,
          filterVariant: 'multi-select',
          filterSelectOptions: [
            { text: 'Dokument wird signiert', value: 1 },
            { text: 'Versand wird verarbeitet', value: 4 },
            { text: 'Widerrufen', value: 10 },
            { text: 'Bereit f\u00fcr Versand', value: 3 },
            { text: 'Signiert', value: 2 },
          ],
          columnFilterModeOptions: [],
        },
        { key: 'status', class: '', style: { 'vertical-align': 'middle' }, header: '', data_type: 'number', sorting: false, filter: false },
      ],
      row_actions: [
        { url: "javascript:alert('{id}')", type: 'other', label: 'JavaScript Demo', id: 'test', disabled: true, icon: '' },
        { url: '/local/delivery/multiaction.php?orgid=900001&selecteddeliveries[]={id}&action=3', type: 'other', label: 'Als Zip Datei herunterladen', id: 'test2', disabled: false, icon: '' },
        { url: '/local/delivery/multiaction.php?orgid=900001&selecteddeliveries[]={id}&action=2', type: 'other', label: 'with icon', id: '', disabled: false, icon: 'fa-paper-plane fa-solid' },
        { url: '/local/delivery/multiaction.php?orgid=900001&selecteddeliveries[]={id}&action=4', type: 'other', label: 'Als zugestellt markieren', id: '', disabled: false, icon: '' },
        { url: '/local/delivery/multiaction.php?orgid=900001&selecteddeliveries[]={id}&action=0', type: 'other', label: 'Als abgebrochen markieren', id: '', disabled: false, icon: '' },
        { url: '/local/delivery/multiaction.php?orgid=900001&selecteddeliveries[]={id}&action=6', type: 'other', label: 'Zustellung/Dokument widerrufen', id: '', disabled: false, icon: '' },
      ],
      row_actions_display_as_menu: true,
      enable_global_filter: true,
      row_actions_callback: '(function({ row, row_actions }){\n                  row_actions[0].disabled = true;\n                  return row_actions;\n                })',
    });
  }
}

// If you want to start measuring performance in your app, pass a function
// to log results (for example: reportWebVitals(console.log))
// or send to an analytics endpoint. Learn more: https://bit.ly/CRA-vitals
// if (process.env.NODE_ENV === 'development') {
//   reportWebVitals(console.log);
// } else {
//   reportWebVitals();
// }
