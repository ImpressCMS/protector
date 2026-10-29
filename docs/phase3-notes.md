# Phase 3: defects fixed

One commit per defect, each with a regression test (a functional scenario where the effect is visible from outside,
otherwise a unit test). The frozen suite changed only where a row pinned the defect (see `tests/functional/SPEC.md`).
Final state: 127 functional scenarios (260 assertions, 1 skipped on Windows) and 100 unit tests, all passing.

| Defect | Fix | Test |
|---|---|---|
| D1 group 1 IPs saved as line numbers | iterate the lines; store through `GroupOneIpList` | BAN-10, BAN-11 |
| D2 SQL built by string concatenation | prepared statements for the log, the access table and the preferences | `DatabaseRepositoriesTest`, `AuditLogTest` |
| D3 non-atomic state files, `unserialize()` without `allowed_classes` | `AtomicFile`, `StoredArray` (same approach as #7) | `StorageTest` |
| D4 `FILTER_SANITIZE_STRING`, `filter_input()` | `TextSanitiser` (same approach as #4) | `TextSanitiserTest` |
| D5 filter file name from the preference included unchecked | only `[A-Za-z0-9_]+.php` is executed | `FilterHandlerTest` |
| D6 unguarded array reads, `null` returns | resolved while decomposing in Phase 2 (`UploadGuard`, `LegacyFeatureGuard`, `BruteForceGuard`) | existing UPL, FEA and rate-limit rows |
| D7 `167777216` typo, discarded `array_filter()` | `IpComparator`; the `array_filter()` calls went with D1 | `FilterHandlerTest` |
| D8 DoS housekeeping on every request | expired rows collected on 1 request in 20; counts ignore expired rows | `DatabaseRepositoriesTest` |
| D9 precheck-stage events died with "No DB connection" | `AuditLog` writes through `Icms\Db\Factory::pdoInstance()`, which exists at `startCoreBoot` | SAN-01, SAN-13, SAN-16, UPL-02, FEA-03 |
| D10 "exit + ban" contamination never banned | ban registered before the request ends | SAN-04 |
| D11 HTMLPurifier filters crashed | `HTMLFilter::filterHTML()` | FLT-06, FLT-07 |
| D12 `$_REQUEST` kept the unsanitised value | compare with the original value | SAN-23, SAN-24 |
| D13 IPv6 clients broke the session hi-jack check | `IpMovement` compares packed addresses (same approach as #6) | `IpMovementTest` |

## Behaviour changes to be aware of

* **D10.** The ban for "exit + ban" is registered in the precheck stage, where the session is not known yet, so members of
  the "never ban" groups are banned too. The "ban only" action still waits for the postcheck stage and respects them.
* **D9.** Records for NUL bytes, `../`, bad uploads, contamination and `xmlrpc.php` now appear in the log, and requests
  with the default settings are served or stopped as configured instead of ending in an error message. A site that
  upgrades will start to see these records.
* **D4.** Backslashes are encoded in the stored request URI and user agent, because they were interpolated into SQL.
* **D2.** The log description is stored as written, no longer with `addslashes()`.
* **D8.** The `protector_access` table can grow slightly between collections.

## Left for later phases

* A composite index on `protector_access (ip, request_uri, expire)` needs the update hook (Phase 5).
* The admin page still builds its own `DELETE` statements with an integer cast (Phase 4 replaces the page).
* The config cache file name still uses a truncated md5 (D7, cosmetic).
