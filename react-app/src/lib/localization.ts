import { MRT_Localization_DE } from 'material-react-table/locales/de';
import { useAppConfig } from './hooks';

export function useLocalization() {
  const config = useAppConfig();

  function getString(id, values: any = undefined) {
    if (process.env.NODE_ENV === 'development') {
      let str = id;
      if (values !== undefined) {
        str += ' ' + JSON.stringify(values);
      }

      return str;
    } else {
      // Production: use Moodle's string function
      // @ts-ignore
      return M.util.get_string(id, 'local_table_sql', values);
    }
  }

  if (config.current_language.match(/^de/)) {
    return {
      getString,
      // Localization object for Material React Table component
      mrtLocalization: MRT_Localization_DE,
    };
  } else {
    return {
      getString,
      // Localization object for Material React Table component
      mrtLocalization: undefined,
    };
  }
}
