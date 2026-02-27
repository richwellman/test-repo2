# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

REDCap v15.5.36 is a secure PHP web application for building and managing online surveys and research databases, developed by Vanderbilt University. This is a licensed consortium project.

## Running Tests

Unit tests require a development server (enforced by the bootstrap):

```bash
# Run all unit tests
UnitTests/vendor/bin/phpunit --configuration phpunit.xml

# Run a single test file
UnitTests/vendor/bin/phpunit --configuration phpunit.xml UnitTests/SomeTest.php

# Run a specific test method
UnitTests/vendor/bin/phpunit --configuration phpunit.xml --filter testMethodName UnitTests/SomeTest.php
```

Tests are located in `UnitTests/` and require their own `vendor/` (managed separately via `UnitTests/composer.json`). The database connection for tests can be set via environment variables: `MYSQL_REDCAP_CI_HOSTNAME`, `MYSQL_REDCAP_CI_USERNAME`, `MYSQL_REDCAP_CI_PASSWORD`, `MYSQL_REDCAP_CI_DB`.

## Building Frontend Assets

Frontend assets are built from `Resources/webpack/`:

```bash
cd Resources/webpack
yarn install       # Install dependencies
yarn build         # Production build
yarn dev           # Development build with watch
```

Output goes to `Resources/webpack/js/` and `Resources/webpack/css/` (compiled as `bundle.js` / `bundle.css`). Vue components in `Resources/js/vue/components/` have their own build (Vite-based, see `vite.config.js` in that directory).

## Architecture

### Request Lifecycle

1. Entry points are feature-specific PHP files (e.g., `DataEntry/index.php`, `Surveys/index.php`) or the root `index.php`.
2. Each entry point includes either `Config/init_project.php` (for project-scoped pages, requires `?pid=`) or `Config/init_global.php` (for system-level pages).
3. `Config/init_functions.php` bootstraps the system by calling `System::init()`, which connects to the database via `database.php` (located one directory above the webroot, not committed).
4. After initialization, a `Route` object (`Classes/Route.php`) is instantiated to dispatch `?route=ClassName:methodName` requests to the appropriate controller method.

### Key Classes

- **`System`** (`Classes/System.php`) — Core bootstrap, DB connection, global config constants, `db_query()` wrappers.
- **`REDCap`** (`Classes/REDCap.php`) — Public API for plugins and external modules: `getData()`, `saveData()`, `getDataTable()`, etc.
- **`RCView`** (`Classes/RCView.php`) — HTML generation utilities. Use `RCView::tt($lang_var)` for text output and `RCView::tt_i($lang_var, $values)` for interpolated strings.
- **`Project`** (`Classes/Project.php`) — Loads and holds all project metadata (arms, events, fields, forms).
- **`Records`** (`Classes/Records.php`) — Record-level data operations.
- **`Controller`** (`Classes/Controller.php`) — Abstract base for all controllers; provides `render($viewName, $params)`.
- **`Hooks`** (`Classes/Hooks.php`) — REDCap hook system. Call hooks via `Hooks::call('redcap_hook_name', $params)`.
- **`Route`** (`Classes/Route.php`) — Allowlist-based routing from `?route=` query param to controller methods.

### Directory Structure

| Path | Purpose |
|------|---------|
| `Classes/` | Core PHP classes (PSR-4 autoloaded under `Vanderbilt\REDCap\Classes\`) |
| `Controllers/` | HTTP controllers extending `Controller`; handle routes like `ClassName:methodName` |
| `Config/` | Bootstrap files (`init_functions.php`, `init_global.php`, `init_project.php`) |
| `Resources/js/` | Per-feature JavaScript files and Vue components |
| `Resources/js/modules/` | Shared JS modules (FetchClient, Store, Pagination, etc.) |
| `Resources/js/vue/components/` | Vite-built Vue 3 component library |
| `Resources/js/Composables/` | Vue composables (AutoComplete, Toast, Modal, EventBus, Fetch) |
| `Resources/webpack/` | Webpack bundle entry point (`src/app.js` → `bundle.js`/`bundle.css`) |
| `Resources/css/` | Global stylesheets |
| `Resources/sql/` | SQL install/upgrade scripts |
| `LanguageUpdater/English.ini` | All UI text strings (language keys) |
| `UnitTests/` | PHPUnit test suite with its own `vendor/` |
| `ExternalModules/` | External Module framework |
| `Libraries/` | Third-party PHP libraries |

### Database Conventions

- Use `db_query($sql, $params)` for parameterized queries (aliases `mysqli_*` functions).
- Never hard-code `redcap_data` as the data table name. Use `REDCap::getDataTable($project_id)` to get the correct sharded table.
- Queries to `redcap_data*` tables must include a `project_id` or `event_id` limit — enforced at the System level.

### Language / i18n

- All UI text lives in `LanguageUpdater/English.ini` as named keys.
- In PHP: `RCView::tt('key_name')` to output text; `RCView::tt_i('key_name', [$val0, $val1])` for placeholder substitution (`{0}`, `{1}`, ...).
- In JavaScript: call `addLangToJS(['key_name'])` in PHP to expose strings as `lang.key_name` in the browser.
- Do not use the older `$lang['key']` pattern directly in new code.

### Controller Pattern

Controllers in `Controllers/` extend the abstract `Controller` class. They are invoked via `?route=ControllerName:methodName`. Non-project routes must be added to the allowlist in `Classes/Route.php`. Controllers render views via `$this->render('ViewFile.php', $params)`, where views are resolved from `APP_PATH_VIEWS`.

### Frontend Libraries (Bundled)

Bootstrap 5, DataTables, Font Awesome, SweetAlert2, Select2, Vue.js, jQuery, Tippy.js, TinyMCE, Moment.js.

### ORM

`Classes/ORM/` provides a Doctrine-based ORM layer via `EntityManagerBuilder`. Use the fluent builder to create `EntityManager` instances when needed for complex data operations.

### External Modules

The External Module framework lives in `ExternalModules/`. Modules extend REDCap via hooks, AJAX endpoints, and cron jobs. See `ExternalModules/docs/` for the full developer guide.
