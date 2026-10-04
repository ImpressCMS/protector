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
* **Phase 4** (admin UI rebuilt with Smarty templates, handler classes and the core security service) changed two
  rows with your approval: **ADM-06** and **ADM-07** now use the core's token field (`protector_admin_REQUEST`) and the
  module's own refusal message instead of the `XOOPS_G_TICKET` field and the "GTicket Error" page. Their intent is
  unchanged: nothing is deleted without a valid token. Scenario **LIF-09** (templates registered once, removed on
  uninstall) was added.
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


## Admin Pages

| ID | Given | When | Then | Known defect |
|---|---|---|---|---|
| ADM-01 | three log records exist (one of them with markup in its description) | the administrator opens the module's admin start page | the page lists all three records with user, address, type and description, shows the two IP list text areas, and escapes the markup |  |
| ADM-02 | three log records exist | the administrator asks for 1 record per page starting at the second | exactly the second record is listed |  |
| ADM-03 | three log records exist | the administrator ticks records 1 and 3 and submits "Remove" | those two records are deleted and record 2 remains |  |
| ADM-04 | three log records exist, two of them with the same address and type | the administrator submits "Compact log" | duplicates are removed keeping the newest of each address/type pair |  |
| ADM-05 | three log records exist | the administrator submits "Remove all" | the log is empty |  |
| ADM-06 | three log records exist | a delete-all form is submitted without its security token | nothing is deleted |  |
| ADM-07 | three log records exist | a delete-all form is submitted with an invalid security token | a refusal message is shown and nothing is deleted |  |
| ADM-08 | three log records exist | a guest opens the admin start page | access is refused with "only admin can access this area" |  |
| ADM-09 | default preferences | the administrator opens the advisory page | it lists the security advisories (trust path, allow_url_fopen, session.use_trans_sid, database prefix, mainfile and database layer patches) and the two attack-simulation links |  |
| ADM-10 | default preferences | the administrator opens the module's admin pages | the admin menu offers the start page, the advisory page and the preferences page |  |
| ADM-11 | default preferences | the administrator opens the module's preferences in the control panel | the page lists the module's settings (by their labels) |  |

## Bans And Ip Lists

| ID | Given | When | Then | Known defect |
|---|---|---|---|---|
| BAN-01 | the administrator lists 127.0.0.50 in the "bad IPs" form | that address and an unlisted one request a page | the listed address is shown the BAD_IP message with an expiry time; the unlisted one is served |  |
| BAN-02 | the administrator lists 127.0.0.52 with an expiry time in the future | that address requests a page | it is blocked |  |
| BAN-03 | the administrator lists 127.0.0.51 with an expiry time in the past | that address requests a page | the entry has expired and the page is served |  |
| BAN-04 | the administrator submits valid and invalid lines (a word, an over-long string) | the admin start page is shown again | only the valid addresses are kept in the list |  |
| BAN-05 | an address is banned for 2 seconds by an isolated-comment attack | it requests a page immediately and again after 4 seconds | it is blocked first and served after the ban has expired |  |
| BAN-06 | an address is both on the bad-IP list and matches "reliable IPs" | it requests a page | the ban wins (the bad-IP check runs before the reliable-IP exemption) |  |
| BAN-07 | Protector is switched off globally and an address is on the bad-IP list | that address requests a page | it is still blocked (the list is enforced before the global switch is looked at) |  |
| BAN-08 | the isolated-comment action bans, and the attacker is an administrator (group 1 is exempt from bans) | the administrator triggers it and then requests a page | the administrator is not banned but is logged out |  |
| BAN-09 | a bad-IP filter that redirects is enabled ("precommon_badip_redirection") | a banned address requests a page | it is redirected to the configured address instead of seeing the message |  |
| BAN-10 | the administrator saves a two-line "allowed IPs for group 1" list that contains the administrator's own address | the administrator requests a page from that address | the stored list holds the addresses, so the administrator is still served (D1 fixed) |  |
| BAN-11 | the administrator saves a one-line "allowed IPs for group 1" list that does not contain the administrator's address | the administrator requests a page | the restriction is applied and the administrator is refused (D1 fixed) |  |

## Content Checks

