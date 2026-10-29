# Protector

Protector hardens an ImpressCMS site against common attacks: request sanitising (NUL bytes, `../`, isolated SQL
comments, `UNION`, `$_GLOBALS` contamination), upload checks, an anti-SQL-injection database trap, an output check
against reflected XSS, DoS / crawler / brute-force limits with temporary or permanent bans, session hi-jack
protection, an IP allow list for administrators, and a log of everything it blocks.

This branch is the **6.x** line: ImpressCMS **2.0.4** (the `main` branch of `impresscms/impresscms`) on **PHP 7.4 up to 8.4**.
The `5.x` branch remains for ImpressCMS 1.4. The code is written in PHP 7.4 syntax so that it runs on that whole range.

## Install

Build the package (or take a released one) and put it on top of the Protector of an installed ImpressCMS 2.0.4 site:

```
composer package             # php bin/build-package, writes var/dist/protector-<version>-<commit>.zip
```

1. Unzip the package in the `modules/` folder of the site. It holds one folder, `protector/`, and overwrites the
   bundled Protector.
2. In the control panel, **update** the module (System, Modules). Until then the site keeps running the old 5.x code.

The update places the forwarding files in the trust path (the preload that ImpressCMS 2.0.4 ships still includes two
small files from there), copies your own filters from `ICMS_TRUST_PATH/modules/protector/filters_*` into the module,
deletes the files of the 5.x layout that the new version no longer has, moves the ban lists and the preference cache from
`ICMS_TRUST_PATH/modules/protector/configs/` to `ICMS_TRUST_PATH/cache/protector/` and adds an index to the access
table. See [docs/packaging.md](docs/packaging.md).

Installing without a bundled copy works the same way: unzip, then install the module in the control panel. The
installer registers the admin templates and copies `preload/protector.php` to `plugins/preloads/` unless the core
already has one.

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
trust_path/modules/protector/   forwarding files for the core's bundled preload (added to the package, placed by the update)
bin/build-package           builds the distributable zip, see docs/packaging.md
tests/unit/                 PHPUnit 9.6, runs on every PHP version from 7.4 to 8.4, no site needed
tests/functional/           black-box suite against a real ImpressCMS 2.0.4 site, see its README and SPEC.md
docs/                       notes for each phase of the 6.x work, and the old 5.x documentation in docs/legacy
```

## Development

```
composer install
composer test:unit           # a second, on any PHP from 7.4 to 8.4
composer test:functional     # about 3 minutes, needs a test site, see tests/functional/README.md
```

The module's PHP 7.4 syntax is kept by [docs/php-compatibility.md](docs/php-compatibility.md), which also describes how
it is tested on every PHP version from 7.4 to 8.4.
