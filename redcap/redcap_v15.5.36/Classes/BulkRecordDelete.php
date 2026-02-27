<?php

class BulkRecordDelete
{

    public $records;

    public $canDelete = false;

    public $errors = array();
    public $notes = array();

    public $group_id = null;

    public $arm = null;
    public $arm_id = null;

    public const FETCH_RECORDS_LIMIT = 10000;

    public function init()
    {
        $this->validateUserRights();
        $this->setGroupId();
        $this->renderPage();
    }

    public function setGroupId()
    {
        global $user_rights;
        if ( !empty( \REDCap::getGroupNames() ) && !empty($user_rights['group_id']) ) {
            $this->group_id = $user_rights['group_id'];
        }
    }

    public function renderPage()
    {
        if ($this->canDelete) $this->handleDelete();
        if (!empty($this->errors) || !empty($_SESSION['rmd_errors'])) {
            $this->renderErrors();
        } else {
            $this->renderNotes();
        }
        $this->renderPageHeader();
    }

    public function renderPageHeader()
    {
        global $lang;
        print	RCView::div(array('style'=>'max-width:750px;', 'class'=>'mt-1 mb-4'),
                    RCView::div(array('style'=>'color:#800000;', 'class'=>'fs16 float-left font-weight-bold'),
                        RCView::fa('fas fa-times-circle fs15 mr-1') . $lang['data_entry_619']
                    ) .
                    RCView::div(array('class'=>'clear'), '')
                ) .
                RCView::p(['class'=>'mb-4'],
                    $lang['data_entry_621'] . RCView::br() .
                    RCView::span(['class'=>'text-dangerrc boldish'], RCView::fa('fa-solid fa-circle-exclamation mr-1') . $lang['data_entry_649'])
                );
    }


