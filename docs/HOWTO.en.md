# MageBooster Reporter Lite: how-to

Reporter Lite lets you write SQL reports in the Magento admin, run them, download the result and send it by e-mail on a schedule. Queries run as a separate read-only database user, so a report can never change store data.

## What it does

- **SQL reports page:** go to **MageBooster > Reporter Lite > SQL reports**. Your reports are on the left; the open report is on the right with the tabs **Query**, **Schedule** and **History**. The four cards at the top show the last run, the next run, the number of recipients and the last result.
- **Query:** write the query in the editor and press **Run** (or Ctrl+Enter). You see the first 100 rows, the row count, the run time and a bar chart when the first column is a label and another column is a number. **Format** puts the main clauses on their own lines.
- **Parameters:** write `:name` in the query and a field for it appears under the editor.
  - Names like `:from`, `:to`, `:date`, `:start_date`, `:end_date` are dates and get presets (Today, Yesterday, Last 7 days, Start of this month ...) or a date you pick. A start date means the start of that day (00:00 store time) and an end date the end of that day (23:59:59), both converted to UTC, the way Magento stores dates.
  - `:store` (or `:store_id`) gets a list of store views.
  - Any other name is a text field.
- **Downloads:** CSV, JSON and HTML always; **Excel** when `phpoffice/phpspreadsheet` is installed; **PDF** when `dompdf/dompdf` is installed. A download contains every row up to the row cap, not just the 100 shown.
- **New report:** opens an empty editor. Pick one of the examples (Sales by day, Best sellers, Customers with no orders, Low stock, Abandoned carts, Coupon usage) to fill the editor; the **About this example** box says what it returns and how to change it. The right column explains how to write a query, which tables hold which data and the most common mistakes. If you leave with unsaved changes, the page asks first.
- **Duplicate** makes an unsaved copy of the open report.
- **Schedule:** turn **On**, then choose Daily, Weekly, Monthly or a cron expression, the day and the time (store time zone), and what period the data covers (for example "The previous week"). The **Next send** line shows the next date and the period it will cover. Add recipients, choose the file type and whether to attach the file or send a download link (valid 7 days). Tick **Don't send when there are no rows** to skip empty results. **Send a test to me** sends the report to your own admin e-mail now.
- **History:** every scheduled and test run with its result, row count and file. Files can be downloaded while they are kept.

## Requirements

- PHP 8.0 or later
- Magento 2.4.4 to 2.4.9 (tested on 2.4.8-p5 with PHP 8.3 and 2.4.9 with PHP 8.4)
- MySQL 5.7+ / 8 or MariaDB 10.3+
- `magebooster/module-license` 1.1 or later (installed automatically by Composer)
- Optional, for Excel export: `composer require phpoffice/phpspreadsheet`
- Optional, for PDF export: `composer require dompdf/dompdf`

Reporter Lite works only in the admin. There is no separate Hyvä package; the same package works on Luma and Hyvä stores.

## Install with Composer (paid)

1. Add the repository:

   ```bash
   composer config repositories.magebooster composer https://repo.magebooster.com
   ```

2. Add your access details to `auth.json` in the Magento root. Use the username from your order e-mail, and your serial number as the password:

   ```json
   {
     "http-basic": {
       "repo.magebooster.com": {
         "username": "<username from your order e-mail>",
         "password": "<your serial number>"
       }
     }
   }
   ```

3. Install the package:

   ```bash
   composer require magebooster/module-reporter-lite
   ```

Keep `auth.json` out of version control.

## Install from a zip file

1. Unzip the License module into `app/code/MageBooster/License`.
2. Unzip this module into `app/code/MageBooster/ReporterLite`.
3. Run the commands below.

## Enable the module

```bash
bin/magento module:enable MageBooster_License MageBooster_ReporterLite
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
bin/magento cache:flush
```

Magento cron must be running for these jobs:

- `mbreporterlite_dispatch`, every minute: sends the scheduled reports that are due
- `mbreporterlite_cleanup`, daily at 03:17: deletes expired files and old history

## Create the read-only database user

Reporter Lite never uses the main Magento database user. Create a user that can only read, and put its details in `app/etc/env.php`.

1. Create the user and give it `SELECT` only. Replace `magento` with your database name:

   ```sql
   CREATE USER 'mb_reports'@'localhost' IDENTIFIED BY '<strong password>';
   GRANT SELECT ON magento.* TO 'mb_reports'@'localhost';
   FLUSH PRIVILEGES;
   ```

   If the database runs on another host, use that host instead of `localhost` (for example `'mb_reports'@'10.0.0.%'`).

   To keep some data out of reach, grant only the tables you want instead of `magento.*`, one line per table (`GRANT SELECT ON magento.sales_order TO ...`). For tables with secrets, such as `customer_entity` (password hashes) or `admin_user`, either leave them out or grant only some columns: `GRANT SELECT (entity_id, email, firstname, lastname, created_at, group_id, store_id) ON magento.customer_entity TO 'mb_reports'@'localhost';`

2. Add the user to `app/etc/env.php`, at the top level of the array:

   ```php
   'mbreporterlite' => [
       'sql' => [
           'username' => 'mb_reports',
           'password' => '<strong password>',
           // optional; by default the host and dbname of the main connection are used:
           // 'host' => 'localhost',
           // 'dbname' => 'magento',
       ],
   ],
   ```

   If the MageBooster Reports module is installed and already has a read-only user under `mbreports/sql`, Reporter Lite uses that one when `mbreporterlite/sql` is missing.

3. Run `bin/magento cache:flush`.

The page header shows the state: **Read-only database user · connected** (green), a warning when the user can change data, or a message when it cannot connect or is missing. The user name must differ from the main Magento user; otherwise queries are refused.

