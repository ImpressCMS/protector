# Phase 2: decomposition of `Protector`

The 1000-line `Protector` class and the two bootstrap function files are replaced by small classes under `src/`.
Behaviour is unchanged: the frozen functional suite passes with no assertion changed (124 scenarios, 248 assertions,
1 skipped), and 54 unit tests cover the classes that need no site.

## Structure

| Namespace | Classes | Replaces |
|---|---|---|
| `Kernel` | composition root; `precheck()` / `postcheck()` run once each | `protector_prepare()`, `protector_postcommon()`, `Protector::getInstance()` |
| `Http` | `ServerRequest`, `Responder`, `ExitResponder` | the repeated `filter_var($_SERVER['REMOTE_ADDR'] ...)` calls and every `exit` / `die` |
| `Config` | `ProtectorConfig`, `ConfigStore` | `_conf`, `updateConfFromDb()`, `updateConfIntoDb()` |
| `Storage` | `DataPaths` | the four `get_filepath4*()` methods |
| `Ban` | `BanList`, `GroupOneIpList`, `IpMatcher`, `IpMatch`, `HtaccessWriter` | bad-IP and group-1 files, `ip_match()`, `deny_by_htaccess()` |
| `Dos` | `DosGuard`, `BruteForceGuard`, `AccessRepository`, `BandwidthLimiter`, `DosAction` | `check_dos_attack()`, `check_brute_force()`, the bandwidth file |
| `Request` | `RequestScanner`, `RequestMutator`, `IsolatedCommentGuard`, `UnionGuard`, `UploadGuard`, `IdValueSanitiser`, `DirectoryTraversalGuard`, `SpamGuard`, `ManipulationGuard`, `LegacyFeatureGuard` | the matching `check_*` / `disable_features()` methods |
| `Output`, `Database` | `XssUmbrella`, `DatabaseTrap`, `SqlInjectionGuard` | `bigumbrella_*`, `dblayertrap_*` |
| `Session` | `Visitor`, `SessionPurger`, `SessionHijackGuard` | `purge()` and the hijack block |
| `Log` | `AuditLog`, `LogLevel` | `output_log()`, `message`, `last_error_type`, the magic log levels |
| `Policy` | `ViolationPolicy` | the `& 1`, `& 2`, `& 4`, `& 8` action bitmasks |
| `Filter` | `FilterHandler` (no longer a singleton), `FilterAbstract` | unchanged contract for filters |
| `Legacy` | `ProtectorFacade` (global alias `Protector`) | see below |

`Kernel::boot()` is the only static. The core creates the SQL guard itself (`new XOOPS_DB_ALTERNATIVE()`), so
`SqlInjectionGuard` takes its dependencies from the kernel when no arguments are given.

## Filter compatibility

Filters in `filters_byconfig/` and third-party drop-ins still call `Protector::getInstance()` and read `_conf`,
`message`, `last_error_type` and `ip_matched_info`, or call `output_log()`. `Legacy\ProtectorFacade` keeps that contract
(including those four properties through `__get` / `__set`) and forwards to the new services. It also offers the
methods the old admin page used. It is the only code that may use the old names and is meant to be removed in a
major release.

## Deliberate differences

All are on failure paths that crashed before:

* A malformed `reliable_ips`, `bip_except` or `groups_denyipmove` value now reads as an empty list instead of a
  `TypeError` (the stripslashes fallback of the precheck stage applies in both stages).
* `HtaccessWriter` returns `false` when `.htaccess` cannot be opened for writing instead of a `TypeError`.
* The `findusers` legacy check skips array POST values instead of a `TypeError`.
* The "done once" flags of the individual checks are replaced by `Kernel` running each stage once.

## Defects found and left for Phase 3

* **D12.** `id_forceintval` and the directory traversal rewrite compare `$_REQUEST[$key]` with the already
  overwritten `$_GET[$key]`, so `$_REQUEST` keeps the unsanitised value whenever the value changed. Pinned by
  `RequestGuardsTest::testKnownDefectD12RequestKeepsTheUnsanitisedIdValue`.
* D9 (precheck-stage events end in "No DB connection") is still there on purpose: `AuditLog::write()` halts through the
  `Responder`, and the buffering fix belongs to Phase 3.
* `ServerRequest` is the single place that still uses `FILTER_SANITIZE_STRING` (D4), and `ConfigStore`, `BanList`
  and `GroupOneIpList` still use unguarded `serialize()` / `unserialize()` (D3).