    public function renderIndexPage()
    {
        extract($GLOBALS);
        session_start();

        if ($GLOBALS['bulk_record_delete_enable_global'] != '1') {
            redirect(APP_PATH_WEBROOT."index.php?pid=".PROJECT_ID);
        }

        $_SESSION['selected_arm'] = $_POST['arm-option1'] ?? ($_SESSION['selected_arm']??"");
        if (isset($_GET['toggle-delete-forms-record'])) {
            $_SESSION['selected_arm'] = $_POST['arm-option2'] ?? ($_SESSION['selected_arm']??"");
        }
        if (isset($_POST['form_event_ajax'])) {
            $_SESSION['form_event_data'] = $_POST['form_event'] ?? null;
            echo json_encode(['status' => 'success']);
            exit;
        }

        include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

        // CSS and JS
        loadCSS('BulkRecordDelete.css');
        loadJS('BulkRecordDelete.js');
        addLangToJS(['data_entry_652', 'data_entry_653', 'data_entry_654', 'data_entry_70']);
        // Tabs
        include APP_PATH_DOCROOT . "ProjectSetup/tabs.php";;

        $this->init();
        $this->redirect();
        $hasArms = $Proj->longitudinal && $Proj->multiple_arms;
        $recordsExist = Records::getRecordCount($Proj->getId()) > 0;

        $delLoggingMsg = "";
        if ($Proj->project['allow_delete_record_from_log']) {
            $delLoggingMsg = RCView::div(array('id' => 'allow_delete_record_from_log_parent', 'style' => 'padding:5px;padding-left: 25px;border:1px solid #eee;background-color:#fafafa;text-indent: -1.4em;margin-top:20px;color:#555;'),
                RCView::checkbox(array('id' => 'allow_delete_record_from_log')) .
                RCView::label(['for' => 'allow_delete_record_from_log', 'class' => 'd-inline'], RCView::tt("data_entry_436", "b") . RCView::br() . RCView::tt("data_entry_437"))
            );
        }

        $changeReasonMsg = "";
        if ($Proj->project['require_change_reason']) {
            $changeReasonMsg =
                RCView::div(array('id'=>'change_reason_div'),
                    RCView::div(array('class'=>'font-weight-bold mt-3 mb-1'),
                        RCView::tt("data_entry_69")
                    ) .
                    RCView::textarea(array('id'=>'change-reason', 'class'=>'x-form-textarea x-form-field', 'style'=>'width:400px;height:120px;'))
                );
        }

        ?>
        <script type="text/javascript">
          var fetchLimit = <?php echo \BulkRecordDelete::FETCH_RECORDS_LIMIT; ?>;
          var langRMD = {
            confirm_deletion: '<?php echo js_escape($lang['data_entry_640']) ?>',
            delete_message_instructions: '<?php echo js_escape(join(' ', [$lang['data_entry_646'], '"' . $lang['edit_project_48'] . '"', $lang['edit_project_140'], $delLoggingMsg, $changeReasonMsg])); ?>',
            delete_forms_warning: '<?php echo js_escape($lang['data_entry_641']) ?>',
            delete_records_warning: '<?php echo js_escape($lang['data_entry_642']) ?>',
            confirm_delete_txt: '<?php echo js_escape(join(' ', [$lang['edit_project_47'] , '"' . $lang['edit_project_48'] . '"' , $lang['edit_project_49']])); ?>',
            message_invalid_records_detected: '<?php echo js_escape($lang['data_entry_643']) ?>',
            message_navigation_warning: '<?php echo js_escape($lang['data_entry_645']) ?>',
            previous: '<?php echo js_escape($lang['datatables_11']) ?>',
            next: '<?php echo js_escape($lang['datatables_10']) ?>',
            continue: '<?php echo js_escape($lang['multilang_690']) ?>'
          };
        </script>
        <?php
        $tabs = array();
        $url = APP_PATH_WEBROOT . "index.php?route=BulkRecordDeleteController:index&pid=" . PROJECT_ID;
        // Tab to enter custom list of records to delete
        $tabs[ $url . '&view=custom-list'] =
            RCView::span(array('style'=>'vertical-align:middle;'), $lang['data_entry_631']);
        // Tab to select records to delete manually
        $tabs[ $url . '&view=record-list'] =
            RCView::span(array('style'=>'vertical-align:middle;'), $lang['data_entry_630']);
        // Default to custom-list
        if (empty($_GET['view'])) $_GET['view'] = 'custom-list';
        $view = isset($_GET['view']) ? htmlspecialchars($_GET['view'], ENT_QUOTES) : "na";
        // delete entire record or just some forms?
        $checked = 'checked';
        $unChecked = '';
        $statusRadio1 = !isset($_GET['toggle-delete-forms-record']) ? $checked : $unChecked;
        ?>
        <div class="container" style="margin-left: 0;">
            <form method="POST" class="delete_records">
                <div style="margin:5px 0 0;">
                    <div class="row">
                        <div class="col">
                            <?php echo RCView::p(['style' => 'font-weight:bold;'], $lang['data_entry_634'])?>
                            <ul style="list-style: none; padding-left: 12px">
                                <li>
                                    <?php
                                    echo RCView::radio(array('id' => 'toggle-delete-entire-record', 'name' => 'toggle-delete-entire-record', 'class' => 'align-middle toggle-delete', 'onclick' => "document.getElementById('toggle-delete-forms-record').checked = false; modifyURL(removeParameterFromURL(window.location.href,'toggle-delete-forms-record'));", $statusRadio1 => $statusRadio1)) .
                                        RCView::label(array('for' => 'toggle-delete-entire-record', 'style' => 'font-size:13px;color:#393733;'), $lang['data_entry_638']);
                                    ?>
                                    <?php echo $this->displayArmsDropdownSelect(false) ?>
                                </li>
                                <li>
                                    <?php
                                    $statusRadio2 = $statusRadio1 === $checked ? $unChecked : $checked;
                                    echo RCView::radio(array('id' => 'toggle-delete-forms-record', 'name' => 'toggle-delete-forms-record', 'class' => 'align-middle toggle-delete', 'onclick' => "document.getElementById('toggle-delete-entire-record').checked = false; modifyURL(window.location.href+'&toggle-delete-forms-record=1');", $statusRadio2 => $statusRadio2)) .
                                        RCView::label(array('for' => 'toggle-delete-forms-record', 'style' => 'font-size:13px;color:#393733;'), $lang['data_entry_639']);
                                    ?>
                                    <?php echo $this->displayArmsDropdownSelect(true) ?>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col">
                            <?php echo RCView::p(['style' => 'font-weight:bold;'], $lang['data_entry_635'])?>
                            <ul style="list-style: none; padding-left: 12px">
                                <?php
                                foreach ($tabs as $this_url => $this_label) {
                                    $qs = parse_url($this_url, PHP_URL_QUERY);
                                    parse_str($qs, $these_param_pairs);
                                    $this_view = $these_param_pairs['view'];
                                    $radioId = "radio-" . $this_view;
                                    $checked = $this_view == $_GET['view'] ? 'checked' : '';
                                    ?>
                                    <li >
                                        <?php
                                        echo RCView::radio(array('id' => $radioId, 'class' => 'align-middle', 'value' => $this_url, $checked => $checked)) .
                                            RCView::label(array('for' => $radioId, 'style' => 'font-size:13px;color:#393733;'), $this_label);
                                        ?>
                                    </li>
                                    <?php
                                }
                                ?>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="clear"></div>
                <?php if ($recordsExist) : ?>
                    <div id="<?= $view ?>" class="row" >
                        <div class="col">
                            <input type="hidden" name="result" value="delete" >
                            <input type="hidden" name="group_id" value="<?= $this->group_id ?>">
                            <input type="hidden" name="mode" value="<?= $view ?>">
                            <?php echo RCView::p(['style' => 'font-weight:bold;display:inline-block;'], $view == 'custom-list' ? str_replace('{0}', $lang['data_entry_657'], $lang['data_entry_637']) : str_replace('{0}', $lang['data_entry_658'], $lang['data_entry_637']))?>
                            <span id="count-scheduled-for-deletion" style="border:1px solid red;color:red;padding: 5px;margin-left: 10px;">0</span>
                            <?php if( $view == 'custom-list' ): ?>
                                <div class="form-group" style="padding-top:0">
                                    <small id="validateHelpBlock" class="form-text text-muted">
                                        <?php echo $lang['data_entry_625'] ?>
                                    </small>
                                    <div class="input-group mt-2">
                                        <textarea class="form-control list-input-step"  rows="10" aria-label="With textarea"></textarea>
                                    </div>
                                    <small id="invalidInputBlock" class="invalid-feedback"></small>
                                    <small id="validInputBlock" class="valid-feedback">
                                        <?php  echo $lang['data_entry_626'] ?>
                                    </small>
                                </div>
                                <div class="form-group" style="padding-top:0;">
                                    <ul id="custom-output"></ul>
                                </div>
                            <?php elseif( $view == 'record-list' ):
                                echo RCView::div(array('style'=>'padding:0 0 10px;'),
                                    RCView::div(array('class'=>'form-text text-muted mb-1'),
                                        $lang['data_entry_647']
                                    ).
                                    RCView::span([],
                                        RCView::a(array('href'=>'javascript:;', 'onclick'=>'selectAllRecords(true)', 'style'=>'font-size:11px;text-decoration:underline;margin: 0 10px 0 5px;'),$lang['ws_35']).
                                        RCView::a(array('href'=>'javascript:;', 'onclick'=>'selectAllRecords(false)', 'style'=>'font-size:11px;text-decoration:underline;'),$lang['ws_55'])
                                    )
                                );
                                ?>
                                <div class="form-group" style="padding-top:0;">
                                    <div class="card">
                                        <div class="card-body" style="padding:0 1.25rem;">
                                            <div id="spinner-container" style="display: none; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color:rgb(92, 99, 106); z-index:1;">
                                                <div class="spinner-border text-medium" role="status">
                                                    <span class="visually-hidden">Loading...</span>
                                                </div>
                                            </div>
                                            <label for="searchBox"></label>
                                            <input type="text" id="searchBox" placeholder="<?=RCView::tt_js2('data_entry_648')?>" style="margin-bottom: 5px;">
                                            <div id="infoText" style="visibility:hidden;" class="text-secondary mb-2 fs11"><i class="fas fa-circle-info"></i> <?php echo $lang['data_entry_644'];?></div>
                                            <div id="record-list-wrapper" class="wrapper">
                                                <ul id="record-output">
                                                    <?php
                                                    ob_start();
                                                    if ($hasArms) $selectedArm = $_SESSION['selected_arm'];
                                                    $this->fetchRecords($selectedArm ?? null, $this->group_id, $this::FETCH_RECORDS_LIMIT + 1); // fetching 1 more than the max allowed per page to determine whether we should display `Next` button.
                                                    $records = json_decode(ob_get_clean(), true);
                                                    $total = 0;
                                                    $showNext = false;
                                                    foreach ($records['records'] as $chunkIndex => $chunk) {
                                                        $total += count($chunk);
                                                        if ($total > $this::FETCH_RECORDS_LIMIT) {
                                                            array_pop($chunk);
                                                            $showNext = true;
                                                        }
                                                        foreach ($chunk as $record => $label) {
                                                            $displayName = ' ';
                                                            $displayName .= $record . ($label == '' ? "" : " ".$label);
                                                            echo '<li>';
                                                            echo '<label>';
                                                            echo '<input type="checkbox" name="records[]" value="' . RCView::escape($record,false) . '">';
                                                            echo RCView::escape($displayName,false);
                                                            echo '</label>';
                                                            echo '</li>';
                                                        }
                                                    }
                                                    ?>
                                                </ul>
                                            </div>
                                            <?php if ($showNext):?>
                                                <div style="position: absolute; bottom: 10px; right: 10px;">
                                                    <button id="prevPage" class="btn btn-secondary" style="display:none;">← Previous</button>
                                                    <button id="nextPage" class="btn btn-secondary">Next →</button>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div class="form-group" style="padding-top: 0">
                                <button id="btn-delete-selection" class="btn btn-danger">
                                    <i class="fas fa-trash-alt"></i> <?php echo $lang['global_19'] ?>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="text-dangerrc fs15"><i class="fa-solid fa-circle-info"></i> <?php echo $lang['data_entry_632'] ?></p>
                <?php endif; ?>
            </form>
        </div>
        <?php
        include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
    }

