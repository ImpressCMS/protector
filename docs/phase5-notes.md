# Phase 5: install, update and manifest

Final state: 130 functional scenarios (273 assertions, 1 skipped on Windows) and 138 unit tests.

| Before | After |
|---|---|
| `eval()`-defined hook functions, `global $ret`, `protector_message_append_*` | plain `icms_module_install_protector()`, `icms_module_update_protector()`, `icms_module_uninstall_protector()` in `include/`, delegating to `Install\Installer`, `Updater` and `Uninstaller` |
| a hand-written SQL loader and drop loop, `sqlfile = false` | `sqlfile` and `tables` in the manifest; the core creates and drops the tables |
| ~330 lines of `$modversion['config'][] = array(...)` in `icms_version.php` | `config/preferences.php` (a list of 33 entries, generated from the old manifest so the names, types, defaults and order are identical); `icms_version.php` is 60 lines |
| `icms_version.php` loading its own language file | removed: the core loads `modinfo.php` before it includes the manifest |
| runtime data in `ICMS_TRUST_PATH/modules/protector/configs/` | `ICMS_TRUST_PATH/cache/protector/` (`DataPaths`, `Install\DataDirectory`) |
| nothing for the access table | composite index `ip_uri_expire (ip(45), request_uri(191), expire)` on new installs and added by the update |

## Moving the runtime data

`Updater` moves `badips*`, `group1ips*`, `bwlimit*` and `configcache*` to the new directory and deletes an old copy
when the new directory already has a file of that name. Between copying the new files and running the update (a few
minutes in practice) the module keeps reading the old location (`DataPaths::forReading()`) so a site is not
unprotected; every write already goes to the new directory. The update scenario LIF-11 covers this, LIF-10 the fresh
install.

The `configs/index.html` placeholder is no longer shipped, so a fresh install does not create the old directory.
The data directory is protected by the trust path's own `.htaccess` and gets an empty `index.html`.

## Dropped upgrade steps

The old update hook also widened `conf_title` / `conf_desc` in the **core's** `config` table, dropped duplicate keys on
it, converted a `timestamp(14)` log column and deleted `prefix_manager.php` from the trust path. They belonged to
XOOPS Cube era versions (2.x to 3.0) and modified a table the module does not own, so they were not carried over;
updating from 5.1 and later is covered.

## Not changed

* The module version (`include/version.txt`, 5.1.0) is pinned by LIF-01; bumping it is a release decision.
* `oninstall.php` and the others moved from the module root to `include/`; the manifest points to the new paths.
* The `trust_path/modules/protector/include/*.inc.php` forwarding files stay until the bundled-copy change in the core
  (Phase 6).
