# Security Scan Report — REDCap v15.5.36

**Scan type:** Static analysis (source code review)
**Date:** 2026-02-26
**Scope:** PHP files in `Classes/`, `Controllers/`, `Config/`, `DataEntry/`, `Surveys/`, `Calendar/`, `ControlCenter/`, `Design/`, `ExternalLinks/`, `MyCap/`, `SharedLibrary/`, `AI/`
**Excluded:** `vendor/`, `node_modules/`, `ExternalModules/example_modules/`, `Resources/webpack/css/tinymce/`, `Libraries/vendor/`

---

## Summary

| Category | Instances | Severity |
|---|---|---|
| SQL Injection | 31 | Critical |
| Cross-Site Scripting (XSS) | 28 | High |
| Insecure CORS | 4 | High |
| Remote Code Execution via `eval()` | 4 | High |
| Insecure Direct Object Reference / JSON | 6 | Medium |

---

## Critical — SQL Injection (31 instances)

Numeric ID parameters from `$_GET`/`$_POST` are concatenated directly into SQL strings rather than being passed as parameterized query arguments. REDCap's `db_query()` supports parameterized queries (`db_query("... where id = ?", [$id])`); the pattern below should be replaced throughout.

### Surveys

| File | Line | Vulnerable Code |
|---|---|---|
| `Surveys/invite_participants.php` | 60 | `db_query("... survey_id = " . $_GET['survey_id'])` |
| `Surveys/participant_export.php` | 22 | `db_query("... survey_id = " . $_GET['survey_id'])` |
| `Surveys/email_self.php` | 52 | `db_query("... survey_id = " . $_GET['survey_id'])` |
| `Surveys/participant_list.php` | 22 | `db_query("... survey_id = " . $_GET['survey_id'])` |
| `Surveys/edit_participant.php` | 13 | `db_query("... survey_id = " . $_GET['survey_id'])` |
| `Surveys/survey_online.php` | 63 | `db_query("update ... survey_id = {$_GET['survey_id']}")` — UPDATE |

### Calendar

| File | Line | Vulnerable Code |
|---|---|---|
| `Calendar/calendar_popup.php` | 35 | `db_query("... cal_id = {$_GET['cal_id']} ...")` |
| `Calendar/calendar_popup_ajax.php` | 47 | `"update ... cal_id = {$_GET['cal_id']} ..."` — `event_time` is escaped, `cal_id` is not |
| `Calendar/calendar_popup_ajax.php` | 59 | `"update ... cal_id = {$_GET['cal_id']} ..."` — same pattern |
| `Calendar/calendar_popup_ajax.php` | 71 | `"update ... cal_id = {$_POST['cal_id']} ..."` — UPDATE via POST |

### ControlCenter

| File | Line | Vulnerable Code |
|---|---|---|
| `ControlCenter/movedata.php` | 59 | `"INSERT INTO $newDataTable SELECT * FROM redcap_data WHERE project_id = {$_GET['project']}"` |
| `ControlCenter/movedata.php` | 62 | `"UPDATE redcap_projects SET data_table = ... WHERE project_id = {$_GET['project']}"` |
| `ControlCenter/movedata.php` | 71 | `db_query("select data_table ... where project_id = ".$_GET['project'])` |
| `ControlCenter/user_controls_ajax.php` | 121 | `"update redcap_user_information set allow_create_db = {$_GET['allow_create_db']} ..."` |
| `ControlCenter/project_templates_ajax.php` | 255 | `"delete from redcap_projects_templates where project_id = ".$_POST['project_id']` |

### DataAccessGroups

| File | Line | Vulnerable Code |
|---|---|---|
| `Classes/DataAccessGroups.php` | 101 | `db_query("... group_id = {$_POST['item']}")` |
| `Classes/DataAccessGroups.php` | 108 | `db_query("... dag_id = {$_POST['item']} ...")` |
| `Classes/DataAccessGroups.php` | 197 | `"update ... set group_id = {$_POST['group_id']} ..."` |

### FileRepository

| File | Line | Vulnerable Code |
|---|---|---|
| `Classes/FileRepository.php` | 77 | `"select ... where folder_id = {$_POST['delete']} ..."` |
| `Classes/FileRepository.php` | 80 | `"update ... where folder_id = {$_POST['delete']} ..."` |
| `Classes/FileRepository.php` | 164 | `"update ... where folder_id = {$_POST['folder_id']} ..."` |

### ExternalLinks

| File | Line | Vulnerable Code |
|---|---|---|
| `ExternalLinks/save_resource_users_ajax.php` | 36 | `"update ... where ext_id = " . $_POST['ext_id']` |
| `ExternalLinks/save_resource_users_ajax.php` | 76 | `"update ... where ext_id = " . $_POST['ext_id']` |
| `ExternalLinks/save_resource_users_ajax.php` | 114 | `"update ... where ext_id = " . $_POST['ext_id']` |
| `ExternalLinks/excluded_projects_ajax.php` | 35 | `"insert ... values ({$_POST['ext_id']}, $pid)"` |

### MyCap

