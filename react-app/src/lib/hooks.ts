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

    if (tableConfig.row_actions_callback) {
      tableConfig.row_actions_callback = stringToFunction(tableConfig.row_actions_callback);
    }

    return tableConfig;
  }, [config]);
};

export function useFetchWithParams() {
  const { uniqueid } = useAppConfig();

  return (params) => fetchWithParams({ uniqueid, ...params });
}
