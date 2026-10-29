# The distributable package

`bin/build-package` (composer script `package`) builds a zip that is unzipped in the `modules/` folder of an installed
ImpressCMS 2.0.4 site, on top of the Protector 5.1 that ships with the core.

```
php bin/build-package [--ref=<git ref>] [--output=<file.zip or directory>] [--obsolete-from=<git ref>]... [--no-obsolete]
```

| Option | Default | Meaning |
|---|---|---|
| `--ref` | `HEAD` | The commit, branch or tag to package. Only committed content is used, never the working tree. |
| `--output` | `var/dist/protector-<version>-<commit>.zip` | A `.zip` file name, or a directory that receives the default file name. |
| `--obsolete-from` | `5.2` (then `origin/5.2`) | A ref with an older layout; its files that the new version does not ship are deleted by the update. Repeatable. |
| `--no-obsolete` | | Leave the list of obsolete files out. |

The version in the name is `root/modules/protector/include/version.txt` at the ref. Text files get LF line endings, the
zip carries Unix file modes, and the file times are the commit time.

## What is in the zip

One folder, `protector/`, so that a wrong place to unzip leaves one stray folder instead of loose files:

* the module, from `root/modules/protector/`;
* `include/trust_path/modules/protector/`, a copy of `trust_path/modules/protector/` of the repository (the two forwarding
  files the core preload includes, and the public check files). The repository keeps one copy; the build adds this one;
* `include/obsolete-files.txt`, generated: every file of the `--obsolete-from` layouts that the zip does not ship, one per
  line, prefixed `root:` (relative to the site root) or `trust:` (relative to the trust path). Never listed: anything
  under `configs/` (the ban lists of 5.x, which the update moves).

The build stops when a required file is missing, when a development file (tests, docs, `vendor`, `.git*`) would be
packaged, or when a file is both shipped and obsolete.

## What the update does

Unzipping replaces files only. The module's update hook (`Install\Updater`) finishes the job, in this order:

1. creates the data directory and moves the 5.x state files into it, adds the index of the access table;
2. places the files under `include/trust_path/` in `ICMS_TRUST_PATH/modules/protector/` (`Install\TrustPathFiles`);
   the installer does the same on a new install;
3. copies custom filters from `ICMS_TRUST_PATH/modules/protector/filters_byconfig|filters_enabled/` into the module's
   folders of the same name (`Install\LegacyFilterMigrator`): plain `name.php` files only, never over an existing file, and
   not the filters that 5.x shipped. The originals stay where they were until you delete them. A shipped filter that a
   site owner changed in place is not carried over;
4. deletes the files in `obsolete-files.txt` (`Install\ObsoleteFiles`), only those, then the directories they leave empty
   (a directory with just the core's placeholder `index.html` counts as empty). Entries that point outside
   `modules/protector/`, use `..`, or lie under `configs/` are ignored.

Until step 2 has run, the site still executes the 5.x code from the trust path; the update is therefore not optional. Right
after it the compiled copies of the replaced files are dropped from opcache.

## Verification

* `tests/unit/tests/PackageBuildTest.php` runs the script and checks the zip (folder, required and forbidden files, line
  endings, modes, obsolete list).
* `tests/unit/tests/PackageInstallTest.php` covers the three install classes.
* Scenario LIF-12 builds the package from `HEAD`, installs a site with the bundled 5.1, adds a custom filter, a changed
  preference, a log record and a ban, unzips the package, runs the module update and then checks that no file of the old
  installation is left except data, that the forwarding files are in place and that the filter, the data and the
  protection work. It needs the `5.2` branch for the obsolete list and is skipped without it.