| File | Line | Vulnerable Code |
|---|---|---|
| `MyCap/edit_task.php` | 22 | `db_query("... task_id = " . $_GET['task_id'])` |
| `MyCap/tasks_list.php` | 24 | `db_query("... project_id = ".$_GET['pid']." ...")` |

### Core / DataEntry / Design

| File | Line | Vulnerable Code |
|---|---|---|
| `Config/init_functions.php` | 1165 | `db_query("... event_id = " .$_GET['event_id'])` — in core bootstrap |
| `DataEntry/change_record_dropdown.php` | 7 | `db_query("update ... show_which_records = {$_GET['show_which_records']} ...")` |
| `Design/define_events_ajax.php` | 28 | `db_query("delete ... event_id = ".checkNull($_GET['event_id']))` |
| `Design/define_events_ajax.php` | 30 | `db_query("delete ... event_id = ".checkNull($_GET['event_id']))` |

### Recommended Fix

```php
// Before (vulnerable)
$q = db_query("SELECT * FROM redcap_surveys WHERE project_id = $project_id AND survey_id = " . $_GET['survey_id']);

// After (safe)
$q = db_query("SELECT * FROM redcap_surveys WHERE project_id = ? AND survey_id = ?", [$project_id, $_GET['survey_id']]);
```

---

## High — Cross-Site Scripting (XSS) (28 instances)

`$_GET`/`$_POST` values are echoed directly into HTML attributes and JavaScript contexts without encoding. Attackers can inject arbitrary script via crafted URLs or form submissions.

### Surveys

| File | Line | Vulnerable Code |
|---|---|---|
| `Surveys/invite_participants.php` | 465 | `onclick="sendSelfEmail(<?php echo $_GET['survey_id'] ?>, ...)"` |
| `Surveys/invite_participants.php` | 482 | `onclick="getShortUrl('...', <?php echo $_GET['survey_id'] ?>)"` |
| `Surveys/invite_participants.php` | 483 | `onclick="customizeShortUrl('...', <?php echo $_GET['survey_id'] ?>, <?php echo $_GET['arm_id'] ?>)"` |
| `Surveys/invite_participants.php` | 791 | `var survey_id = <?php echo $_GET['survey_id'] ?>;` |
| `Surveys/invite_participants.php` | 792 | `var event_id = <?php echo $_GET['event_id'] ?>;` |
| `Surveys/return_code_widget.php` | 13 | `echo $_GET['s']` in href |
| `Surveys/return_code_widget.php` | 28 | `onclick="...?s=<?php echo $_GET['s'] ?>..."` |
| `Surveys/survey_info_table.php` | 1367 | `onclick="deleteSurvey(<?php echo $_GET['survey_id'] ?>)"` |
| `Surveys/view_results.php` | 169 | `value="<?php echo $_POST['results_code_hash'] ?>"` |

### DataEntry

| File | Line | Vulnerable Code |
|---|---|---|
| `DataEntry/index.php` | 1188 | `'&page=<?php echo $_GET['page'] ?>&id='+this.value` |
| `DataEntry/index.php` | 1203 | same pattern |
| `DataEntry/index.php` | 1218 | same pattern |
| `DataEntry/index.php` | 1860 | `var event_id = <?php echo $_GET['event_id'] ?>;` |
| `DataEntry/index.php` | 1861 | `var instance = <?php echo $_GET['instance'] ?>;` |
| `DataEntry/index.php` | 2105 | `$('#dc-icon-<?php echo $_GET['dqresfld'] ?>').click();` |

### ControlCenter

| File | Line | Vulnerable Code |
|---|---|---|
| `ControlCenter/user_api_tokens.php` | 67 | `'?pid=' + <?php echo $_GET['api_pid']; ?>` |
| `ControlCenter/user_api_tokens.php` | 102 | `project_id: '<?php echo $_GET['api_pid']; ?>'` |
| `ControlCenter/user_api_tokens.php` | 397 | `action: '<?php echo $_GET['action']; ?>'` |
| `ControlCenter/user_api_tokens.php` | 398 | `api_username: '<?php echo $_GET['api_username']; ?>'` |
| `ControlCenter/user_api_tokens.php` | 409-410 | multiple unescaped parameters in URL string |
| `ControlCenter/edit_project.php` | 126 | `action='...?project=<?php echo $_GET['project'] ?>'` |

### Design / MyCap / Other

| File | Line | Vulnerable Code |
|---|---|---|
| `Design/online_designer.php` | 3294 | `var form_name = '<?php echo $_GET['page'] ?>';` |
| `Design/online_designer.php` | 3356 | `openLogicBuilder('<?php echo $_GET['field']; ?>')` |
| `Design/online_designer.php` | 3362 | `openAddMatrix('<?php echo $_GET['field']; ?>', '')` |
| `Design/online_designer.php` | 3368 | `openAddQuesForm('<?php echo $_GET['field']; ?>', ...)` |
| `MyCap/task_info_table.php` | 513 | `deleteMyCapSettings(<?php echo $_GET['task_id'] ?>, '<?php echo $_GET['page'] ?>')` |
| `SharedLibrary/index.php` | 550-551 | `value="<?php print $_GET['pnid']?>"` and `value="<?php print $_GET['page']?>"` |
| `ProjectSetup/project_revision_history.php` | 377 | `var pid = '<?php echo $_GET['pid']; ?>';` |