| ID | Given | When | Then | Known defect |
|---|---|---|---|---|
| SPM-01 | default preferences (guests may post up to 4 links) | a guest posts a message with 4 external links | the post reaches the page |  |
| SPM-02 | default preferences (guest limit 5) | a guest posts a message with 6 external links | the request is terminated with an empty page and a "URI SPAM" record with the score is logged |  |
| SPM-03 | default preferences | a guest posts a message with 6 BBCode links like [url=www.spam.example] | the request is terminated and logged |  |
| SPM-04 | default preferences | a guest posts a message with 10 links to the site itself | links to the own host are not counted and the post is accepted |  |
| SPM-05 | guest link limit set to 0 (off) | a guest posts a message with 20 external links | the post is accepted |  |
| SPM-06 | default preferences (the limit for logged-in users is off) | an administrator posts a message with 20 external links | the post is accepted |  |
| FLT-01 | the filter "postcommon_post_need_multibyte" is enabled | a guest posts 150 plain ASCII characters | the post is rejected with the "no multibyte characters" message and a "Singlebyte SPAM" record is logged |  |
| FLT-02 | the filter "postcommon_post_need_multibyte" is enabled | a guest posts 150 characters that include multibyte characters | the post is accepted |  |
| FLT-03 | the filter "prepurge_exit_message" is enabled and contamination ends the request | a request injects xoopsConfig[nocommon] | the filter's message is shown |  |
| FLT-04 | a third-party filter written as a function (protector_postcommon_post_zzmarker) is dropped into the filters directory and listed in the preferences | a guest posts a form | the filter ran and could change the posted data |  |
| FLT-05 | a third-party filter written as a class (protector_postcommon_post_zzclass extends ProtectorFilterAbstract) is dropped into the filters directory and listed in the preferences | a guest posts a form | the filter ran and could change the posted data |  |
| FLT-06 | the filter "postcommon_post_htmlpurify4everyone" is enabled | a guest posts a message longer than 32 characters | the page is served and the posted HTML has been purified: the script is gone, the harmless markup stays (D11 fixed) |  |
| FLT-07 | the filter "postcommon_post_htmlpurify4guest" is enabled | a guest posts a message longer than 32 characters | the posted HTML has been purified |  |
| FEA-01 | "disable features" at its default (XML-RPC and the old criteria bug) | a request posts uname=0, logging off | the request is terminated with an empty page |  |
| FEA-02 | "disable features" set to none | a request posts uname=0 | the request is served |  |
| FEA-03 | "disable features" at its default, logging at its default | a request asks for /xmlrpc.php | the request is terminated with an empty page and an xmlrpc record is logged (D9 fixed) |  |
| MAN-01 | the manipulation check is on | the site's front page is requested twice | the first request stores a fingerprint of the web root and index.php in the preferences; it stays unchanged on the second request |  |

## Database Trap And Output Check

| ID | Given | When | Then | Known defect |
|---|---|---|---|---|
| DBT-01 | default preferences | a request value containing UNION SELECT reaches an unescaped query | the query is stopped with "SQL Injection found", before it runs; the earlier UNION check has logged the request |  |
| DBT-02 | the visitor's address matches "reliable IPs" | the same request is made | the request-level checks are skipped but the query is still stopped, and the trap logs it as "SQL Injection" |  |
| DBT-03 | default preferences | a hostile value is escaped and quoted by the page before it is used in a query | the query runs normally |  |
| DBT-04 | default preferences | a plain number is used in the unescaped query | the query runs normally |  |
| DBT-05 | default preferences | an injection that contains none of the trap's trigger words ("1 or 1=1 -- ") is used in the unescaped query | the trap does not notice it and the query runs (documented limit of the heuristic) |  |
| DBT-06 | "enable DB layer trap" off | a request value containing UNION SELECT reaches an unescaped query | the query runs; the earlier UNION check still logs the request |  |
| DBT-07 | Protector switched off globally | a request value containing UNION SELECT reaches an unescaped query | the query runs and nothing is logged |  |
| DBT-08 | default preferences | a harmless request, then a request with a suspicious value, hit a page | the anti-injection marker constant is defined for both; the database alternative is installed only for the suspicious one |  |
| OUT-01 | default preferences | a script tag is passed in the query string and echoed unescaped into an HTML page | the whole page is replaced by "XSS found by Protector." |  |
| OUT-02 | default preferences | a payload shorter than 15 characters after "<" is echoed into an HTML page | the page is served (documented limit of the heuristic) |  |
| OUT-03 | default preferences | a script tag is echoed into a JSON response | non-HTML content types are not checked, so the response is served |  |
| OUT-04 | "enable Big Umbrella" off | a script tag is echoed into an HTML page | the page is served |  |
| OUT-05 | default preferences | a quote-based attribute injection payload is echoed into an HTML page | the whole page is replaced by "XSS found by Protector." |  |
| OUT-06 | a page defines BIGUMBRELLA_DISABLED before the core boots | a script tag is echoed into that page | the page is served |  |
| OUT-07 | default preferences | a script tag is passed but the page does not echo it | the page is served normally |  |

