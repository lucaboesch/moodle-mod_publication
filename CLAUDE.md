# CLAUDE.md — mod_publication

## Overview

**mod_publication** ("Student Folder" / "Studierendenordner") is a Moodle activity module for **collecting and publishing student files and online texts** to everyone in a course. Files either get uploaded directly by students or are imported from an existing `mod_assign` activity. Publication of each file can be gated behind teacher approval and/or student (owner) approval, with a group-approval variant for team submissions.

- **Component**: `mod_publication`
- **Version**: v5.1.1 (`2026011401`), `MATURITY_STABLE`
- **Requires**: Moodle 5.1+ (`2025100600`)
- **Authors**: Hannes Laimer, Philipp Hager, Andreas Windbichler, Simeon Naydenov, Clemens Marx
- **Copyright**: Academic Moodle Cooperation (AMC)
- **License**: GNU GPL v3+
- **Purpose**: `MOD_PURPOSE_COLLABORATION`. No gradebook integration (`FEATURE_GRADE_HAS_GRADE` = false).
- **Supports**: groups, groupings, intro, show-description, idnumber, custom completion rules, view tracking, backup/restore.

## Common Commands

```bash
# PHP CodeSniffer (Moodle standard)
vendor/bin/phpcs --standard=moodle public/mod/publication/

# Build AMD modules
npx grunt amd --root=mod/publication

# PHPUnit (tests exist for this plugin)
vendor/bin/phpunit --filter mod_publication public/mod/publication/tests/

# Behat
vendor/bin/behat --tags @mod_publication
```

See the repo-root `.claude/moodle-project.md` for instance config and full command syntax.

## Modes (two distinct concepts — do not conflate)

**1. Stored DB mode** — `publication.mode` (int), what the teacher picked in the form:

| Constant | Value | Meaning |
|----------|-------|---------|
| `PUBLICATION_MODE_UPLOAD` | 0 | Students upload files directly |
| `PUBLICATION_MODE_IMPORT` | 1 | Files imported from a linked `mod_assign` |
| `PUBLICATION_MODE_ONLINETEXT` | 2 | Defined but **not** handled by the constructor — effectively unused |

**2. Runtime mode** — `publication::$mode` (string), computed in the constructor from the stored mode + whether the linked assign uses team submissions:

| Constant | Value | When |
|----------|-------|------|
| `PUBLICATION_MODE_FILEUPLOAD` | `'fileupload'` | stored mode = UPLOAD |
| `PUBLICATION_MODE_ASSIGN_TEAMSUBMISSION` | `'teamsubmission'` | IMPORT + assign has `teamsubmission` |
| `PUBLICATION_MODE_ASSIGN_IMPORT` | `'import'` | IMPORT + individual submissions |

Use `get_mode()` to read the runtime mode; read `get_instance()->mode` for the stored value.

## Approval Model

Files carry two approval states (`publication_file.teacherapproval`, `publication_file.studentapproval`):

- **Teacher approval** (`obtainteacherapproval`) — whether teachers must approve before a file is visible.
- **Student approval** (`obtainstudentapproval`) — whether the file's owner must consent to publication.
- **Group approval** (team submissions): the per-member votes live in `publication_groupapproval`; the cumulated result is cached back into `publication_file.studentapproval`. Strategy is `groupapproval`:
  - `PUBLICATION_APPROVAL_GROUPAUTOMATIC` (-1) — automatic
  - `PUBLICATION_APPROVAL_ALL` (0) — all members must approve
  - `PUBLICATION_APPROVAL_SINGLE` (1) — a single member suffices
- Approval is only accepted inside the `approvalfromdate` / `approvaltodate` window (`is_approval_open()`).

## Notifications

`publication.notifyfilechange` / `publication.notifystatuschange` use:

| Constant | Value |
|----------|-------|
| `PUBLICATION_NOTIFY_NONE` | 0 |
| `PUBLICATION_NOTIFY_TEACHER` | 1 |
| `PUBLICATION_NOTIFY_STUDENT` | 2 |
| `PUBLICATION_NOTIFY_ALL` | 3 |

