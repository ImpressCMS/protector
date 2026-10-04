# Protector

Protector hardens an ImpressCMS site against common attacks: request sanitising (NUL bytes, `../`, isolated SQL
comments, `UNION`, `$_GLOBALS` contamination), upload checks, an anti-SQL-injection database trap, an output check
against reflected XSS, DoS / crawler / brute-force limits with temporary or permanent bans, session hi-jack
protection, an IP allow list for administrators, and a log of everything it blocks.

This branch is the **6.x** line: ImpressCMS **2.1** and PHP **8.2 or newer**. The `5.x` branch remains for ImpressCMS 1.4.

## Install

1. Copy `root/modules/protector/` to `modules/protector/` of the site.
2. Copy `trust_path/modules/protector/` into the site's trust path. The preload that ImpressCMS 2.1 ships still
   includes two small files from there; they forward to the module. This step goes away when the core is updated.
3. In the control panel, install the module (or **update** it when upgrading from 5.x).

The installer creates the tables, registers the admin templates and copies `preload/protector.php` to
`plugins/preloads/` unless the core already has one. Upgrading from 5.x also moves the ban lists and the preference
cache from `ICMS_TRUST_PATH/modules/protector/configs/` to `ICMS_TRUST_PATH/cache/protector/` and adds an index to the
access table. Until you run the update, the module keeps reading the old location.

Preferences are in the control panel under the module's preferences; the module's own admin pages show the log, the
bad-IP and administrator-IP lists, and a security advisory with two attack simulation links.

## Writing a filter

Drop a file named `<hook>_<name>.php` into `filters_byconfig/` (list it in the *Filters* preference) or
`filters_enabled/` (always on). It defines either a function `protector_<hook>_<name>()` or a class
`protector_<hook>_<name>` with `execute()`. The hooks are `precommon_badip`, `precommon_bwlimit`, `prepurge_exit`,
`postcommon_post`, `postcommon_register`, `postcommon_manipu`, `f5attack_overrun`, `crawler_overrun`,
`bruteforce_overrun` and `spamcheck_overrun`. The global class `Protector` remains for filters (`Protector::getInstance()`,
`output_log()`, `message`, `ip_matched_info`); new code should use the namespaced classes.

## Layout

```
root/modules/protector/     the module (copied to modules/protector)
  src/                      ImpressCMS\Module\Protector\ (PSR-4): Kernel, guards, Ban, Dos, Admin, Install, ...
  config/preferences.php    the module's preferences
  templates/                admin templates (Smarty)
  include/                  precheck/postcheck entry points, install/update/uninstall hooks
trust_path/modules/protector/   forwarding files for the core's bundled preload
tests/unit/                 PHPUnit, no site needed
tests/functional/           black-box suite against a real ImpressCMS 2.1 site, see its README and SPEC.md
docs/                       notes for each phase of the 6.x work, and the old 5.x documentation in docs/legacy
```

## Development

```
composer install
composer test:unit           # a second
composer test:functional     # about 3 minutes, needs the Herd site described in tests/functional/README.md
```
