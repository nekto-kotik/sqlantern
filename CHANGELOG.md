# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased]

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
