# Protector functional test specification

This document is **generated** from the tests (`composer spec`), so it always matches what runs. Each row is one
automated scenario that talks to a real ImpressCMS 2.1 site over HTTP (`http://202.test`), looks at the database and at
the module's data files, and records what Protector does.

The suite is **frozen**: it must stay green, with unchanged assertions, after every refactoring phase. Only rows flagged
with a **known defect** may change, and only in the phase that fixes that defect. Changes made so far:

* **Phase 0** recorded the module as it was shipped (see [BASELINE.md](BASELINE.md)).
* **First Phase 1 commit** fixed the three defects that made the module do nothing on ImpressCMS 2.1 (S7, D4, S11).
  Scenarios `ASI-01` to `ASI-04`, which documented the broken behaviour, were replaced by `ENA-01` to `ENA-04`, which
  assert the fixed behaviour. All other scenarios were untouched and passed before and after.
* **Phase 1 layout move** (single module directory, namespaced classes). Only the layout adapter (`src/Layout.php`)
  changed, plus one hard-coded file path in `ENA-04` that now comes from the adapter. One scenario was **added**,
  `LIF-08` (upgrade from the previous release); no existing assertion was changed.
* **Phase 2** (decomposition of the `Protector` class into `Kernel`, guards and services) changed no row of this
  document and no assertion. The only edit to the suite is this note.

## Known defects still pinned

1. **Anything detected before the database exists dies with "No DB connection" (D9).** Null bytes, `../`, bad uploads,
   contamination, `xmlrpc.php` and the old criteria-bug probe are stopped, but by an error message instead of the
   configured action, and never logged (SAN-01, SAN-13, SAN-16, UPL-02, FEA-03). They behave as documented when
   logging is switched off.
2. **The "group 1 allowed IPs" form is broken (D1).** It stores the line numbers instead of the addresses. With two or
   more lines every administrator is locked out; with one line nothing is restricted (BAN-10, BAN-11).
3. **Contamination with a ban action never bans (D10)** and **the built-in HTMLPurifier filters crash (D11)**
   (SAN-04, FLT-06).

## How the suite works

* The installer is driven over HTTP once (`composer site:install`) and a snapshot of the site (files + database) is
  taken (`composer site:snapshot`). Every run starts by restoring that snapshot; every test starts from default
  preferences, empty log/access tables and no ban files.
* Different visitors are simulated by binding the client's source address (`127.0.0.2`, `127.0.0.3`, ...). The default
  "reliable IPs" setting exempts `127.0.0.1`, so the tests use other addresses for the "attacker".
* Preferences are changed in the database, then one harmless request refreshes Protector's cache, exactly like saving
  them in the control panel would.
* Rows marked **known defect** assert the *current, wrong* behaviour on purpose.
* After changing the module in the repository, run `composer site:install` and `composer site:snapshot` again so the
  site contains the new code.

Run it with:

```
composer test:functional            # about 100 seconds
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

