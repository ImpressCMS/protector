# Phase 1 preflight: findings

Goal: confirm, against the real core, the assumptions the plan makes about the single-directory layout, PSR-4 classes,
IPF persistence, install/update hooks and CSRF handling, **before** any file is moved. No module code was changed.

Environment: ImpressCMS 2.1.0 Beta (build 130) on Laravel Herd, `http://202.test`. The web SAPI runs **PHP 8.2.34**
(cgi-fcgi); the CLI used by the test runner is 8.3.33.

Method: read the relevant core code, then built a throw-away module (`pftest`, since removed) mirroring the planned
structure and exercised it through the real control panel: install, admin page, update, uninstall.

## Verified as assumed

| Assumption | Result |
|---|---|
| A PSR-4 class can be used at `startCoreBoot`, before the database exists | **Yes.** A preload (`class IcmsPreloadX extends \Icms\Preload\Item`) required a 15-line `spl_autoload_register` closure and called a namespaced class; the result was visible to the page. No core change is needed for the interim autoloader. |
| `icms_version.php` in the module directory is picked up | **Yes.** `Entity::loadInfo()` includes `modules/<dir>/icms_version.php` and falls back to `xoops_version.php`. It first loads `modules/<dir>/language/<lang>/modinfo.php` itself, so the module's three-tier language fallback code is unnecessary. |
| An IPF entity can be a PSR-4 class | **Yes**, with a thin shim only for the handler. `icms_getModuleHandler('entry','pftest')` finds `mod_pftest_EntryHandler` in `class/EntryHandler.php` (core classmap). That shim extends a PSR-4 repository class whose constructor sets `$this->className` to the PSR-4 entity. No `mod_<module>_<Name>` entity class is needed. The shim must `require_once` the module's `src/autoload.php` itself, because the core's class map is built after the preload stage. |
| `icms::$security` can replace the module's ticket class | **Yes.** `getTokenHTML()`, `createToken()`, `check()`; tokens are session-bound, per-user-agent and expire (default: session lifetime). See "Behaviour differences" below. |
| The IPF table view works in a module admin page | **Yes.** `new \Icms\Ipf\View\Table($handler)` + `addColumn(new \Icms\Ipf\View\Column(...))` + `->fetch()` renders paging, sorting, edit/delete links. |
| Install hook, update hook, uninstall hook | **Yes.** See "Hooks" below. |
| The trust path is writable from a web request | **Yes.** `ICMS_TRUST_PATH/cache/protector` can be created and written from a page request. |
| D9 (events before the database exists cannot be logged) | Already reproduced by the functional suite (SAN-01, SAN-13, SAN-16, UPL-02, FEA-03). |

## Corrections to the plan

1. **Templates are flat.** The core reads `modules/<dir>/templates/<file>` (and `templates/blocks/` for blocks). The
   manifest's `file` entry is both the file name and the template name stored in the database. The planned
   `templates/admin/*.html` layout does not work; use `templates/protector_admin_*.html`.
2. **Smarty delimiters are `<{ ... }>`**, not `{ ... }`. Relevant to Phase 4 (and to the 4-space-indent rule).
3. **IPF table creation is only triggered by a hook.** The core runs `moduleUpgrade()` (which creates and alters the
   `object_items` tables) *inside* the branch that executes `onInstall` / `onUpdate`. A module with `object_items` but
   no `onInstall` script gets no tables. The hook script is therefore always required, and it must also
   `define('PROTECTOR_DB_VERSION', n)` (upper-cased dirname + `_DB_VERSION`) before the core calls the upgrade;
   otherwise the install fails with "Undefined constant". The database version is stored in `modules.dbversion` and can
   drive `protector_db_upgrade_<n>()` functions.
4. **`sqlfile` works and replaces the hand-written SQL loader**, with one change: table names inside the SQL file must
   be the full unprefixed names (`CREATE TABLE protector_access`), whereas today's `sql/mysql.sql` uses `access` /
   `log` and relies on the module prefixing them. `tables => [...]` in the manifest drives the drop on uninstall.
   Installed tables keep their current names (`<prefix>_protector_log`, `<prefix>_protector_access`), so no data
   migration is involved.
5. **IPF item name drives the admin links.** The table's edit/delete links point to `admin/<item>.php?op=mod|del&<item>_id=N`.
   A `log` item needs an `admin/log.php` front controller.
6. **Opcache needs two seconds.** The web PHP revalidates files every 2 seconds. After copying module files, wait
   at least 2 seconds before the next request; otherwise a stale class is served (I hit this once). The test harness
   installs and runs seconds later, so it is not affected today, but any "edit, then request immediately" step is.
7. **The core's own installer cannot report a failed module insert** (`Icms\Module\Entity::name()` no longer exists),
   which is why a module insert failure shows as a PHP fatal. Not ours to fix in this phase; relevant for Phase 6.

## Hooks (verified)

| Item | Behaviour |
|---|---|
| Declaration | manifest keys `onInstall`, `onUpdate`, `onUninstall` = path relative to the module directory; the file is `include_once`d only if the key is set |
| Function names | `xoops_module_install_<name>` is tried first, then `icms_module_install_<name>` (same pattern for `update`, `uninstall`); `<name>` is `modname` or the dirname |
| Signatures | install and uninstall: `($module)`; update: `($module, $prev_version, $prev_dbversion)` (observed `["1.0.0", 1, "1.1.0"]`, the module object already carries the new version) |
| Return value | falsy = "failed to execute" message; a string return is appended to the log |
| Update behaviour | adding a field to the entity and bumping `*_DB_VERSION` added the column automatically and kept existing rows |
| Uninstall | drops every table listed in `tables`, removes templates, runs the hook |

## Behaviour differences when replacing the ticket class with `icms::$security`

* No "repost the form" page: `check()` only returns true/false. The admin handler must show its own error (today
  scenarios ADM-06 and ADM-07 assert the old "GTicket Error" page and that nothing is deleted; **ADM-07's expected text
  will have to change in Phase 4 and needs your approval then**, ADM-06 should stay valid).
* Tokens are validated against `$_SERVER['HTTP_USER_AGENT']` without a null check (a request without a User-Agent raises
  a warning); the admin pages are only reachable by browsers, so this is acceptable.
* There is no "area" check; `createToken($timeout, $name)` takes a token name, which can be `protector_admin`.

## Risk to resolve in Phase 4 (not decided here)

Using IPF for the existing `protector_log` table means the entity must describe the table that 5.x installations
already have (`lid`, `uid`, `ip`, `type`, `agent`, `description`, `extra`, `timestamp DATETIME`). IPF's automatic
upgrade adds missing columns, but I have not verified that it leaves existing columns and their types alone. Until it is
prototyped against a real 5.x table with data, the plan's "log through IPF" stays a proposal; the fallback is the
plain repository class with prepared statements, which has no such risk. `protector_access` stays outside IPF in
either case.

## What the first Phase 1 step can now do

Create the single module directory with: `icms_version.php`, `language/`, `src/autoload.php` (+ namespaced skeleton),
`preload/` source, `class/` (IPF shims only when needed), `templates/` (flat), `sql/`, `include/` hook scripts with
`PROTECTOR_DB_VERSION`; keep a two-file forwarding shim in `trust_path/modules/protector/include/` for the core's
bundled preload until the core PR lands; move code unchanged; run the frozen suite.