## Enablement

| ID | Given | When | Then | Known defect |
|---|---|---|---|---|
| ENA-01 | the module installed from the repository on ImpressCMS 2.1 | ordinary pages are requested | the postcheck stage runs: the 33 preferences are loaded from the database and the preference cache file is written (S7 fixed) |  |
| ENA-02 | isolated-comment action "sanitize" and UNION action "exit" | a request carries both an isolated comment and a UNION | the comment is closed, the UNION ends the request, and only the first event of the request is logged (one record per request): Protector protects (S7 fixed) |  |
| ENA-03 | a visitor from 127.0.0.2, whatever PHP's filter_input() returns in this environment | the visitor triggers a check that is logged | the record carries the visitor's address, taken from $_SERVER (D4 fixed) |  |
| ENA-04 | the module installed from the repository on ImpressCMS 2.1 | the database-trap class is compared with the core class it extends | its query() signature is compatible, so the trap can be loaded (S11 fixed) |  |

## Lifecycle

| ID | Given | When | Then | Known defect |
|---|---|---|---|---|
| LIF-01 | the module was installed by the ImpressCMS installer | the installation is inspected | the module is registered, active, at version 5.1.0; it has 33 preferences with the documented names, types and defaults (including dos_skipmodules, which the core uses to detect Protector); and the log and access tables exist |  |
| LIF-02 | the module was installed by the ImpressCMS installer | the files that connect Protector to the core are inspected | the preload file declares IcmsPreloadProtector and includes the module's pre- and post-check files |  |
| LIF-03 | the module is installed | the control panel dashboard is opened | it does not warn that Protector cannot be found |  |
| LIF-04 | the module is installed | the administrator uninstalls the module in the control panel | the module, its preferences and both tables are gone; the preload file is removed; the dashboard now warns that Protector cannot be found |  |
| LIF-05 | the module was uninstalled | the administrator installs it again | the module, its 33 preferences, both tables and the preload file are back |  |
| LIF-06 | a preference was changed to a non-default value and a log record exists | the administrator runs "update" for the module in the control panel | the changed preference, all 33 preferences and the log record are still there |  |
| LIF-07 | the module is installed and active | an ordinary page is requested | the page is served and Protector's runtime preference cache has been written in the module's data directory |  |
| LIF-09 | the module is installed | it is updated, uninstalled and installed again in the control panel | its two admin templates are registered exactly once after the installation, the update and the reinstallation, and are removed by the uninstallation |  |

## Log Record

| ID | Given | When | Then | Known defect |
|---|---|---|---|---|
| LOG-01 | default preferences | a guest from 127.0.0.2 with a known User-Agent triggers the isolated-comment check | one record is written with type ISOCOM, user 0, the address, the User-Agent, a description naming the value, and a time within the last minute |  |
| LOG-02 | default preferences | an administrator triggers the isolated-comment check | the record carries the administrator's user id |  |
| LOG-03 | log level 15 (kinds 1, 2, 4 and 8 only) | a guest triggers the isolated-comment check (kind 32) | nothing is logged |  |
| LOG-04 | log level 63 (kinds up to 32) | a guest triggers the isolated-comment check (kind 32) | it is logged |  |
| LOG-05 | default preferences | the same address triggers the isolated-comment check in two consecutive requests | only one record is written (an event identical to the newest record in address and type is not repeated) |  |
| LOG-06 | default preferences | two different addresses trigger the isolated-comment check one after the other | both are logged |  |

## Rate Limit