	private function displayArmsDropdownSelect(bool $partialDelete)
	{
		global $lang, $Proj;

		$id = $partialDelete ? 'option2' : 'option1';
		$selectedArm = $_SESSION['selected_arm'] ?? $Proj->firstArmNum;
		$recordsByArms = Records::getArmsForAllRecords($Proj->getId());

		// If selected arm is invalid, default to first arm
		if (!in_array($selectedArm, array_keys($recordsByArms))) {
			$selectedArm = $Proj->firstArmNum;
			$_SESSION['form_event_data'] = null; // Reset session selection
		}

		$style = (!$partialDelete && !$Proj->multiple_arms) ? 'display: none;' : '';
		$style2 = !$Proj->multiple_arms ? 'display: none;' : '';

		$html = "<div class=\"form-group form-event-list-wrapper-{$id}\" style=\"display:none; padding-top:0;margin-left:20px;\">\n";

		if ($partialDelete) {
			$html .= "<span class=\"d-block small text-muted\">{$lang['data_entry_659']}</span>\n";
		}

		$html .= "<div class=\"toggle-ribbon\" style=\"{$style}\">\n";
		$html .= "<span class=\"ribbon-text\">\n";
		$html .= $partialDelete ? $lang['data_entry_636'] : $lang['data_entry_704'];

		if ($partialDelete) {
			$html .= " <span id=\"triangle\">&#9660;</span>";
		}

		$html .= "</span>\n";
		$html .= "<div id=\"longitudinal-arms-list-{$id}\" style=\"padding-left:12px;\">\n";
		$html .= "<div style=\"padding-top:10px;{$style2}\">\n";
		$html .= "<label for=\"arm-select-{$id}\">{$lang['data_entry_705']}</label>\n";
		$html .= "<select id=\"arm-select-{$id}\" class=\"arm-select custom-select ms-1\" name=\"arm-{$id}\">\n";

		foreach ($Proj->events as $arm_num => $arm_detail) {
			$selected = ($arm_num == $selectedArm) ? 'selected' : '';
			$label = "{$lang['global_08']} {$arm_num}{$lang['colon']} {$arm_detail['name']}";
			$html .= "<option value=\"{$arm_num}\" {$selected}>{$label}</option>\n";
		}

		$html .= "</select>\n";
		$html .= "</div>\n";

		if ($partialDelete) {
			$formEventListHtml = $this->getFormEventList($selectedArm);
			$html .= "<div id=\"form-event-list-wrapper\">\n{$formEventListHtml}\n</div>\n";
		}

		$html .= "</div>\n"; // longitudinal-arms-list
		$html .= "</div>\n"; // toggle-ribbon
		$html .= "</div>\n"; // form-group

		return $html;
	}

