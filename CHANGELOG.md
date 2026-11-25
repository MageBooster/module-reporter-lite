# Changelog

All notable changes to this package are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the package uses [Semantic Versioning](https://semver.org/).

## [1.0.1] - 2026-11-25

### Fixed

- The admin no longer stops with "Two 'add' commands cannot have equal id (MageBooster_Reports::magebooster)" when this module is installed together with another MageBooster module. The shared "MageBooster" menu item now comes from magebooster/module-license; this module only adds its own entries under it.

### Changed

- Requires magebooster/module-license ^1.3. The ACL parent of this module's resources is now `MageBooster_License::magebooster` (roles that had the old parent get the new one from the License data patch).

## [1.0.0] - 2026-11-21

### Added

- Admin page MageBooster > Reporter Lite > SQL reports: report list with search, library examples, SQL editor with automatic parameters, run preview (100 rows, row count, run time, bar chart).
- Downloads in CSV, JSON and HTML, plus Excel (with phpoffice/phpspreadsheet) and PDF (with dompdf/dompdf).
- New report page with six examples, "About this example" and help (how to write a query, where the data is, common mistakes).
- Schedules: daily, weekly, monthly or cron, in store time; date range for the scheduled run; recipients; attachment or 7-day download link; skip when empty; test send.
- Run history with files, download links with random tokens, cleanup cron.
- Read-only database user from `env.php` (`mbreporterlite/sql`, falling back to `mbreports/sql`), SELECT-only validator, time limit and row cap.
- Table prefix support for every table a query reads: comma lists, joins, aliases, backtick names, subqueries and WITH (CTE) bodies; CTE names are skipped.
- Console command `bin/magento mbreporterlite:run`.
- Licence check through MageBooster License (30-day grace period).
