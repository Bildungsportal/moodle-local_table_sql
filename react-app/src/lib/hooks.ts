import { useContext, useMemo } from 'react';
import { fetchWithParams, stringToFunction } from './helpers';
import { AppContext } from './context';

export const useAppContext = () => useContext(AppContext);

export const useAppConfig = () => {
  const { config } = useAppContext();

  return useMemo(() => {
    if (!config) {
      return {};
    }
    const tableConfig = { ...config };

    // set index
    tableConfig.columns.forEach((column, index) => (column.index = index));

    if (tableConfig.row_actions_js_callback) {
      tableConfig.row_actions_js_callback = stringToFunction(tableConfig.row_actions_js_callback);
    }
    if (tableConfig.render_detail_panel_js_callback) {
      tableConfig.render_detail_panel_js_callback = stringToFunction(tableConfig.render_detail_panel_js_callback);
    }

    return tableConfig;
  }, [config]);
};

export function useFetchWithParams() {
  const { uniqueid, url } = useAppConfig();

  return (params) => fetchWithParams(url, { uniqueid, ...params });
}