Two notification kinds: `PUBLICATION_NOTIFY_STATUSCHANGE` (`'status'`) and `PUBLICATION_NOTIFY_FILECHANGE` (`'file'`). Notifications are **batched** in the static `publication::$pendingnotifications` array and flushed by calling `publication::send_all_pending_notifications()` (every action path that mutates files calls this). Message provider: **`publication_updates`** (`db/messages.php`).

## File-list Filters

`get_allfilestable($filter)` accepts: `nofilter`, `allfiles`, `approved`, `rejected`, `approvalrequired`, `nofiles` (`PUBLICATION_FILTER_*` constants).

## Database Schema (5 tables)

| Table | Purpose |
|-------|---------|
| `publication` | Main instance: dates (`allowsubmissionsfromdate`, `duedate`, `cutoffdate`, `approvalfromdate`/`approvaltodate`), `mode`, `importfrom` (linked assign id), approval flags, `groupapproval`, notify settings, `maxfiles`/`maxbytes`/`allowedfiletypes`, `autoimport`, `availabilityrestriction` |
| `publication_file` | One row per published file: `userid`, `fileid`, `filesourceid`, `filename`, `contenthash`, `type`, `teacherapproval`, `studentapproval` (last also caches cumulated group approval) |
| `publication_extduedates` | Per-user duedate extensions |
| `publication_groupapproval` | Per-member group-approval votes for team-submission imports (cumulated into `publication_file`) |
| `publication_overrides` | Per-user **or** per-group date overrides (`allowsubmissionsfromdate`, `duedate`, `approvalfromdate`, `approvaltodate`) |

## File Areas

Only one file area: **`attachment`** (in component `mod_publication`). `mod_publication_pluginfile()` serves it and gates on `mod/publication:view`.

## Capabilities (`db/access.php`)

| Capability | Default roles | Notes |
|------------|---------------|-------|
| `mod/publication:view` | guest, student, teacher, editingteacher, manager | Base gate for the activity **and** pluginfile |
| `mod/publication:addinstance` | editingteacher, manager | `RISK_XSS`, `CONTEXT_COURSE` |
| `mod/publication:upload` | student, teacher, editingteacher, manager | Upload files; also gates the "my files" panel |
| `mod/publication:approve` | teacher, editingteacher, manager | Approve/reject files, import, view all-files page, zip |
| `mod/publication:grantextension` | teacher, editingteacher, manager | Grant per-user duedate extensions |
| `mod/publication:manageoverrides` | teacher, editingteacher, manager | Manage date overrides |
| `mod/publication:receiveteachernotification` | teacher, editingteacher | Receive teacher-side notifications |

## Entry Points (Pages)

| File | Purpose | Who |
|------|---------|-----|
| `index.php` | List all publication instances in a course | All |
| `view.php` | Main view. Dispatches `action`: `zip`, `zipusers`, `zipfiles`, `import`, `grantextension`, `approveusers`/`rejectusers`/`resetstudentapproval`; handles `download`, `savevisibility`, and student-approval submit. `allfilespage=1` switches to the teacher all-files view (needs `:approve`). On the teacher view the bulk file actions (`zipfiles` + `approveusers`/`rejectusers`/`resetstudentapproval`) operate on the **per-file** checkboxes (`selectedfile[<fileid>]`) — not whole users/groups; only `grantextension` still reads `selecteduser[]`. | All (capability-gated branches) |
| `upload.php` | Student file upload | Students |
| `grantextension.php` | Grant extension form | Teachers |
| `onlinepreview.php` | Render online-text preview | All |
| `overrides.php` / `overrides_edit.php` / `overrides_delete.php` | List / edit / delete user & group date overrides | Teachers |
| `externallib.php` | AJAX WS `mod_publication_get_onlinetextpreview` (service `mod_publication_onlinetextpreview`) | All (needs `:view`) |

## Forms

`mod_form.php` (instance settings), `upload_form.php`, `mod_publication_files_form.php` (my files), `mod_publication_allfiles_form.php` (teacher all files), `mod_publication_grantextension_form.php`, `overrides_form.php`.

## Core Classes

