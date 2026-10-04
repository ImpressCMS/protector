# Functional test suite

Black-box tests for the Protector module. They run against a real ImpressCMS 2.1 site served by Laravel Herd
(`http://202.test`), over HTTP, and inspect the database and the module's data files. Nothing is mocked.
The reviewable description of every scenario is [SPEC.md](SPEC.md) (generated); the results of the baseline run are in
[BASELINE.md](BASELINE.md).

## One-time setup

1. `composer install` in the repository root (PHPUnit only).
2. Optional `tests/functional/.env` (git-ignored) to override the defaults in `src/Config.php`
   (site path/URL, trust path, database credentials, database name). Generated values (admin password, database salt)
   are appended to it on first use.
3. `composer site:install` – resets the Herd site to its pristine (uninstalled) state from a backup copy, copies the
   module under test into the installer's bundled-module location and drives the web installer. Takes a few seconds.
4. `composer site:snapshot` – stores files + database of the installed site. Every test run restores this snapshot first.

## Running

```
composer test:functional                 # whole suite (about 100 seconds)
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
* **The Herd site is a throw-away.** `site:install` mirrors it from `C:/Users/david/trustpath/_backup/202-pristine`
  (a copy made before the first run), recreates the trust path and database `protector_test`, and never touches other
  Herd sites or databases.
* **Windows**: `dirname('/x.php')` returns a backslash; the client normalises redirect targets accordingly.