### Recommended Fix

```php
// HTML attribute context
value="<?php echo htmlspecialchars($_GET['survey_id'], ENT_QUOTES, 'UTF-8') ?>"

// JavaScript variable context (numeric)
var survey_id = <?php echo (int)$_GET['survey_id'] ?>;

// JavaScript variable context (string)
var form_name = <?php echo json_encode($_GET['page']) ?>;
```

---

## High — Insecure CORS Configuration (4 instances)

### Finding 1 — Unconditional Wildcard Origin
**File:** `Classes/System.php:518`

```php
header("Access-Control-Allow-Origin: *");
```

Allows any origin to read responses. Acceptable only for fully public, unauthenticated endpoints.

### Finding 2 — Reflected Origin with Allow-Credentials (most severe)
**File:** `Controllers/BaseController.php:80–81`

```php
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
}
```

`HTTP_ORIGIN` is reflected unconditionally back to the caller **and** `Allow-Credentials: true` is set. Any attacker-controlled site can make authenticated cross-origin requests using the victim's session cookies. This is the highest-impact CORS misconfiguration pattern.

### Finding 3 — Reflected Allow-Headers
**File:** `Controllers/BaseController.php:92`

```php
if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
    header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
```

Request-supplied headers are reflected without validation.

### Finding 4 — Conditional Reflected Origin (System.php)
**File:** `Classes/System.php:529`

```php
if ($_SERVER['HTTP_ORIGIN'] != null && in_array($_SERVER['HTTP_ORIGIN'], $allowed_domains)) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
}
```

Lower risk due to the allowlist check, but the allowlist contents need to be verified (no wildcard subdomains).

### Recommended Fix

```php
$allowed = ['https://trusted.institution.edu', 'https://other.approved.org'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed, true)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
}
```

Never set `Allow-Credentials: true` alongside `Allow-Origin: *` or a reflected, unvalidated origin.

---

## High — Remote Code Execution via `eval()` (4 instances)

### Finding 1 — Calculated Field Formula Evaluation
**File:** `Classes/DataExport.php:7743`

```php
eval('$result = '.$actualFormula.';');
```

The regex guard on line 7742 checks for `[\[$]` but may be bypassable. If `$actualFormula` is derived from project-designer-supplied calculation fields, a privileged user could inject arbitrary PHP.

### Finding 2 — Branching Logic Dynamic Function
**File:** `Classes/LogicParser.php:365`

```php
eval("\$myFunction = function($argListCode){ $code };");
```

Conditional/branching logic entered by project designers is compiled to PHP via `eval()`.

### Finding 3 — Comparison Operator Evaluation
**File:** `Config/init_functions.php:4667`

```php
eval("\$value = $code;");
```

Dynamically evaluates a comparison expression. If `$code` construction can be influenced, RCE is possible.

### Finding 4 — Dynamic Function Builder
**File:** `Config/init_functions.php:5042`

```php
eval($eval_string);
```

The surrounding `try/catch` suppresses errors but does not prevent code execution.

### Recommended Fix

Replace `eval()` for expression evaluation with a sandboxed math/logic library. For branching logic, use a dedicated safe-expression evaluator rather than compiling to PHP. At minimum, add explicit sandboxing (e.g., disable dangerous functions in the evaluated string via an allowlist of permitted tokens before evaluation).

---

## Medium — Insufficient Input Validation / IDOR (6 instances)

| File | Line | Issue |
|---|---|---|
| `AI/translator.php` | 13 | `json_decode($_POST['texts'])` — no schema validation before use |
| `Controllers/FhirMappingHelperController.php` | 72 | `json_decode(@$_GET['options'], true)` — error-suppressed decode |
| `Controllers/FhirMappingHelperController.php` | 100 | `json_decode($_GET['options'] ?? [], true)` — unvalidated |
| `UserRights/search_user.php` | 125 | `json_decode($_GET['usernames'])` — no validation |
| `ExternalModules/manager/ajax/save-settings.php` | 18 | `json_decode($_POST['settings'], true)` — no schema validation |
| `ControlCenter/project_templates_ajax.php` | 255 | `"delete ... where project_id = ".$_POST['project_id']` — also SQL injection |

---

## Prioritization

| Priority | Action |
|---|---|
| P0 — Immediate | Fix `BaseController.php:80` reflected-origin + credentials CORS |
| P0 — Immediate | Parameterize all SQL queries identified above, starting with `Config/init_functions.php:1165` (core bootstrap) |
| P1 — Short term | Apply output encoding to all XSS instances — at minimum cast numeric params with `(int)` and use `json_encode()` for JS string contexts |
| P2 — Medium term | Audit and restrict all `eval()` call sites; replace with safe expression evaluators |
| P3 — Ongoing | Add schema validation after all `json_decode()` calls on user input |