| Class | File | Responsibility |
|-------|------|----------------|
| `publication` | `locallib.php` | **Main god-class (~2500 lines).** Instance access, file import from assign, zip download, approval logic, notifications, calendar events, overrides CRUD, group approval. **Global namespace** (not `mod_publication\...`) — loaded via `require_once locallib.php`; the observer does `use publication;` |
| `allfilestable\{base,group,import,upload}` | `classes/local/allfilestable/` | Teacher "all files" tables (`table_sql`), one subclass per mode |
| `filestable\{base,group,import,upload}` | `classes/local/filestable/` | Student "my files" tables, one subclass per mode |
| `observer` | `classes/observer.php` | Reacts to assign submit/remove, course-module-created, group membership changes |
| `custom_completion` | `classes/completion/custom_completion.php` | Rules `completionupload`, `completionassignsubmission` |
| `provider` | `classes/privacy/provider.php` | GDPR export/delete (covers all 5 tables) |
| `overview` | `classes/courseformat/overview.php` | Course activity-overview integration (5.1) |
| `dates` | `classes/dates.php` | Activity dates provider |
| `activity` | `classes/search/activity.php` | Global search |
| `report_editdates_integration` | `classes/report_editdates_integration.php` | `report_editdates` plugin hook |

## Event Observers (`db/events.php`)

| Observed event | Handler |
|----------------|---------|
| `mod_assign\event\assessable_submitted` | `observer::import_assessable` |
| `mod_assign\event\submission_removed` | `observer::import_assessable` |
| `core\event\course_module_created` | `observer::course_module_created` (auto-imports if new instance is IMPORT mode) |
| `core\event\group_member_added` | `observer::import_group_member_changed` |
| `core\event\group_member_removed` | `observer::import_group_member_changed` |

## Emitted Events (`classes/event/`, 13)

`course_module_viewed`, `course_module_instance_list_viewed`, `publication_file_uploaded`, `publication_file_imported`, `publication_file_deleted`, `publication_approval_changed`, `publication_duedate_extended`, plus `{user,group}_override_{created,updated,deleted}`.

## AMD Modules (`amd/src/`)

`alignrows`, `bulkuseractions`, `filesform`, `groupapprovalstatus`, `modform`, `onlinetextpreview`. Rebuild after edits with `grunt amd`.

## Templates (`templates/`, 6)

`overview`, `myfiles`, `overrides`, `approval_icon`, `approval_icon_fontawesome`, `approvaltooltip`.

## Admin Defaults (`settings.php`)

Site-wide defaults for new instances: `maxfiles`, `maxbytes`, `obtainteacherapproval`, `obtainstudentapproval`, `obtaingroupapproval`, `notifyfilechange`, `notifystatuschange`, `availabilityrestriction`, and the four date durations (`allowsubmissionsfromdate`, `duedate`, `approvalfromdate`, `approvaltodate`).

## Import Workflow (IMPORT mode)

1. Teacher creates an instance with `mode = IMPORT` and `importfrom` = an `mod_assign` id.
2. `publication::importfiles()` pulls submitted files (and online texts) from the assignment into `publication_file`.
3. Observers re-run the import automatically when students submit/remove assignment work (`import_assessable`) and on instance creation (`course_module_created`).
4. If the assign uses team submissions, runtime mode becomes `teamsubmission` and group approval applies; group-membership changes re-sync via `import_group_member_changed`.
5. Teachers toggle visibility / approval; pending notifications flush at the end of each action.

## Tests

- PHPUnit: `tests/allfilestable_test.php`, `tests/privacy_test.php`, generator in `tests/generator/lib.php`.
- Behat: `tests/behat/{publication,filesubmissions,overview_report}.feature`.
- Test helper classes live under `classes/local/tests/` (`base`, `publication`).

## Gotchas

- **`db/messages.php` has the wrong file-header** (`mod_checkmark`) — copy-paste leftover, harmless.
- **`publication` is in the global namespace**, unlike the PSR-4 classes under `classes/`. Always `require_once locallib.php` before using it.
- **Stored `mode` vs runtime `$mode`** are different types and value sets (see Modes above) — a frequent source of confusion.
- `PUBLICATION_MODE_ONLINETEXT` (2) is defined but the constructor never maps it to a runtime mode.
- Group approval is **cached** into `publication_file.studentapproval`; call `check_and_update_group_approval()` (done in `view.php` for team-submission mode) to keep it fresh.
- No gradebook integration — this module publishes files, it does not grade them.
