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
* **Phase 3** (defect fixes) flipped the rows listed under "Known defects" below and added three scenarios. No other
  assertion changed.

## Known defects

None are pinned any more. Phase 3 fixed the ones this suite recorded, each in its own commit, and flipped only the rows
that pinned them:

| Defect | Rows flipped |
|---|---|
| D1 the "group 1 allowed IPs" form stored line numbers | BAN-10, BAN-11 |
| D9 events raised before the database exists died with "No DB connection" | SAN-01, SAN-13, SAN-16, UPL-02, FEA-03 |
| D10 contamination with an "exit + ban" action never banned | SAN-04 |
| D11 the HTMLPurifier filters crashed | FLT-06 |

New rows added in Phase 3: SAN-23 and SAN-24 (D12, `$_REQUEST` follows the sanitised value) and FLT-07 (guest
HTMLPurifier filter). Defects without a pinned row (D2 to D8, D13) are covered by unit tests in `tests/unit/`.

## How the suite works

* The installer is driven over HTTP once (`composer site:install`) and a snapshot of the site (files + database) is
  taken (`composer site:snapshot`). Every run starts by restoring that snapshot; every test starts from default
  preferences, empty log/access tables and no ban files.
* Different visitors are simulated by binding the client's source address (`127.0.0.2`, `127.0.0.3`, ...). The default
  "reliable IPs" setting exempts `127.0.0.1`, so the tests use other addresses for the "attacker".
* Preferences are changed in the database, then one harmless request refreshes Protector's cache, exactly like saving
  them in the control panel would.
* A row marked **known defect** asserts the *current, wrong* behaviour on purpose (none at the moment).
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

