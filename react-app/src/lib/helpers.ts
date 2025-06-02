export async function fetchWithParams(url: URL, params: object) {
  // make a copy, pass url.href, for compatibility with older browsers
  url = new URL(url.href);
  url.searchParams.set('is_xhr', '1');

  for (const key in params) {
    if (params[key] === undefined || params[key] === null) {
      continue;
    }
    url.searchParams.set(key, params[key]);
  }

  const response = await fetch(url.href, {
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

/**
 * Escape HTML special characters.
 * @param {string} str
 * @returns {string}
 */
function escapeHTML(str) {
  return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

/**
 * Escape regular expression special characters.
 * @param {string} str
 * @returns {string}
 */
function escapeRegExp(str) {
  return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

export function highlightNodes(root, highlight) {
  const searchParts = highlight.trim().toLowerCase().split(/\s+/);
  const escapedStr = searchParts.map((part) => escapeRegExp(escapeHTML(part))).join('|');
  const regexp = new RegExp(`(${escapedStr})`, 'gi');

  const elements = [root, ...root.querySelectorAll('*')];
  elements.forEach((element) => {
    if (element.childNodes.length === 1 && element.childNodes[0].nodeType === Node.TEXT_NODE) {
      const modifiedText = element.textContent.replace(regexp, '<mark>$1</mark>');
      if (modifiedText != element.textContent) {
        element.innerHTML = modifiedText;
      }
    } else {
      element.childNodes.forEach((child) => {
        if (child.nodeType === Node.TEXT_NODE) {
          if (child.textContent.match(regexp)) {
            const modifiedText = child.textContent.replace(regexp, '<mark>$1</mark>');
            if (modifiedText != child.textContent) {
              // Create a span element to hold the new HTML content
              const span = document.createElement('span');
              span.innerHTML = modifiedText;

              // Replace the original text node with the new span element
              element.replaceChild(span, child);
            }
          }
        }
      });
    }
  });
}

export function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}
