# Functional test suite

Black-box tests for the Protector module. They run against a real ImpressCMS **2.0.4** site over HTTP, and inspect the
database and the module's data files. Nothing is mocked. The reviewable description of every scenario is
[SPEC.md](SPEC.md) (generated); the results of the baseline run are in [BASELINE.md](BASELINE.md).

## One-time setup

1. `composer install` in the repository root (PHPUnit only).
2. A pristine copy of ImpressCMS 2.0.4: the `htdocs` directory of the `v2.0.4` tag of `impresscms/impresscms`, copied to
   the directory named by `PRISTINE_PATH`. The suite resets the test site from it before every install.
3. Optionally a configuration file `tests/functional/.env` (git-ignored) to change the defaults in `src/Config.php`, which
   are the values below:

   ```
   SITE_URL=http://127.0.0.1:8204
   SITE_PATH=C:/path/to/204-site
   SITE_PATH_FOR_HASH=C:/path/to/204-site
   TRUST_PATH=C:/path/to/204-trust
   SNAPSHOT_PATH=C:/path/to/204-snapshot
   PRISTINE_PATH=C:/path/to/_backup/204-pristine
   DB_NAME=protector_test204
   SNAPSHOT_DB=protector_test204_snapshot
   ```

   Generated values (admin password, database salt) are appended to the file on first use. Another file can be chosen
   with `PROTECTOR_ENV_FILE`, and any key can be overridden for one run with an environment variable `PROTECTOR_<KEY>`.
4. A web server for `SITE_PATH`. Any PHP version from 7.4 to 8.4 will do; the quickest is PHP's built-in server with the
   router that supplies `QUERY_STRING` (ImpressCMS 2.0 reads it unguarded):

   ```
   php -S 127.0.0.1:8204 -t C:/path/to/204-site tests/functional/bin/router.php
   ```

   Run the suite once per PHP version you want to cover (docs/php-compatibility.md).
5. `composer site:install` – resets the site to its pristine (uninstalled) state, copies the module under test into the
   installer's bundled-module location and drives the web installer. Takes a few seconds.
6. `composer site:snapshot` – stores files + database of the installed site. Every test run restores this snapshot first.

## Running

```
composer test:functional                 # whole suite (about 3 minutes)
composer test:functional -- --filter RateLimitTest
composer spec                            # regenerate SPEC.md from the test attributes
```

Reports are written to `tests/functional/reports/` (JUnit XML, test-dox text).

## Layout

| Path | Purpose |
|---|---|
| `tests/` | the scenarios, one class per area; each test carries `#[Scenario]` (and `#[KnownDefect]`) attributes |
| `src/Layout.php` | the only place that knows where the module's files live; changes when the layout changes |
| `src/Site/` | install, snapshot/restore, admin session helper |
| `src/Http/` | small cURL client (cookies, source address, Referer) |
| `fixtures/` | test-only pages copied into the site root: `probe.php`, `sqlq.php`, `reflect.php`, `skipdos.php`, `reflect_nobu.php` |

## Things worth knowing

* **POSTs need a same-site `Referer`.** Without one the core puts the database layer into write-protected "proxy"
  mode and silently refuses writes done through `query()` (for example installing a module). The client adds it.
* **Client addresses are simulated** by binding the source address (`127.0.0.x`). The default "reliable IPs"
  preference exempts `127.0.0.1`, so "attackers" use other addresses.
* **The test site is a throw-away.** `site:install` mirrors it from `PRISTINE_PATH`, recreates the trust path and the
  database named by `DB_NAME`, and never touches other sites or databases.
* **Windows**: `dirname('/x.php')` returns a backslash; the client normalises redirect targets accordingly.

## Unit tests

The new classes under `src/` that do not need a running site are covered by plain PHPUnit tests in `tests/unit/`
(`composer test:unit`, under a second). They are supplementary: the functional suite stays the regression gate.

## Upgrade scenario (LIF-08)

`UpgradeTest` exports the git ref `5.2` (the previous release) with `git archive`, installs it through the core
installer, adds data, copies the current files over it and runs the module update. It needs `git` and `tar` on the
PATH and the `5.2` branch in the repository; it is skipped with a message otherwise. The harness reads the module
tree to install from `PROTECTOR_MODULE_SOURCE` when that environment variable is set (used by the scenario).
