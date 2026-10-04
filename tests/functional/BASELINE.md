# Baseline run report (Phase 0)

Module code under test: the repository at branch `claude/protector-module-modernize-72f85b`, **unmodified** (commit
`3ebee91`), module version 5.1.0.

| | |
|---|---|
| Site | ImpressCMS 2.1.0 Beta, build 130, `C:\Users\david\sites\202`, `http://202.test` |
| Server | Laravel Herd: nginx + PHP 8.3.33 (FastCGI), MySQL 8.0.36 |
| Database | `protector_test` (prefix `ptest`), snapshot copy `protector_test_snapshot` |
| Suite | 123 scenarios, 231 assertions |

## Results

| Profile | Command | Result |
|---|---|---|
| `enabled` | `composer test:functional` | **123 tests, 231 assertions: 118 passed, 5 skipped, 0 failed** (two consecutive runs, identical) |
| `as-is` | `composer test:functional:as-is` | **4 tests, 11 assertions: all passed** (these document the unpatched module) |

Duration: about 100 seconds for the full suite. The slowest scenarios are DOS-03 (about 10 s, the module sleeps by
design), BAN-05 (about 4 s, waits for a ban to expire) and the lifecycle scenarios (about 4 s each, they restore the
snapshot afterwards).

### Skipped scenarios (5)

| Scenario | Reason |
|---|---|
| ASI-01 to ASI-04 | only meaningful in the `as-is` profile; reported as skipped in the default profile |
| MAN-01 | cannot run on Windows: the module compares `$_SERVER['SCRIPT_FILENAME']` (backslashes) with `ICMS_ROOT_PATH` (slashes). It would run on Linux. |

### Determinism

No scenario was found to be flaky in the two full runs. Time-dependent scenarios use short, explicit limits (a ban
of 2 seconds, a 5-second sleep by design); none depends on the wall clock of the 60-second DoS window.

## What the baseline shows

See "What the baseline run found" at the top of [SPEC.md](SPEC.md). In short: **as shipped, Protector does nothing on
this ImpressCMS 2.1 build** (S7). The `enabled` profile applies three text substitutions to the *installed copy* so
the suite can record the module's intended behaviour; the repository is untouched.

## Decision needed before Phase 1

The plan assumed the module worked and that Phases 1-2 could be pure restructuring. It does not work, so there are two
honest options for the start of Phase 1:

1. **Recommended.** Phase 1 begins with one small, reviewable commit that applies the three enablement fixes (S7 class
   name, D4 `filter_input`, S11 signature) to the repository. From then on the `enabled` profile *is* the native
   behaviour and the `ASI-*` scenarios flip to assert the fixed behaviour. Everything else stays frozen.
2. Keep the repository as is until Phase 3 and run the refactoring against the patched copy. This keeps Phases 1-2
   "pure" but means the refactored code must reproduce the three patches by itself, which is the same work in a
   less visible place.

The scenario catalogue itself does not depend on this choice.
