# Craft Remote Core

A helper library for [`craft-remote-sync`](https://github.com/weareferal/craft-remote-sync) and [`craft-remote-backup`](https://github.com/weareferal/craft-remote-backup) Craft plugins.

This module is used by `craft-remote-backup` and `create-remote-sync` for shared functionality, particularly with regard to remote backend providers:

- AWS
- Google Drive
- Dropbox
- Digital Ocean
- Backblaze

The business logic for these providers is stored here so that it doesn't have to be duplicated across plugins.
