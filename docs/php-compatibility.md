# Protector 6 on ImpressCMS 2.0.4 and PHP 7.4 to 8.4

**Target.** The `main` branch of `impresscms/impresscms`, ImpressCMS 2.0.4: PDO only, PHP 7.4 up to 8.4, the legacy class
names (`icms_db_Factory`, `icms_preload_Item`, ...). An earlier stage of this work targeted the 2.1 development line with
its renamed `Icms\...` classes; that line was dropped, and so was everything that existed only for it (the module loader
change in the core, the aliases for the renamed classes, the PHP 8.2 minimum). The phase notes in this directory
were written before and still say "2.1".

## Rules for the module's code

The module must parse and run on every PHP version from 7.4 to 8.4, so `root/modules/protector` is written in PHP 7.4
syntax:

* allowed: scalar and class types on parameters, returns and properties, `?Type`, arrow functions, `??=`, spread of
  lists, `declare(strict_types=1)`;
* not allowed: constructor promotion, `readonly`, enums, `match`, union types, `mixed`, `never`, `static` return types,
  nullsafe `?->`, attributes, `new` in initialisers, first-class callable syntax (`foo(...)`), trailing commas in
  parameter lists, `throw` expressions, `catch (Exception)` without a variable, spread of string keys, and functions
  that appeared after 7.4 (`str_contains()`, `str_starts_with()`, `str_ends_with()`, `array_is_list()`, ...).

`CLAUDE.md` asks for PHP 8.2 syntax; for this module the 7.4 floor of the core wins.

Where PHP 8 gave a cleaner design the same design is kept in 7.4 form: value objects with private properties and a
constructor, `LogLevel` and `DosAction` as classes of constants instead of enums, `switch` instead of `match`.

## How the rules are enforced

1. `tests/unit/tests/PhpCompatibilityTest.php` scans every file of the module with the tokenizer for newer functions,
   newer syntax tokens, constructor promotion and union types. It runs everywhere.
2. The same test runs `php -l` over every file with real older binaries when `PROTECTOR_PHP_BINARIES` lists them, for
   example `PROTECTOR_PHP_BINARIES="C:\...\php74\php.exe;C:\...\php80\php.exe;C:\...\php81\php.exe"`.
3. The functional suite, below, runs the whole module on each PHP version.

## The tests

The tests are written in PHP 7.4 syntax too and use **PHPUnit 9.6** (the last version that runs on 7.4), so both
suites can be run by any PHP from 7.4 to 8.4: `composer test:unit` and `composer test:functional`, with the web server
and the runner on the same or on different versions. Composer's platform is pinned to PHP 7.4.33 so that dependencies
resolve for the oldest version.

* `#[DataProvider]` and `#[Group]` are annotations (`@dataProvider`, `@group`), because PHPUnit 9 ignores attributes.
* `#[Scenario]` and `#[KnownDefect]` stay attributes: they are our own metadata, read with reflection by `spec.php`
  (which therefore needs PHP 8 to regenerate `SPEC.md`). On 7.4 a one-line attribute is a comment, so the tests still load.
* The tests and the harness were converted with the same Rector set as the module, with the rule that turns attributes
  into annotations skipped (it deletes them).
* The configuration files use the PHPUnit 9.3+ format (`<logging><junit outputFile=...>`).

Verified: 144 unit tests pass on 7.4, 8.0, 8.1, 8.2, 8.3 and 8.4 (311 assertions with the optional parse check enabled),
and the functional suite passes with the runner and the site server both on 7.4.33 and both on 8.4.27.

## The conversion

The module was first written with PHP 8.2 syntax and converted with Rector's downgrade set (`withDowngradeSets(php74:
true)`, run from a scratch project, not a dependency of this repository). Rector rewrote constructor promotion, readonly
properties, `match`, `str_contains()` and friends, nullsafe calls, first-class callables and similar; it left a
`@readonly` annotation in the docblocks where a property was `readonly`. The two enums were converted by hand. The only
remaining 8.x-sensitive area is behaviour, not syntax: for example `substr()` returns `false` instead of `''` on 7.4, which
Rector preserves by casting.

Repeat it after adding code in newer syntax: run Rector with the same set over `root/modules/protector/src`, then the
unit test above.

## The test site

The suite runs against a pristine ImpressCMS 2.0.4 site served by PHP's built-in web server, so any PHP version can serve
it (`tests/functional/bin/router.php` supplies `QUERY_STRING`, which 2.0 reads unguarded):

```
php -S 127.0.0.1:8204 -t <site directory> tests/functional/bin/router.php      (with the PHP version under test)
PROTECTOR_ENV_FILE=tests/functional/.env.204 php tests/functional/bin/site.php install
PROTECTOR_ENV_FILE=tests/functional/.env.204 php tests/functional/bin/site.php snapshot
PROTECTOR_ENV_FILE=tests/functional/.env.204 php tests/functional/bin/run.php
```

The pristine site is the `htdocs` directory of the `v2.0.4` tag (or of `main`). `.env.204` holds the address, the paths
and the database of the test site and is not committed (see the functional suite's README).

## Results

| Server PHP | Scenarios | Result |
|---|---|---|
| 7.4.33 | 130 | pass (276 assertions, 1 skipped on Windows) |
| 8.0 to 8.4 | 130 each | see the table in the pull request |

Every run installs the module through the 2.0.4 web installer under the PHP version being tested, so the install,
update (from 5.2) and uninstall hooks are exercised on that version too.

## Edits to existing scenarios (approved)

* SPM-04 and SES-01 contained the host `202.test`; they use the site address from the configuration.
* ENA-04 compared the exact typed signature text of `query()`; it now checks that no parameter of the database trap's
  `query()` is typed more narrowly than the core's. In 2.0.4 the core declares it without types, and the trap must not
  add any.
