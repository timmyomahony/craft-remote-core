# Craft Remote Services Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## 5.0.0 - 2024-08-03

### Added

- Craft 5 support.

### Fixed

- Fixed file listing issue with AWS. See Remote Backup [Issue #54](https://github.com/timmyomahony/craft-remote-backup/issues/54) and Remote Core [PR #24](https://github.com/timmyomahony/craft-remote-core/pull/24)

## 4.1.2 - 2023-12-07

### Added

- Merged support for IAM profiles. See [Issue #44](https://github.com/timmyomahony/craft-remote-backup/issues/44).

### Fixed

- Simplified the regex to accept all characters in the system name & env. Hopefully avoiding further interface errors. See [Issue #49](https://github.com/timmyomahony/craft-remote-backup/issues/49)
- Cleaned up logging. See Remote Backup [Issue #59](https://github.com/timmyomahony/craft-remote-backup/issues/59) and Remote Core [PR #25](https://github.com/timmyomahony/craft-remote-core/pull/25)

## 4.1.1 - 2023-09-29

### Fixed

- Updated the regex for filenames to include ' and _ (occasionally included in system names). See [Issue #19](https://github.com/weareferal/craft-remote-core/issues/19).

### Added

- Better handling of default environment when creating filename. See [Issue #54](https://github.com/weareferal/craft-remote-sync/issues/54).
- Additional logging to help with debugging user issues.

## 4.1.0 - 2022-10-05

### Added

- Temporary files are now deleted more reliably now when something goes wrong, avoiding issues with disk space getting eaten up.
- Added a proper table layout to the utilties interface to improve readability.
- Added "file size" to the utilities interface so you can now see how larger your files are.
- Added timezone handling to files to give accurate dates, times & "time since"
- Added a "test connection" button to the settings
- Added file chunking to Google Drive upload
- Added small icon to the utilities section to show current cloud provider at-a-glance

### Changed

- Changed the filename formatting to use double underscores, improving reliability.
- Refactored core module aliases for consistancy
- Refactored the base provider service, improving logging.

### Fixed

- [Issue #10](https://github.com/weareferal/craft-remote-backup/issues/10)
- [Issue #11](https://github.com/weareferal/craft-remote-core/pull/11) (thanks @joelzerner)
- [Issue #36](https://github.com/weareferal/craft-remote-backup/issues/36)
- [Issue #43](https://github.com/weareferal/craft-remote-sync/issues/43)
- [Issue #45](https://github.com/weareferal/craft-remote-sync/issues/45)
- [Issue #34](https://github.com/weareferal/craft-remote-backup/issues/34)

### Removed

- Removed Dropbox temporarily due to issues with long-held tokens.

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