## Rules for queries

- One `SELECT` or `WITH ... SELECT` statement. A trailing `;` is fine; a second statement is refused.
- Not allowed: `INSERT`, `UPDATE`, `DELETE` and other writing or admin statements, `INTO OUTFILE` / `DUMPFILE`, `LOAD_FILE`, `SLEEP`, `BENCHMARK`, lock functions, variables (`@x`, `:=`), version comments (`/*! */`), `FOR UPDATE` / `LOCK IN SHARE MODE`, `information_schema`, `performance_schema`, `mysql` and `sys`.
- Use named parameters (`:from`), not `?`.
- Write table names without the table prefix; the prefix is added for you after `FROM` and `JOIN`. In a comma list (`FROM a, b`) only the first table gets the prefix, so use `JOIN`.
- Each run stops after the time limit (default 10 seconds) and reads at most the row cap (default 5,000 rows). The count shows "more than 5000" when the cap is reached.
- Magento stores dates in UTC. Date parameters are converted for you; to show a date in store time use `CONVERT_TZ(created_at, '+00:00', '+01:00')` with your offset.

## Admin configuration

Go to **Stores > Configuration > MageBooster > Reporter Lite**:

- **E-mail:** Send from (store e-mail identity); Largest attachment (MB), default 10. A larger file is sent as a download link.
- **Queries:** Most rows per run (default 5,000, up to 100,000); Longest run (seconds, default 10, up to 120).
- **Files and history:** Download links work for (days), default 7; Keep run history for (days), default 90.

### Permissions (ACL)

Under **System > Permissions > User Roles > Role Resources > MageBooster > Reporter Lite**:

- **Open and run saved reports, download results:** the page, Run and downloads of saved reports.
- **Write, save and delete report queries:** the editor, New report, Duplicate, Save and Delete. Without it a user can only run saved queries as they are.
- **Schedule reports and send them by e-mail:** the Schedule tab and Send a test.
- The settings page has its own resource under **Stores > Configuration**.

## For developers

### Command line

```bash
bin/magento mbreporterlite:run                      # list reports
bin/magento mbreporterlite:run 3                    # run report 3 now and send it to its recipients
bin/magento mbreporterlite:run 3 --test-to=me@example.com
bin/magento mbreporterlite:run 3 --test-to=me@example.com --eml=var/mail-test   # write the e-mail as .eml, send nothing
bin/magento mbreporterlite:run --cleanup            # delete expired files and old history
```

### Database tables

- `mbreporterlite_report`: reports, parameters and schedules
- `mbreporterlite_run`: run history, file names, hashed download tokens and expiry dates

Files are kept in `var/mbreporterlite`. The public download address is `https://<store>/mbreporterlite/file/get?t=<token>`; it is not indexed and stops working when the link expires or the file is cleaned up.

## Troubleshooting

- **"Reporter Lite is not set up yet".** Add the `mbreporterlite/sql` block to `env.php` and flush the cache.
- **"Could not connect as the read-only report user".** Check the user name, the password and the host in the grant (`'mb_reports'@'localhost'` vs `'%'`).
- **"SELECT command denied" for a table.** The read-only user has no grant on that table. Add a grant, or remove the table from the query.
- **The header warns that the user can change data.** Revoke everything and grant `SELECT` only: `REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'mb_reports'@'localhost'; GRANT SELECT ON magento.* TO 'mb_reports'@'localhost';`
- **Scheduled reports do not arrive.** Check that cron runs, the **Send from** identity and the store's mail settings (Stores > Configuration > Advanced > System > Mail Sending Settings). The History tab shows the reason of each failed run.
- **No Excel or PDF button.** Install `phpoffice/phpspreadsheet` or `dompdf/dompdf`.
- **A query times out.** Narrow the date range, add a `WHERE` on an indexed column (`created_at`, `entity_id`, `store_id`) or raise **Longest run**.

## Uninstall

```bash
bin/magento module:disable MageBooster_ReporterLite
composer remove magebooster/module-reporter-lite
bin/magento setup:upgrade
bin/magento cache:flush
```

Then drop the tables `mbreporterlite_report` and `mbreporterlite_run` if you no longer need them, delete `var/mbreporterlite`, drop the read-only database user and remove the `mbreporterlite` block from `env.php`.

## Licence check (serial number)

- Enter the serial in **Stores > Configuration > MageBooster > Licenses**, field **MageBooster Reporter Lite**. The serial starts with `MB1`.
- The **License status** table on that page shows the module as **Active**, **Grace period** or **Switched off**. `bin/magento magebooster:license:status` shows the same.
- Without a valid serial (none entered, not valid, expired, another domain or another product) the module keeps working for **30 days**. The 30 days start the first time the store sees the module without a valid serial. A valid serial ends the grace period.
- During and after the grace period, every admin page shows a notice that cannot be closed, with the days left or the date the module was switched off.
- Shoppers never see a licence message, and the check runs inside your store: nothing is sent to MageBooster.
- Development hosts (`localhost`, `127.0.0.1`, `*.localhost`, `*.test`, `*.local`) always pass the domain check.
- **After the 30 days:** the SQL reports page opens the Licenses page with a "license required" message, scheduled reports are not sent and download links stop working. Reports and history are kept and come back with a valid serial.

## Credits

Inspired by Kodhub Reporter (MIT). Reporter Lite is a new implementation.

## Licence & copying

Commercial licence, see `LICENSE.txt`. Copyright (c) 2026 WebWorks 101 OÜ (MageBooster). All rights reserved. See the README for the full terms.

## Support

info@magebooster.com
