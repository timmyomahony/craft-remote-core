# Craft Remote Services Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## 4.1.0 - 2022-10-01

### Added

- Added "file size" to the backup and sync output.
- Better error handling. Temporary files are deleted more reliably now when something goes wrong, avoiding issues with disk space getting eaten up ([issue #36](https://github.com/weareferal/craft-remote-backup/issues/36)).
- Added timezone handling to files to give accurate dates, times & "time since"
- Added file chunking to Google Drive upload ([issue #11](https://github.com/weareferal/craft-remote-core/pull/11) thanks @joelzerner)
- Added small icon to the utilities section to show current provider

### Changed

- Improved the utilities interface, converting to a more readable table with multiple columns ([issue #10](https://github.com/weareferal/craft-remote-backup/issues/10), [issue #43](https://github.com/weareferal/craft-remote-sync/issues/43))
- Changed the filename formatting to use brackets instead of underscores ([issue #45](https://github.com/weareferal/craft-remote-sync/issues/45)).
- Changed file listing to avoid crashing when an unreadable filename is received.

### Fixed

- Cleaned up the base provider service, improving logging.

## 4.0.0 - 2022-08-18

### Added

- Craft 4 compatibility. Version has jumped from 1.X.X to 4.X.X to make following Craft easier.

## 1.4.0 - 2020-12-08

### Added

- Added generic s3-compliant provider (issue #31 on craft-remote-sync)
- Added TTR to queue jobs (issue #38 on craft-remote-sync)

### Changed

- Bumped version number for parity between sync & backup plugins
- Added support for transfering files to and from all volume backends, not just local
- Fixed filename regex (issue #26 on craft-remote-sync)
- Moved shared utilities JS and CSS to core module
- Updated the formatting for file table

## 1.0.1 - 2020-11-06

### Changed

- Fixed composer 2 issue

## 1.0.0 - 2020-06-17

### Added

- Initial release
