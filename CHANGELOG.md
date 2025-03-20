# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased]

## [1.9.14 beta] - 2025-03-20

### Fixed
- A bug when the screens' overview could partially go off the screen after dragging panels between the screens.
- Non-standard ports defined via environmental variables (`SQLANTERN_PORT_{port}`) did not actually work, only constants from `config.sys.php` worked. They do work now.

### Added
- Profiler now shows average and median values for each query.
- BLOB/BINARY columns display the size of the data now for both drivers (MariaDB/MySQL and PostgreSQL).
- MariaDB/MySQL: BLOB/BINARY can usually be downloaded now (when there is a unique column in the query results).
- Single-file version's front-side can now be configured via environment variables. The settings must be prefixed with `SQLANTERN_JS_` and be uppercase. E.g. `SQLANTERN_JS_DISPLAY_EXPORT`, `SQLANTERN_JS_EXPORT_BREAK_ROWS`. (The single-file version still doesn't and isn't going to read `js/config.sys.js`.)

### Changed
- Keep-alive logic was fully redesigned and the toggleable icon removed. Keep-alive background requests are now automatic and always enabled.

### Removed
- The global "Minimize all panels" button was removed. I personally haven't used it in a long while anymore, and I think it's very confusing to the users (and not of much help). If anybody needs it back, email me at sqlantern@sqlanern.com please.

## [1.9.13 beta] - 2025-01-07

