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
      // @ts-ignore
      return M.util.get_string(id, 'local_table_sql', values);
    }
    // let str = config.language_strings?.[id];
    // if (str) {
    //   if (values !== undefined) {
    //     str = str.replace('{$a}', values);
    //   }
    // } else {
    //   str = id;
    //   if (values !== undefined) {
    //     str += ' ' + JSON.stringify(values);
    //   }
    // }
    // return str;
  }

  if (config.current_language.match(/^de/)) {
    return {
      getString,
      reactTable: MRT_Localization_DE,
    };
  } else {
    return {
      getString,
      reactTable: undefined,
    };
  }
}
