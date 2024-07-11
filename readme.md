RS Entry Archives for Gravity Forms  (WordPress Plugin)
==

_This plugin adds the ability to archive Gravity Forms entries, which are hidden from the default entry view._

## Description ##

This plugin allows you to mark entries as archived, similar to how you would mark them as read or unread. Archived entries only show under the Entries list when you select the "Archived" filter.

Here you can see the archive functionality from the Entry list screen:

1. ![Screenshot showing the Archive link in the row actions below the first column.](assets/screenshots/screenshot-0.png)
2. ![Screenshot the message confirming the entry was archived.](assets/screenshots/screenshot-4.png)
3. ![Screenshot showing the updated post count for each filter, the newly archived entry changed the archived entry count from 7 to 8.](assets/screenshots/screenshot-5.png)

## Screenshots ##

On the entry list screen, a filter for "Archived" will appear allowing you to view the archived entries.
![Screenshot showing the default filters, as well as the "Archived" filter with 41 entries in this example.](assets/screenshots/screenshot-1.png)

On the entry list, an "Archive" or "Unarchive" link will appear next to the "Spam" or "Trash" links.
![Screenshot showing the Archive link in the row actions below the first column.](assets/screenshots/screenshot-2.png)

When exporting entries, you can use conditional logic to get only archived or unarchived entries, or both.
![Screenshot of conditional logic fields from the export screen. The "Archive Status" field is selected.](assets/screenshots/screenshot-3.png)

A message appears to indicate when an entry has been archived.
![Screenshot the message confirming the entry was archived.](assets/screenshots/screenshot-4.png)

## Changelog ##

#### 1.4.3
* Added the filter `rs_entry_archives/show_archived_entries`, if set to true, the entries displayed on the current page will show archived entries instead of active ones.

#### 1.4.2
* Moved separator before archive link

#### 1.4.1
* Added Git Updater support
* Updated Readme

#### 1.2.0
* Added to GitHub

#### 1.1.0
* First release
