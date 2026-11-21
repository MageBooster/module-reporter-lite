<!-- mb-top -->
# MageBooster Reporter Lite

MageBooster Reporter Lite: write read-only SQL reports in the Magento admin, run them, download CSV, Excel, JSON, HTML or PDF, and send them by e-mail on a schedule.

![Magento 2.4.4–2.4.9](https://img.shields.io/badge/Magento-2.4.4%E2%80%932.4.9-orange) ![PHP 8.0+](https://img.shields.io/badge/PHP-8.0%2B-777bb4) ![Admin only](https://img.shields.io/badge/area-admin%20only-0a7) ![Licence: Commercial](https://img.shields.io/badge/licence-Commercial-red)

```bash
composer require magebooster/module-reporter-lite
bin/magento setup:upgrade
```

Paid packages are served from the MageBooster Composer repository (`https://repo.magebooster.com`); your serial number is the password. See the guide for the full steps.

- Guide: [English](docs/HOWTO.en.md) · [Türkçe](docs/HOWTO.tr.md)
- Changes: [CHANGELOG.md](CHANGELOG.md)
- Buy a licence: https://magebooster.com
- Support: info@magebooster.com

---

<!-- /mb-top -->
## What it does

- **One admin page** (MageBooster > Reporter Lite > SQL reports): your reports on the left, the report on the right with the tabs Query, Schedule and History.
- **SQL editor** with highlighting. Parameters you write as `:name` get their own field automatically: date parameters (`:from`, `:to`, `:start_date` ...) get presets such as "Last 30 days", and `:store` gets a store view list.
- **Run** shows the first 100 rows, the row count, the run time and, where it fits, a bar chart.
- **Downloads:** CSV, JSON and HTML always; Excel when `phpoffice/phpspreadsheet` is installed; PDF when `dompdf/dompdf` is installed.
- **Library:** six ready examples (sales by day, best sellers, customers without orders, low stock, abandoned carts, coupon usage) with a short explanation, plus help on the right: how to write a query, where the data is, common mistakes.
- **Schedule:** daily, weekly, monthly or a cron expression in store time; the date range the scheduled run covers; recipients; file type; the file attached or as a download link valid for 7 days; "don't send when there are no rows"; "send a test to me".
- **History:** every run with its result and its file while the file is kept.

## Safety

- Queries run only as a separate **read-only database user** (set in `app/etc/env.php`, never in the admin). The page header shows whether that user is connected, and warns if it can write.
- Only one `SELECT` or `WITH` statement is accepted. No `;` chaining, `INTO OUTFILE`, `LOAD_FILE`, `SLEEP`, `BENCHMARK`, variables, locking reads or system schemas.
- Each run has a time limit (MariaDB `max_statement_time` / MySQL `MAX_EXECUTION_TIME`) and a row cap, in a read-only transaction.
- Admin permissions (ACL) for viewing, editing and scheduling; form keys on every request; escaped output; spreadsheet formula guard in CSV and Excel.
- Download links use a random token (only its hash is stored), expire after 7 days, and the files live in `var/mbreporterlite` and are removed by a cleanup cron.

## Table prefixes

Write table names without the prefix. When the store uses a table prefix, Reporter Lite adds it to every table the query reads: comma lists (`FROM a, b`), every `JOIN`, aliases with or without `AS`, backtick names, subqueries and the bodies of `WITH` blocks. CTE names, names with a database (`db.table`) and names that already have the prefix are left alone.

## Hyvä

Reporter Lite works only in the Magento admin and adds nothing to the storefront, so there is **no separate `-hyva` package**. The same package works on Luma and Hyvä stores.

## Credits

Inspired by Kodhub Reporter (MIT). Reporter Lite is a new implementation; no code was copied.

## Licence & copying

Commercial licence, see `LICENSE.txt`. Copyright (c) 2026 WebWorks 101 OÜ (MageBooster). All rights reserved.

- One serial number = one production installation (one store domain). Staging and development copies of that same store are allowed.
- Each serial number and each download is tied to the customer who bought it.
- You may modify the code for your own licensed store. Keep the copyright and licence notices.
- Not allowed: copying to other stores, redistributing, reselling, sublicensing, publishing in public repositories, marketplaces or forums, removing licence checks or these notices, sharing serial numbers.
- In case of unauthorised copying or use, the serial number is revoked without refund, updates and support stop, and WebWorks 101 OÜ may claim damages and take legal action. Estonian law applies; the court is Harju County Court.