| ID | Given | When | Then | Known defect |
|---|---|---|---|---|
| DOS-01 | F5 limit of 3 requests, action "none" | one address requests the same page 6 times | every request is served; one DoS record is logged |  |
| DOS-02 | F5 limit of 3 requests, action "exit" | one address requests the same page 6 times | the first requests are served and the later ones end with an empty page; a DoS record is logged |  |
| DOS-03 | F5 limit of 3 requests, action "sleep" | one address requests the same page 6 times | every request is served but the over-limit ones are delayed by about 5 seconds each |  |
| DOS-04 | F5 limit of 3 requests, action "temporary ban" | one address floods the same page and then requests another page | the address is then shown the BAD_IP message with an expiry time |  |
| DOS-05 | F5 limit of 3 requests, action "permanent ban" | one address floods the same page and then requests another page | the address is shown the BAD_IP message with the maximum expiry date (year 2038) |  |
| DOS-06 | F5 limit of 3 requests, action ".htaccess deny" | one address floods the same page | a PROTECTOR block denying that address is written to the site's .htaccess, and the original is backed up (the harness removes both afterwards) |  |
| DOS-07 | crawler limit of 3 requests | one address requests 6 different pages | the first requests are served, the later ones end with an empty page; a CRAWLER record is logged |  |
| DOS-08 | crawler limit of 3 requests, the visitor's User-Agent matches the "welcomed crawlers" pattern (Googlebot) | it requests 6 different pages and the same page 6 times | every request is served, nothing is logged and no access record is kept for it |  |
| DOS-09 | the site's directory name is listed in "modules skipped by the DoS check" | one address requests the same page 6 times | every request is served and nothing is recorded |  |
| DOS-10 | the visitor's address matches "reliable IPs" | it requests the same page 6 times | every request is served and nothing is logged |  |
| DOS-11 | Protector switched off globally | one address requests the same page 6 times | every request is served and nothing is logged |  |
| DOS-12 | a page defines PROTECTOR_SKIP_DOS_CHECK before the core boots | one address requests that page 6 times | every request is served and nothing is logged |  |
| BWL-01 | bandwidth limit of 10 recorded accesses | one address makes 12 requests, then a different address requests a page | the other address is told the site is crowded (HTTP 503) |  |
| BWL-02 | bandwidth limit left at its default (0 = off) | one address makes 12 requests, then a different address requests a page | the other address is served |  |
| BRU-01 | brute-force limit of 3 | one address submits 6 failed logins, then requests a page | after the limit the address is banned, a BRUTE FORCE record is logged and the next page shows the BAD_IP message |  |
| BRU-02 | brute-force limit of 3, the visitor's address matches "reliable IPs" | it submits 6 failed logins, then requests a page | it is never banned |  |

## Request Sanitising

| ID | Given | When | Then | Known defect |
|---|---|---|---|---|
| SAN-01 | default preferences | a request tries to inject xoopsConfig[nocommon] | the request is terminated with the Protector message and a CONTAMI record is logged (D9 fixed) |  |
| SAN-02 | logging off, contamination action "none" | a request tries to inject xoopsConfig[nocommon] | Protector lets the request through and the core itself answers with a redirect |  |
| SAN-03 | logging off, contamination action "exit" | a request tries to inject xoopsConfig[nocommon] | the request is terminated with the Protector message |  |
| SAN-04 | logging off, contamination action "exit + temporary ban" | a request tries to inject xoopsConfig[nocommon], then the same address requests a normal page | the first request is terminated and the address is banned, so the second request gets the jail message (D10 fixed) |  |
| SAN-05 | isolated-comment action "none" | a request carries a value ending in an unterminated "/*" | the value is passed on unchanged and an ISOCOM record is logged |  |
| SAN-06 | isolated-comment action "sanitize" | a request carries a value ending in an unterminated "/*" | the comment is closed ("*/" appended) and an ISOCOM record is logged |  |
| SAN-07 | isolated-comment action "exit" | a request carries a value ending in an unterminated "/*" | the request is terminated with the Protector message and logged |  |
| SAN-08 | isolated-comment action "exit + temporary ban" | the attacker triggers it, then both the attacker and another address request a page | the attacker is shown the BAD_IP message with an expiry time; the other address is served normally |  |
| SAN-09 | isolated-comment action "exit + permanent ban" | the attacker triggers it, then requests a page | the attacker is shown the BAD_IP message |  |
| SAN-10 | union action "none" | a request carries "1 UNION SELECT 1" | the value is passed on unchanged and a UNION record is logged |  |
| SAN-11 | union action "sanitize" | a request carries "1 UNION SELECT 1" | the word UNION is rewritten to "uni-on" and a UNION record is logged |  |
| SAN-12 | union action "exit" | a request carries "1 UNION SELECT 1" | the request is terminated with the Protector message and logged |  |
| SAN-13 | default preferences | a request carries a NUL byte | the NUL byte is replaced by a space, the page is served and a NullByte record is logged (D9 fixed) |  |
| SAN-14 | logging off, NUL-byte sanitising on | a request carries a NUL byte | the NUL byte is replaced by a space |  |
| SAN-15 | NUL-byte sanitising off | a request carries a NUL byte | the value is passed on unchanged |  |
| SAN-16 | default preferences | a request carries "../../etc/passwd" | the value is rewritten, the page is served and a DirTraversal record is logged (D9 fixed) |  |
| SAN-17 | logging off, "../" elimination on | a request carries "../../etc/passwd" | the value is rewritten with a trailing " ." |  |
| SAN-18 | "../" elimination off | a request carries "../../etc/passwd" | the value is passed on unchanged |  |
| SAN-19 | the visitor's address matches "reliable IPs" | a request carries "../../etc/passwd" | the value is passed on unchanged |  |
| SAN-20 | "force integer on *id parameters" off | a request carries topic_id=12abc;-- | the value is passed on unchanged |  |
| SAN-21 | "force integer on *id parameters" on | a request carries topic_id=12abc;-- and name=zz | only characters [0-9a-zA-Z_-] remain in the *id parameter; other parameters are untouched |  |
| SAN-23 | "force integer on *id parameters" on | a request carries topic_id=12abc;-- | the combined request array holds the same cleaned value as the query string (D12) |  |
| SAN-24 | "../" elimination on | a request carries "../../etc/passwd" | the combined request array holds the same rewritten value as the query string (D12) |  |
| SAN-22 | Protector switched off globally | requests carry a NUL byte, an isolated comment and a UNION | all values are passed on unchanged and nothing is logged |  |

