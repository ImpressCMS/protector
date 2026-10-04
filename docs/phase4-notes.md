# Phase 4: admin UI

The two admin pages (log and IP lists, advisory) no longer echo HTML from procedural scripts. Final state: 128
functional scenarios (all passing, 1 skipped on Windows) and 128 unit tests.

## Structure

| Piece | Replaces |
|---|---|
| `admin/index.php` (about 40 lines: permission check, language, dispatch, render) | the front controller that `include`d `admin/pages/*.php` |
| `Admin\AdminApplication` (wiring and page selection) | the `$page` switch |
| `Admin\StartPage` (POST actions and the view data of the start page) | `admin/pages/index.php` (277 lines) |
| `Admin\AdvisoryPage` and `Advisory\*Check` (one class per check, each returns a `CheckResult`) | `admin/pages/advisory.php` |
| `Admin\IpListParser`, `Admin\UserAgentLabel` | inline parsing and formatting |
| `Log\LogRepository` (prepared statements, deterministic order) | the inline `SELECT` / `DELETE` statements |
| `Admin\CoreCsrfTokens` over `icms::$security`; `Admin\CoreRedirector` over `redirect_header()` | `include/gtickets.php` (295 lines) |
| `templates/protector_admin_index.html`, `templates/protector_admin_advisory.html` (Smarty, `<{ }>`, 4-space indent) | echoed HTML |
| the core's module admin menu (`admin/admin_menu.php`) | `admin/mymenu.php` |

Output is escaped in the templates with Smarty's `|escape`; no custom escaping helper was added. The inline JavaScript
of the old page is reduced to two small functions, and the confirmation texts travel in `data-confirm` attributes
instead of being pasted into a script string.

## Templates and the install hooks

The templates are declared in the manifest (`$modversion['templates']`), so the core registers them on install and
update and removes them on uninstall. The hand-written template loaders in `oninstall.php` and `onupdate.php` would have
registered every file in `templates/` a second time under a different name, so they were removed. Scenario LIF-09
checks that each template exists exactly once after install, update and reinstall, and that uninstall removes both.

## Approved changes to frozen scenarios

* **ADM-06** removes the field `protector_admin_REQUEST` instead of `XOOPS_G_TICKET`.
* **ADM-07** sends an invalid value in that field and expects the module's refusal message instead of the
  "GTicket Error" page. The core's `check()` only returns true or false, so there is no "repost the form" page any more.

## Decisions to review

* **The log list does not use `Icms\Ipf\View\Table`.** ADM-01 to ADM-05 pin the markup (the form `MainForm`, the
  `ids[]` checkboxes, the `action` field, the single-quoted attributes), which the IPF table cannot produce. The plan
  kept IPF as a proposal pending a test against an existing 5.x table; nothing in Phase 4 needed it.
* **The form field is still called `action`, the page selector still `page`.** The scenarios and the DB-trap
  detection in the precheck stage (`admin/index.php?page=advisory`) depend on both, so the `op` convention was not applied.
* **The advisory page keeps the labels "mainfile.php" and "databasefactory.php"** because ADM-09 asserts them, although
  the two checks now mean "the pre- and post-check ran" and "the database layer is ready". Three checks were added:
  preload present, data directory writable, PHP version. Renaming or removing the two old labels needs a change to
  ADM-09 and your approval.

## Behaviour differences

* The log list is ordered by time, then by id (newest first), so the order is stable when records share a second.
* Compacting a log without duplicates, and deleting without a selection, no longer produce a SQL error.
* A blank line in the "bad IPs" text area is skipped instead of being stored as an empty address.
* A token that is missing or expired sends the administrator back to the module's admin page with a message; before,
  it went to the site's front page.
