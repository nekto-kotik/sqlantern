# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased]

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