	public function renderNotes()
    {
        if (!empty($this->notes) || !empty($_SESSION['rmd_notes'])) {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $_SESSION['rmd_notes'] = $this->notes;
            } else {
                print $this->buildFeedbackMssg(['notes' => $_SESSION['rmd_notes']], "success");
            }
        }
    }

    public function buildFeedbackMssg($contents, $type = 'danger')
    {
        $alerts = "";
        $feedback = $contents['notes'] ?? $contents['errors'];
        foreach($feedback as $content) {
            $alerts .= "<div class='alert alert-$type'>$content</div>";
        }
        if (isset($contents['notes'])) {
            unset($_SESSION['rmd_notes']);
        }
        if (isset($contents['errors'])) {
            unset($_SESSION['rmd_errors']);
        }
        return $alerts;
    }

    public function redirect(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            redirect($_SERVER['REQUEST_URI']);
        }
    }

    public function fetchRecords($arm_id, $dag = null, $limit=null, $limitOffset=0)
    {
        global $Proj;
        // Get all records
        $records = \Records::getRecordList($Proj->project_id, $dag, false, false, $arm_id, $limit, $limitOffset);
        $records = array_values($records);
        if (!empty($records)) {
            // add custom record labels
            $recordsAndLabels = Records::getCustomRecordLabelsSecondaryFieldAllRecords($records, true);
        } else if (empty($records) && $limitOffset > 0){
            // back to the first record if we have reached the end
            $records = array_values(\Records::getRecordList($Proj->project_id, $dag, false, false, $arm_id, $limit));
            $recordsAndLabels = Records::getCustomRecordLabelsSecondaryFieldAllRecords($records, true);
        }
        $recordsAndLabels = $recordsAndLabels ?? [];
        foreach ($records as $value) {
            if (!isset($recordsAndLabels[$value])) {
                $recordsAndLabels[$value] = '';
            }
        }
        ksort($recordsAndLabels);
        $records = array_chunk($recordsAndLabels, 1000, true);
        echo json_encode(array("records" => $records));
    }

    public function validateUserRights()
    {
        $this->canDelete = $this->canDelete();
        if (!empty($this->errors)) {
            $this->renderErrors();
        }
    }

    // Determine if current user can access the Mass Delete page. Return boolean.
    public function canDelete()
    {
        global $user_rights, $lang;
        // If Bulk Delete is disabled globally, return false
        if ($GLOBALS['bulk_record_delete_enable_global'] != '1') return false;
        // stop client-side manipulation of group_id for unauthorized access
        if (!empty($_POST['group_id']) && $_POST['group_id'] != $user_rights['group_id']) {
            $errorMssg =   "<div class='red'>
						<img src='" . APP_PATH_IMAGES . "exclamation.png'> <b>{$lang['messaging_122']}</b><br>
					</div>";
            $this->errors[] = $errorMssg;
            return false;
        }
        // If user has delete record capabilities, then return true
        return UserRights::canDeleteWholeOrPartRecord();
    }

    public function handleDelete()
    {
        global $Proj, $lang;
        if (isset($_POST['delete']) && $_POST['delete'] == 'true') {
            if (!UserRights::canDeleteWholeOrPartRecord() || $GLOBALS['bulk_record_delete_enable_global'] != '1') {
                $this->errors[] = "Unauthorized! Missing record delete permission!";
                return;
            }
            try {
                $form_event = $_POST['form_event'];
                // check if multi-arm project
                if ($Proj->longitudinal && $Proj->multiple_arms) {
                    // make sure arm is valid, avoid sql injections
                    if(!$this->arm_id = $Proj->getArmIdFromArmNum($this->arm = $_SESSION['selected_arm'])) {
                        $this->errors[] = "The submitted arm from which to delete specified records was not found!";
                        return;
                    }
                    $this->records = \Records::getRecordList($Proj->project_id, $this->group_id, false, false, $this->arm);
                } else {
                    $this->records = \Records::getRecordList($Proj->project_id, $this->group_id);
                }
                // Determine which records we need to delete
                $post_records = $_POST['records'] ?? [];
                $valid_records = array_intersect(($post_records??[]), ($this->records??[]));
                if (count($valid_records) != count($post_records)) {
                    $this->errors[] = "Invalid records were requested for deletion. Please try again!";
                    return;
                } else {
                    // If doing partial delete with Reason For Change enabled, check to make sure reason is provided
                    if (!empty($form_event) && $Proj->project['require_change_reason'] && (!isset($_POST['change-reason']) || trim($_POST['change-reason']) == '')) {
                        $this->errors[] = "Reason for change was not provided. Please try again!" ;
                        return;
                    }
                    if (empty($form_event)) {
                        // Delete record data from logging? (default to yes if project-level setting is enabled, otherwise if not enabled, default to no)
                        $allow_delete_record_from_log = ($Proj->project['allow_delete_record_from_log'] && !(isset($_POST['delete_logging']) && $_POST['delete_logging'] == '0')) ? 1 : 0;
                        // DELETE THE RECORD
                        foreach ($valid_records as $record) {
                            \Records::deleteRecord(
                                $record,
                                $Proj->table_pk,
                                $Proj->multiple_arms,
                                $Proj->project['randomization'],
                                $Proj->project['status'],
                                $Proj->project['require_change_reason'],
                                $this->arm_id,
                                "",
                                $allow_delete_record_from_log
                            );
                        }
                        $this->notes[] = "<b>" . str_replace('{0}', count($valid_records), $lang['data_entry_628']) . "</b>";
                    } else {
                        // DELETE THE FORM
                        $deleted_forms = '';
                        foreach ($form_event as $one_form_event) {
                            // Split out the form and event
                            list($selected_event_name, $selected_event_id, $selected_form) = $this->splitFormEvent($one_form_event);
                            if (empty($selected_event_name)) {
                                $deleted_forms .= '   <li> ' . $selected_form;
                            } else {
                                $deleted_forms .= '   <li>[' . $selected_event_name . '] ' . $selected_form;
                            }
                            // Delete this form for all provided records
                            $this->deleteForm($selected_event_id, $selected_form, $Proj->isRepeatingForm($selected_event_id, $selected_form), $valid_records, ($_POST['change-reason'] ?? ""));
                        }
                        $this->notes[] = "<b>" . str_replace(['{0}', '{1}'], [$deleted_forms, count($valid_records)], $lang['data_entry_629']) . "</b>";
                    }
                }
                // Clear events forms selections upon successful deletion
                unset($_SESSION['form_event_data']);
            } catch (\Exception $e) {
                // This catch block catches all exceptions, including database exceptions. Ideally, we want to handle database errors internally and not display them to the user; we might want to explore using transactions.
                $this->errors[] = $e->getMessage();
                return;
            }
        }
    }

    public function renderErrors()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_SESSION['rmd_errors'] = $this->errors;
        } else {
            print $this->buildFeedbackMssg(['errors' => $_SESSION['rmd_errors']]);
        }
    }

    public function getFormEventList($arm_num = null)
    {
        return self::renderSelectedFormsEvents($_SESSION['form_event_data'] ?? [], $arm_num);
    }


    // Creates a div with selectable forms/events - requires javascript functions
    public static function renderSelectedFormsEvents($selected_forms_events=array(), $selected_arm_num = null)
    {
        global $Proj, $lang, $user_rights;

        // Get an array of all event names (in the current arm)
        $all_events = REDCap::getEventNames(true);

        $hasDeleteRights = UserRights::canDeleteWholeOrPartRecord();

        $checkboxHeaders = "<tr>";
        $checkboxColumns = "<tr>";
        $allArmsCheckboxes = array();
        $maxCheckboxesInGroups = 0;
        foreach ($Proj->events as $arm_num => $arm_detail)
        {
            if ($selected_arm_num && $arm_num != $selected_arm_num) {
                continue;
            }
            if ($Proj->multiple_arms) {
                $checkboxHeaders .= "<th style='font-size:12px;color:#800000;font-weight:bold;'>".$lang['global_08']." $arm_num".$lang['colon']." ".$arm_detail['name']."</th>";
            }
            $checkboxes = array();
            foreach ($arm_detail['events'] as $event_id => $event_attr) {
                $event_name = is_array($all_events) ? $all_events[$event_id] : '';
                if ($Proj->longitudinal) {
                    $checkboxes[] = RCView::div(array('style' => 'margin-top:3px;padding-top:3px;border-top:1px solid #ccc;font-weight:bold;'),
                        RCView::input(array('type' => 'checkbox', 'onclick' => "selectAllInEvent('$event_name', this);", 'id' => 'event-' . $event_name)) .
                        RCView::escape($event_attr['descrip'])
                    );
                } else {
                    $checkboxes[] = RCView::div(array('style' => 'font-weight:bold;'),
                        $lang['global_110'] . $lang['colon']
                    );
                }
                if (isset($Proj->eventsForms[$event_id])) {
                    $all_checked = true;
                    foreach ($Proj->eventsForms[$event_id] as $form) {
                        if (!isset($user_rights['forms'][$form])) continue;
                        if ($hasDeleteRights && !in_array($user_rights['forms'][$form], ['1', '3'])) continue;
                        $checkbox_id = 'ef-' . $event_name . '-' . $form;
                        $attr = array('type' => 'checkbox', 'id' => $checkbox_id, 'class' => 'efchk', 'name' => 'form_event[]', 'value' => $checkbox_id);
                        if (in_array($checkbox_id, $selected_forms_events)) {
                            $attr['checked'] = 'checked';
                        } else {
                            $all_checked = false;
                        }
                        $checkboxes[] = RCView::div(array('class' => 'wrap ' . ($Proj->longitudinal ? 'hangevl' : 'hangevc')),
                            RCView::input($attr) .
                            RCView::escape($Proj->forms[$form]['menu'])
                        );
                    }
                    if ($all_checked && $Proj->longitudinal) {
                        $checkboxes[] = RCView::script("document.getElementById('event-$event_name').checked = true;");
                    }
                }
            }

            $maxCheckboxesInGroups = max(count($checkboxes), $maxCheckboxesInGroups);
            $allArmsCheckboxes[] = $checkboxes;
        }
        // The following maneuver allows for checkboxes representing different forms in different arms to be aligned when the number of checkboxes/forms in each arm is different.
        foreach ($allArmsCheckboxes as &$checkboxGroup) {
            if (count($checkboxGroup) < $maxCheckboxesInGroups) {
                $count = $maxCheckboxesInGroups - count($checkboxGroup);
                while($count > 0) {
                    $checkboxGroup[] = RCView::div(array('class' => 'wrap ' . ($Proj->longitudinal ? 'hangevl' : 'hangevc')),
                        RCView::input(array('type' => 'checkbox', 'style' => 'visibility: hidden'))
                    );
                    $count--;
                }
            }
            $checkboxColumns .= "<td>".implode($checkboxGroup)."</td>";
        }

        $checkboxTable = "<table id='choose_select_forms_events_table'>" .
            ($Proj->multiple_arms ? "<tr>$checkboxHeaders<tr>" : '') .
            "<tr>$checkboxColumns<tr>
		</table>";

        // Build the hidden div
        $html = RCView::div(array('id'=>'choose_select_forms_events_div'),
            RCView::div(array('id'=>'choose_select_forms_events_div_sub'),
                RCView::div(array('style'=>($Proj->longitudinal ? 'width:400px;min-width:400px;' : 'width:300px;min-width:300px;').'color:#800000;font-weight:bold;font-size:13px;padding:6px 3px 5px;margin-bottom:3px;border-bottom:1px solid #ccc;'),
                    ($Proj->longitudinal ? $lang['data_entry_706'] : $lang['data_entry_706'])
                ) .
                RCView::div(array('style'=>'padding:0 0 10px;'),
                    RCView::span(array('id'=>'select_links_forms'),
                        RCView::button(array('class'=>'btn btn-primaryrc btn-xs', 'onclick'=>'excludeEventsUpdate(1);return false;'),$lang['global_125']).
                        RCView::button(array('class'=>'btn btn-defaultrc btn-xs', 'onclick'=>'excludeEventsUpdate(0);return false;'),$lang['global_53']).
                        RCView::a(array('href'=>'javascript:;', 'onclick'=>'selectAllFormsEvents(true)', 'style'=>'font-size:11px;text-decoration:underline;margin:0 10px 0 30px;'),$lang['ws_35']).
                        RCView::a(array('href'=>'javascript:;', 'onclick'=>'selectAllFormsEvents(false)', 'style'=>'font-size:11px;text-decoration:underline;'),$lang['ws_55'])
                    )
                ).
                $checkboxTable
            )
        );

        return $html;
    }

    public function splitFormEvent($selected_form_event)
    {
        global $Proj;
        // The format is 'ef-' . event name . '-' . form name for longitudinal projects
        // The format is 'ef--' . form name for class projects
        $pieces = explode("-", $selected_form_event);
        // Find the event_id. If no event_name is specified, there is only one event
        if (empty($pieces[1])) {
            $event_id = $Proj->firstEventId;
        } else {
            $event_id = null;
            $events = \REDCap::getEventNames(true);
            foreach ($events as $event_num => $event_name) {
                if ($event_name == $pieces[1]) {
                    $event_id = $event_num;
                    break;
                }
            }
        }
        return array($pieces[1], $event_id, $pieces[2]);
    }

    public function deleteForm($selected_event_id, $selected_form, $repeating_form, $record_list, $change_reason="")
    {
        global $Proj, $enable_edit_survey_response, $user_rights, $randomization;
        $project_id = $Proj->getId();
        $rf = new \RepeatingForms($project_id, $selected_form);

        $Locking = new Locking();
        $Locking->findLocked($Proj, $record_list);

        $survey_id = $Proj->forms[$selected_form]['survey_id'] ?? null;

        $formEditRights = $user_rights['forms'][$selected_form] ?? null;
        $userCanEditResponses = ($enable_edit_survey_response && $formEditRights == '3');

        $econsentEnabledForSurvey = false;
        $eConsentResponseIsEditable = false;
        if ($survey_id != null && Econsent::econsentEnabledForSurvey($survey_id)) {
            $econsentSettings = Econsent::getEconsentSurveySettings($survey_id);
            $eConsentResponseIsEditable = ($econsentSettings['allow_edit'] == '1');
            $econsentEnabledForSurvey = true;
        }

        $performSurveyLevelChecks = ($survey_id != null && (!$enable_edit_survey_response || !$userCanEditResponses || $econsentEnabledForSurvey));

        // Have the records been randomized? If so, make sure the user can't deleted the randomization field
        $formContainsRandFields = false; // default
        if ($randomization && Randomization::setupStatus()) {
            // Get randomization attributes
            $randAttrAll = Randomization::getAllRandomizationAttributes($project_id);
            $rids = array_keys($randAttrAll);
            foreach ($randAttrAll as $randAttr) {
                // Form contains randomization field
                $formContainsRandFields = ($randAttr['targetEvent'] == $selected_event_id && $Proj->metadata[$randAttr['targetField']]['form_name'] == $selected_form);
                if ($formContainsRandFields) break;
                // Loop through strata fields
                foreach ($randAttr['strata'] as $strata_field=>$strata_event) {
                    if ($strata_event == $selected_event_id && $Proj->metadata[$strata_field]['form_name'] == $selected_form) {
                        $formContainsRandFields = true;
                        break 2;
                    }
                }
            }
        }

        if ($repeating_form) {
            foreach($record_list as $record_id) {
                // Check 0) If record has been randomized and this form contains the randomization field or strata fields, skip record
                if ($formContainsRandFields) {
                    // Loop through all Randomization RIDs
                    foreach ($rids as $rid) {
                        if (Randomization::wasRecordRandomized($record_id, $rid)) {
                            continue;
                        }
                    }
                }
                // If whole record is locked, then skip this record
                if ($Locking->isWholeRecordLocked($Proj->project_id, $record_id, $Proj->eventInfo[$selected_event_id]['arm_num'])) {
                    continue;
                }
                // Loop through instance
                $rf->loadData($record_id, $selected_event_id);
                $all_instances = $rf->getAllInstanceIds($record_id, $selected_event_id);
                foreach ($all_instances as $instance_id)
                {
                    // Check 1) If the form is locked, skip it (cannot delete while locked)
                    if (isset($Locking->locked[$record_id][$selected_event_id][$instance_id][$selected_form."_complete"])) continue;
                    // Survey-related checks
                    if ($performSurveyLevelChecks) {
                        // Is this a survey response? And if so, is it a completed survey response?
                        $responseStatus = Survey::isResponseCompleted($survey_id, $record_id, $selected_event_id, $instance_id);
                        $isSurveyResponse = ($responseStatus !== false);
                        $isCompletedSurveyResponse = ($responseStatus == '1');
                        // Check 2) If no users are allowed to modify survey responses AND this form data is a survey response (not just form data), skip it
                        if ($isSurveyResponse && !$enable_edit_survey_response) continue;
                        // Check 3) If the user does not have form-level rights to modify survey responses for this instrument AND this is a completed survey response, skip it
                        if ($isSurveyResponse && !$userCanEditResponses) continue;
                        // Check 4) If this is a completed e-Consent response, in which e-Consent responses are not allowed to be edited for this survey, skip it
                        if ($isCompletedSurveyResponse && $econsentEnabledForSurvey && !$eConsentResponseIsEditable) continue;
                    }

                    // Delete form instance data
                    $rf->deleteInstance($record_id, $instance_id, $selected_event_id, $change_reason);
                }
            }
        } else {
            $instance_id = 1; // default for non-repeating data
            foreach ($record_list as $record_id)
            {
                // Check 0) If record has been randomized and this form contains the randomization field or strata fields, skip record
                if ($formContainsRandFields && Randomization::wasRecordRandomized($record_id)) continue;
                // Check 1) If the form is locked, skip it (cannot delete while locked)
                if (isset($Locking->locked[$record_id][$selected_event_id][$instance_id][$selected_form."_complete"])) continue;
                // Survey-related checks
                if ($performSurveyLevelChecks) {
                    // Is this a survey response? And if so, is it a completed survey response?
                    $responseStatus = Survey::isResponseCompleted($survey_id, $record_id, $selected_event_id, $instance_id);
                    $isSurveyResponse = ($responseStatus !== false);
                    $isCompletedSurveyResponse = ($responseStatus == '1');
                    // Check 2) If no users are allowed to modify survey responses AND this form data is a survey response (not just form data), skip it
                    if ($isSurveyResponse && !$enable_edit_survey_response) continue;
                    // Check 3) If the user does not have form-level rights to modify survey responses for this instrument AND this is a completed survey response, skip it
                    if ($isSurveyResponse && !$userCanEditResponses) continue;
                    // Check 4) If this is a completed e-Consent response, in which e-Consent responses are not allowed to be edited for this survey, skip it
                    if ($isCompletedSurveyResponse && $econsentEnabledForSurvey && !$eConsentResponseIsEditable) continue;
                }

                // Delete form data
                \Records::deleteForm($project_id, $record_id, $selected_form, $selected_event_id, $instance_id, null, $change_reason);
            }
        }
    }

}
