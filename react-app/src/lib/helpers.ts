export async function fetchWithParams(params) {
  let fetchURL: URL;
  if (process.env.NODE_ENV === 'development') {
    // fetchURL = new URL('http://localhost/moodle41/local/eduportal/org/students.php?orgid=900001');
    // fetchURL = new URL('/moodle41/local/table_sql/demo/deliveries.php?orgid=900001', 'http://localhost/');
    fetchURL = new URL('/moodle41/local/table_sql/demo/index.php?orgid=900001', 'http://localhost/');

    // cors error
    // fetchURL = new URL('/moodle41/local/delivery/deliveries.php?orgid=900001', 'http://localhost/');

    // not working anymore
    // fetchURL = new URL('/moodle41/local/eduportal/demo/user_selector_table_sql.php?orgids=900027,900001,900023', 'http://localhost/');
  } else {
    fetchURL = new URL(document.location.href);
  }

  fetchURL.searchParams.set('is_xhr', '1');

  for (const key in params) {
    if (params[key] === undefined || params[key] === null) {
      continue;
    }
    fetchURL.searchParams.set(key, params[key]);
  }

  const response = await fetch(fetchURL.href, {
    credentials: 'include',
  });
  return response.json();
}

export function stringToFunction(str) {
  if (typeof str == 'function') {
    return str;
  }

  if (!str || !String(str).trim()) {
    return null;
  }

  return eval(`(${str})`);
}

export function replacePlaceholders(template, obj) {
  return template.replace(/\{([^{}]+)\}/g, (match, key) => {
    const value = obj[key];
    return value !== undefined ? value : match;
  });
}

export function convertSnakeCaseToCamelCase(obj) {
  const camelCasedObj = {};
  for (const key in obj) {
    if (obj.hasOwnProperty(key)) {
      const camelCasedKey = key.replace(/-([a-z])/g, function (match, letter) {
        return letter.toUpperCase();
      });
      camelCasedObj[camelCasedKey] = obj[key];
    }
  }
  return camelCasedObj;
}

// https://dev.to/uttarasriya/js-polyfill-part-4-debounce-throttle-leading-trailing-options-3nn8
export function debounce(func, delay, option = { leading: false, trailing: true }) {
  let timer; // same like basic debounce
  let trailingArgs; // as we require last arguments for trailing

  if (!option.leading && !option.trailing) return () => null; //if both false, return null

  return function debounced(...args) {
    //returns a debounced function

    if (!timer && option.leading) {
      // timer done but leading true
      func.apply(null, args); //call func
    } else {
      trailingArgs = args; // arguments will be the last args
    }

    clearTimeout(timer); //clear timer for avoiding multiple timer instances

    timer = setTimeout(() => {
      if (option.trailing && trailingArgs) func.apply(null, trailingArgs); // trailingArgs is present and trailing is true

      trailingArgs = null; //reset last arguments
      timer = null; // reset timer
    }, delay);
  };
}
