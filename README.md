# Craft Remote Services module for Craft CMS 3.x

This module is used by `craft-remote-backup` and `create-remote-sync` for shared functionality, particularly with regard to remote backend providers:

- AWS
- Google Drive
- Dropbox
- Digital Ocean
- Backblaze

The business logic for these providers is stored here so that it doesn't have to be duplicated across plugins.
