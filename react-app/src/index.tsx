import React from 'react';
import ReactDOM from 'react-dom/client';
import './index.css';
import App from './App';
import reportWebVitals from './reportWebVitals';
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
dayjs.extend(utc)
dayjs.extend(timezone)

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
  if (true) {
    (async () => {
      let result = await fetchWithParams({
        uniqueid: '',
        table_sql_action: 'get_config',
      });

      if (result && result.type == 'success') {
        table_sql_start({
          container: '#table-sql-dev',
          ...result.data,
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
