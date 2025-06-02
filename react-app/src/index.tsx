import React from 'react';
import ReactDOM from 'react-dom/client';
import './index.css';
import App from './App';
import { AppContext } from 'lib/context';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fetchWithParams } from 'lib/helpers';
import { AdapterDayjs } from '@mui/x-date-pickers/AdapterDayjs';
import { LocalizationProvider } from '@mui/x-date-pickers/LocalizationProvider';

import 'dayjs/locale/de'; // needed for german locale
import dayjs from 'dayjs';
import utc from 'dayjs/plugin/utc';
import timezone from 'dayjs/plugin/timezone';

// needed for .tz() function
dayjs.extend(utc);
dayjs.extend(timezone);

const queryClient = new QueryClient();

function table_sql_start(config) {
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

  // make the url for xhr calls
  if (!(config.url instanceof URL)) {
    if (config.url) {
      config.url = new URL(config.url, document.location.href);
    } else {
      config.url = null; // dynamic url (eg. url can be changed via javascript pushState, and then reload the table dynamically)
    }
  }

  let tableElement = containerElement.querySelector('.table-sql-container') || container;

  const root = ReactDOM.createRoot(tableElement as HTMLElement);
  root.render(
    <React.StrictMode>
      <AppContext.Provider value={{ config }}>
        <QueryClientProvider client={queryClient}>
          <LocalizationProvider dateAdapter={AdapterDayjs} adapterLocale={config.current_language}>
            <App />
          </LocalizationProvider>
        </QueryClientProvider>
      </AppContext.Provider>
    </React.StrictMode>
  );
}

(window as any).table_sql_start = table_sql_start;
(window as any).table_sql_reload = function (uniqueid) {
  document.dispatchEvent(new CustomEvent('local_table_sql:reload'));
};

if (process.env.NODE_ENV == 'development') {
  // const url = new URL('http://localhost/moodle/local/eduportal/org/students.php?orgid=900001');
  // const url = new URL('/moodle/local/table_sql/demo/deliveries.php?orgid=900001', 'http://localhost/');
  const url = new URL('/moodle/local/table_sql/demo/index.php?orgid=900001', 'http://localhost/');
  // const url = new URL('/moodle/local/leseguetesiegel/jury/submissions.php', 'http://localhost/');
  // const url = new URL('/moodle/blocks/edupublisher/pages/etapas.php', 'http://localhost/');

  // cors error
  // const url = new URL('/moodle/local/delivery/deliveries.php?orgid=900001', 'http://localhost/');

  // not working anymore
  // const url = new URL('/moodle/local/eduportal/demo/user_selector_table_sql.php?orgids=900027,900001,900023', 'http://localhost/');

  if (true) {
    (async () => {
      let result = await fetchWithParams(url, {
        uniqueid: '',
        table_sql_action: 'get_config',
      });

      if (result && result.type == 'success') {
        table_sql_start({
          container: '#table-sql-dev',
          ...result.data,
          url: result.data.url || url,
        });
        return result.data;
      } else {
        throw result;
      }
    })();
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
