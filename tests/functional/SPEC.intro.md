# Protector functional test specification

This document is **generated** from the tests (`php tests/functional/bin/spec.php`), so it always matches what runs.
It is the thing to review before any refactoring starts: each row is one automated scenario that talks to a real
ImpressCMS 2.1 site over HTTP (`http://202.test`), looks at the database and at the module's data files, and records
what Protector does today.

After you approve it, the suite is **frozen**: it must stay green, with unchanged assertions, after every refactoring
phase. Only rows flagged with a **known defect** may change, and only in the phase that fixes that defect.

## What the baseline run found

Running the module exactly as it is in the repository on this ImpressCMS 2.1 build (`2.1.0 Beta`, build 130):

1. **Protector does not protect anything (S7).** `postcheck.inc.php` waits for a class named
   `Icms\Db\Legacy\icms_db_legacy_Factory`, which does not exist, so the whole second stage (preference loading,
   address checks, DoS, brute force, SQL-injection and spam checks, logging) never runs and the preference cache file
   is never written. Pages are served as if the module were not there (scenarios ASI-01, ASI-02).
2. **Even with that fixed, address-based protection is inert (D4).** The module reads the visitor's address with
   `filter_input(INPUT_SERVER, 'REMOTE_ADDR')`, which returns `null` under nginx + PHP FastCGI (the usual production
   setup) while `$_SERVER['REMOTE_ADDR']` is correct (ASI-03). Bans, DoS, brute-force and logging then do nothing.
3. **Even with both fixed, the SQL-injection trap cannot load (S11).** `ProtectorMySQLDatabase::query()` has a
   signature that is incompatible with the core class it extends, which is a PHP fatal error (ASI-04).
4. **Anything detected before the database exists dies with "No DB connection" (D9).** Null bytes, `../`, bad uploads,
   contamination, `xmlrpc.php` and the old criteria-bug probe are all stopped, but by an error message instead of the
   configured action, and never logged (SAN-01, SAN-13, SAN-16, UPL-02, FEA-03). They only behave as documented when
   logging is switched off.
5. **The "group 1 allowed IPs" form is broken (D1).** It stores the line numbers instead of the addresses. With two or
   more lines every administrator is locked out; with one line nothing is restricted (BAN-10, BAN-11).
6. **Contamination with a ban action never bans (D10)** and **the built-in HTMLPurifier filters crash (D11)**
   (SAN-04, FLT-06).

## The two profiles

| Profile | What is installed | Purpose |
|---|---|---|
| `enabled` (default) | the repository's code, with S7, D4 and S11 corrected **in the installed copy only** by `tests/functional/src/Site/CompatibilityProfile.php` | records what Protector is *meant* to do, which is what the refactoring has to preserve |
| `as-is` (`PROTECTOR_PROFILE=as-is`) | the repository's code, untouched | proves findings 1–3; only the `ASI-*` scenarios are meaningful here |

The patches are three small text substitutions and become no-ops once the code no longer contains the patterns. The
repository itself is **not** modified by Phase 0.

## How the suite works

* The installer is driven over HTTP once (`php tests/functional/bin/site.php install`) and a snapshot of the site
  (files + database) is taken. Every run starts by restoring that snapshot; every test starts from default
  preferences, empty log/access tables and no ban files.
* Different visitors are simulated by binding the client's source address (`127.0.0.2`, `127.0.0.3`, ...). The default
  "reliable IPs" setting exempts `127.0.0.1`, so the tests use other addresses for the "attacker".
* Preferences are changed in the database, then one harmless request refreshes Protector's cache, exactly like saving
  them in the control panel would.
* Rows marked **known defect** assert the *current, wrong* behaviour on purpose.

Run it with:

```
composer test:functional            # enabled profile, about 100 seconds
composer test:functional:as-is      # as-is profile (only the ASI-* scenarios are meaningful there)
```

## Not covered, and why

* **Manipulation check (MAN-01)** is skipped on this Windows host: the module compares `SCRIPT_FILENAME`
  (backslashes) with `ICMS_ROOT_PATH` (slashes), so the check can never fire here. It runs on Linux.
* **Per-user link limit for logged-in non-administrators** needs a second user account; only the administrator
  exists in the snapshot (SPM-06 covers the administrator only).
* **Real DNS block-list and Project Honey Pot filters** (`postcommon_post_deny_by_rbl`, `..._httpbl`) need the
  network and are not exercised.
* **Rendering details** of the admin pages (colours, layout) are deliberately not asserted; only content, forms,
  escaping and behaviour are.

