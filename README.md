# local_table_sql Moodle Plugin
**Enhanced table_sql functionality with advanced features and a modern, responsive interface**

Moodle provides a `table_sql` class for building tables through a unified API. However, it lacks modern features such as AJAX-based pagination and filtering.

`local_table_sql` is a drop-in replacement for Moodle’s core `table_sql` class and adds a wide range of powerful enhancements, including AJAX navigation, full-text search and advanced filtering for each column.

Plugins can declare this plugin as a dependency in their `version.php`.
Tables built using `local_table_sql` provide the following features:

## Key Features

- **AJAX-driven data loading** – Load only the data that is needed, improving performance.
- **Built-in filtering and sorting** – Global search or column-specific filters included.
- **Full-text search support**
- **Dynamic column visibility** – Show or hide columns instantly.
- **Customizable UI actions** – Add action buttons or context menus for each row.
- **Row selection and bulk actions**
- **SQL-based data source** – Provide your own SQL query as input.
- **Custom form integration** – Display forms for adding or editing rows.


## Usage

See the demo folder for example usage of the `local_table_sql` plugin.

## Information for developers regarding the react-app directory

The `react-app` directory contains the React frontend responsible for rendering table data, built using **Material React Table**.

To build the React app (only needed if you made changes to the react code, a precompiled version is included in the plugin):

1. Run the script:
   ```bash
   cd react-table
   ./build.sh
   ```

2. The compiled output will be copied into the js/main.js directory.

The react-app directory is not required for normal plugin usage**—only for development**.
It is included in the repository for transparency and open-source collaboration.

## JavaScript Licenses

See: LICENSE.js.txt