### Fixed
- MariaDB/MySQL: Fixed an error in the databases' list, caused by a combination of PHP 8 with enabled databases' sizes display and empty database(s) in the list.
- MariaDB/MySQL: Greatly improved the speed of listing tables for remote databases which are far away (tables' panels opening speed).
- MariaDB/MySQL: The tables panels now always list the tables alphabetically (I had relied on `SHOW TABLES` previously, which as I found out is not reliable in this regard).

### Added
- Using custom ports is now very trivial and can also be done in the single-file version: it is now enough to set a server-side setting for each port, like `SQLANTERN_PORT_33306`, `SQLANTERN_PORT_55432`, etc. Single-file version is now configurable via environment variables.
- A new function: Backup and restore SQLantern's LocalStorage, located in the "Sessions" menu (the new "Backup" tab). It is designed to backup everything stored in the domain's LocalStorage (Sessions, Saved Queries, Notepad) to restore it after global cookie clearing (which also clears LocalStorage globally), but it can also be used to copy everything from one SQLantern instance to another. Saving and restoring to/from client's local storage is always available, but saving/restoring to/from the server storage is available if the client has a valid database connection _and_ the `SQLANTERN_SERVER_SIDE_BACKUPS_ENABLED` server-side setting is set to `true`. Server-side backups are _encrypted_ by the backup password you provide when saving, but as usual - short and simple passwords will make the data trivial to decrypt, so be careful.
- Number of rows on page is now selectable in GUI (per panel). The options of 30, 50 or 100 rows per page are given for now.
- New GUI settings: "Display database sizes" and "Auto size units". Both were server-side settings before (`SQL_DISPLAY_DATABASE_SIZES` and `SQL_SIZES_FLEXIBLE_UNITS`).
- New GUI setting: "Start with open Export/Import". Makes Export/Import options displayed in the panel with the list of tables on a database has when it is first opened (but not the subsequent times).
- New server-side parameters: `SQLANTERN_SHOW_CONNECTION_ERROR` (display a real connection error), `SQLANTERN_USE_SSL` (use SSL), `SQLANTERN_TRUST_SSL` (don't validate SSL). All three currently only work in MariaDB/MySQL driver.
- New server-side parameter: `SQLANTERN_EXPORT_DB_DATE_SUFFIX` - a format for the date and time of export, which is added to the name of the file when exporting a database.
- A new `docs` directory and a new `sqlantern-how-to.md` document containing how-tos.

### Changed
- Server-side settings are now read _not only_ from the `config.sys.php` file, _but also from environment variables_ - which is especially important for the single-file version, **which is now fully configurable**.
- Exports now get date and time appended to the file names (server time).
- All the server-side settings start with `SQLANTERN_` now (vs `SQL_` previously). Settings from your `config.sys.php` with old prefixes will still work, there is no backward compatibility break.
- `README_profiler` documents are moved to the new `docs` directory and renamed to `sqlantern-profiler`.
- Clarified the problem with import in the documentation - additional debugging helped me understand that the problem is actually all about the servers running out of memory.

### Removed
- Server-side setting `SQL_PORTS_TO_DRIVERS` is now obsolete and removed - replaced by `SQLANTERN_PORT_{port}` settings. There is no backward compatibility break: if you have `SQL_PORTS_TO_DRIVERS` configured in your `config.sys.php`, it'll still work, but the setting is not listed and not documented anymore.
- Server-side setting `SQL_ROWS_PER_PAGE` is now obsolete and removed - replaced by a drop-down select in the front-side.
- Server-side settings `SQL_DISPLAY_DATABASE_SIZES` and `SQL_SIZES_FLEXIBLE_UNITS` are now obsolete and removed - replaced by the front-side GUI settings.

## [1.9.12 beta] - 2024-05-16

### Fixed
- Views are now listed after tables in exported dumps, preventing the "Table '...' doesn't exist" error in cases when views alphabetically go before the tables they reference (e.g. a view named "a" referencing a table named "d" caused that error, because "d" has not been created yet when "a" CREATE statement was run).
- A bug which prevented deleting "handy queries" in Settings (they could be edited and reordered, but not deleted).
- A bug in profiler after clicking "Stop" and "Run" in quick succession (double-clicking "Stop" basically). Profiler created a new thread in that case while keeping the old thread alive, running profiling in two threads and displaying confusing results. (Profiler's front-end was actually fully rewritten.)
- OpenCart extension: Servers with non-UTC PHP and/or DB time zones were always causing "ACCESS DENIED" under certain conditions no matter what (a combination of time zone and session duration). OpenCart's internal config value is used now to sync PHP with DB and work in the same time zone (in the same way OpenCart itself handles it).

### Added
- It is now possible to refresh the list of databases, list of tables and a table's structure and indexes - with the new "Refresh" icon (at the top of panels).
- A new query-only panel type, opened by a new "plus" icon. The new panel type is the same as a table panel, only lacking Structure and Indexes (and it's not linked to any table in the database). Unlike table panel, it opens without any initial pre-filled query.
- Databases' comments and tables' comments are now displayed if present. (Table fields' comments are not displayed yet.)

### Changed
- Profiler was visually redesigned to make it clearer when the timeout between the queries happens.

## [1.9.11 beta] - 2024-03-05

### Fixed
- A critical bug in duplicated panels and panels restored from a saved session - they sometimes had a different query applied when using automatic pagination than the query in the Query box.

### Added
- Foreign keys are now listed (in "Indexes", in both MariaDB/MySQL and PostgreSQL).

## [1.9.10 beta] - 2024-02-19

### Fixed
- PostgreSQL: `REFRESH MATERIALIZED VIEW` caused a fatal error in Profiler (and possibly other queries). Those queries correctly fall back to the non-precise measurement now.

### Added
- PostgreSQL: Materialized Views are now listed in the tables/views panels.

## [1.9.9 beta] - 2024-02-06

### Fixed
- Deleting a query from query history occasionally deleted a wrong query (not the clicked one).
- Multi-line-style comment/comments at the start of the query resulted in a fatal PHP error in PHP 8.1 and later (one-line comments were fine). In PHP versions below 8.1 another glitch happened with such queries (only if they were `SELECT`s): the total number of rows was not returned and auto-pagination did not appear. Both drivers were affected.
- Columns labeled as `KEY` index in "Structure" were not always single-column indexes (sometimes `MUL` should have been displayed instead). Both drivers were affected.
- OpenCart extension: Refreshing the SQLantern page resulted in ACCESS DENIED. You need to clear all cookies on your domain if you still encounter this bug or if you now always get ACCESS DENIED after this update (you might have an old conflicting cookie in your browser after the update).

## [1.9.8 beta] - 2024-01-29

### Fixed
- Greatly reduced the chances of "Out of memory" browser tab crashes after receiving too much data from the server (hopefully, almost completely eliminated).
- Reduced the chances of occasional silent session save/auto-save failures without visible errors (when browser could display the data, but the session cannot be saved and auto-saved with that data because of the 5MB-per-SessionStorage-value limitation). A quick fix is provided for now, there will be a better fix in the future.
- Fixed undesireable behaviour: if a query was run repeatedly with a timer, clicking "Stop" was executing the query once again (before actually stopping), sometimes changing the results.
- Single-file version did not contain LICENSE file (LICENSE link in the About text led nowhere in the best case scenario).
- Small JavaScript fixes.

### Added
- MariaDB/MySQL: New "Primary" column in "Indexes" (which wasn't there, unlike PostreSQL).

## [1.9.7 beta] - 2024-01-22

### Fixed
- PostgreSQL: Fixed major breakage on some databases caused by the tables' index improvement in the previous version. Tested much more thoroughly this time.
- PostgreSQL: Fixed occasionally wrong `Columns` in `Indexes`.

### Added
- The index columns' separator is now configurable (`SQL_INDEX_COLUMNS_CONCATENATOR`). Columns are combined with " + " by default, but users can set other values.

## [1.9.6 beta] - 2024-01-19

### Fixed
- Eliminated an extremely annoying major bug: if the last line of the query was commented out by `-- `, auto-pagination did not work and unlimited results were returned. At the same time pagination was displayed, creating an illusion that there was no problem. Both drivers were affected by this bug (MariaDB/MySQL and PostgreSQL).
- `index.php` is added specifically to the back side URL, for the servers which know and execute PHP, but don't consider `index.php` a default index file and other related situations.
- PostgreSQL: Connection to non-standard ports is now supported (is was accidentally not supported before).
- PostgreSQL: Eliminated a confusing and non-working pagination for queries with `LIMIT`.
- Joomla plugin: Fixed a critical bug, which caused a global PHP error when trying to edit a menu item in Joomla 4 admin panel (caused in fact by unused dev-only code; Joomla 3 was not affected).
- A minor bug: "Cardinality" hasn't been formatted as a number, as intented.

### Changed
- Improved tables' indexes listing for both drivers (MariaDB/MySQL and PostgreSQL).

## [1.9.5 beta] - 2024-01-12

### Fixed
- Under some conditions there was an unhandled and uncontrolled browser-induced horizontal scroll after duplicating a panel. That wasn't working as it had been intended and it had worked properly some time before the public release (handled by our JS, not by the browser), but broken when the sessions were introduced. The horizontal scroll after panel duplication is now working as initially intended.
- PostgreSQL: Databases with multiple schemas and schemas outside `search_path` are now supported (only tables and views in `search_path` were accidentally supported before). If there are only tables/views in one schema in a database, and that schema is listed in `search_path`, tables are not prefixes with a schema. If there are multiple schemas OR one non-`search_path` schema, all tables get their schema added at the start.

## [1.9.4 beta] - 2024-01-03

### Fixed
- Sessions did not properly save and restore views in panels with tables' lists (views looked like tables after restore, not marked as views; they technically worked though).
- Export in databases without views works again (adding views support in the previous version broke Export almost completely).
- pgsql: Databases are listed alphabetically now (facepalm.jpg).
- Built-in support for spaces in tables' and views' names. Putting it into "Fixed", because they were bugged (listed, but couldn't be used by the built-in functions).

### Changed
- Connections are listed alphabetically now (instead of "natural" order of how they were added).

## [1.9.3 beta] - 2023-12-27

### Fixed
- Brought back PHP 5.6 compatibility. (Broken by deduplication-related changes in 1.9.2.)

## [1.9.2 beta] - 2023-12-27

### Fixed
- pgsql: Non-SELECT queries, which actually returned data, displayed "executed" instead of data. E.g. `SHOW search_path`.
- pgsql: Database tables are listed alphabetically now, derp.
- pgsql: Queries with the last line commented-out (`-- ` one-line comment style) caused "Internal SQLantern failure" (inability to count rows).

### Added
- A new constant: `SQL_DEDUPLICATE_COLUMNS`, which turns on and off the behaviour, described in "Changed".

### Changed
- Columns with duplicate names get table name added in parenthesis now. If a result has columns with identical names (e.g. `id`), some of those columns are not lost now (they were before), but have a table name added in parenthesis. E.g. if a query like `SELECT * FROM chats_chatters LEFT JOIN chats ON chats.id = chats_chatters.chat_id` returns two `id` columns, one of them becomes `id (chats_chatters)`, and the other `id (chats)`. It is now closer to the results in the native MariaDB/MySQL console. Doesn't match the native console fully, but it's better than before (and actually better than console results, IMHO).
- Views are now supported in both drivers (mysqli and pgsql). They are listed among the database tables, with an added "view" to distinguish them, and exported (export is still mysqli-only).

## [1.9.1 beta] - 2023-12-19

### Fixed
- Removed some legacy code, which prevented running any queries containing escaped characters (e.g. `SELECT '\''`) and ran other queries incorrectly (e.g. `SELECT '\n'`). The problem-inducing code was over 1.5 years old and we couldn't figure out why it was there at all :-( and 1.5 year ago is the earliest version we have, the code had apparently made sense somewhere before that, but not since.
- The errors are now displayed correctly for queries with HTML symbols/tags, which fail (e.g. `SELECT '<br>' FROM ???`). Before this change HTML tags became literally part of HTML code and became invisible.
- Tiny text mistakes, tiny error message improvements.

### Changed
- pgsql: Errors are now formatted exactly like in the mysqli driver (they looked different).

## [1.9.0 beta] - 2023-12-16

### Added
- Initial public release