## Session And Group Access

| ID | Given | When | Then | Known defect |
|---|---|---|---|---|
| SES-01 | default preferences (24 significant address bits, group 1 protected) | an administrator logs in from 127.0.0.2 and the same browser then arrives from 127.0.1.2 (another /24 network) | the session is purged, the visitor is redirected to the home page and is a guest afterwards |  |
| SES-02 | default preferences | an administrator logs in from 127.0.0.2 and the same browser then arrives from 127.0.0.77 (same /24 network) | the session is kept |  |
| SES-03 | the list of protected groups is emptied | an administrator moves to another /24 network | the session is kept |  |
| SES-04 | "significant address bits" set to 0 | an administrator moves to another /24 network | the check is off and the session is kept |  |
| SES-05 | the allowed-IPs list for group 1 contains only 127.0.0.9 (written in the module's storage format, because the form cannot store it, see BAN-10/11) | an administrator logged in earlier requests a page from 127.0.0.2, and again from 127.0.0.9 | from the unlisted address the account is disabled with a message; from the listed address the administrator is recognised |  |
| SES-06 | the allowed-IPs list for group 1 ends with a dot (127.0.0.), meaning a prefix match | an administrator requests a page from 127.0.0.2 | the administrator is recognised |  |
| SES-07 | the allowed-IPs list for group 1 is empty | an administrator requests a page | the administrator is recognised (an empty list means "all addresses") |  |

## Upgrade

| ID | Given | When | Then | Known defect |
|---|---|---|---|---|
| LIF-08 | a site running the previous release (branch 5.2) with a changed preference, a log record and a banned address | the current files are copied over the installation without removing anything and the administrator runs "update" for the module | preferences, log record and ban list are kept; the banned address is still blocked; checks, SQL trap and both admin pages work with the new code |  |

## Upload

| ID | Given | When | Then | Known defect |
|---|---|---|---|---|
| UPL-01 | default preferences | a genuine PNG is uploaded as real.png | the upload reaches the page untouched |  |
| UPL-02 | default preferences | a PHP script is uploaded | the request is terminated with the Protector message and an UPLOAD record is logged (D9 fixed) |  |
| UPL-03 | logging off | a PHP script is uploaded | the request is terminated with the Protector message |  |
| UPL-04 | logging off | a file with two dots in its name (double.sneaky.png) is uploaded | the request is terminated with the Protector message |  |
| UPL-05 | logging off | a text file claiming to be a JPEG is uploaded | the request is terminated with the Protector message (the PHP warning it emits first is defect D6 and is not asserted) |  |
| UPL-06 | logging off | a PNG is uploaded under a .jpg name | the request is terminated with the Protector message because the extension does not match the content |  |
| UPL-07 | "die on bad extensions" off | a PHP script is uploaded | the upload reaches the page untouched |  |
| UPL-08 | the visitor's address matches "reliable IPs" | a PHP script is uploaded | the upload reaches the page untouched |  |

## Known-defect index

These scenarios assert today's behaviour although it is a defect. They are the only assertions that may change later, each in the phase that fixes the defect.

| Defect | Pinned behaviour | Scenarios |
|---|---|---|

_128 scenarios generated from the test attributes by `php tests/functional/bin/spec.php`._
