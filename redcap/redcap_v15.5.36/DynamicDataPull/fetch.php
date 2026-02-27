<?php
require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

function isProjectStatusAnalisysCleanup() {
	global $Proj;
	$statusAnalysisCleanup = 2;
	return ($Proj?->project['status'] ?? 0) == $statusAnalysisCleanup;
}

if(isProjectStatusAnalisysCleanup()) {
	// Output warning message that project is in analysis status
	ob_start();
	?>
	<div class="yellow my-2" style="max-width:100%;">
		<i class="fas fa-exclamation-triangle text-warning"></i>
		<span>
			<?= $lang['global_03']; // "Warning" ?> 
			<?= $lang['ws_347'] ?>
		</span>
		<details class="my-2">
			<summary><?= $lang['ws_22'] ?></summary>
			<p><?= $lang['ws_348'] ?></p>
			<a href="mailto:<?= $project_contact_email; ?>" style="text-decoration:underline;">
				<?= $lang['bottom_39']; ?>
			</a>
		</details>
	</div>
	<?php
	$html = ob_get_clean();
	// Return content as JSON
	print json_encode_rc(['item_count'=>-1, 'html'=>$html]);
	exit;
}

/** @var DynamicDataPull $DDP */
// First check if the user has user access rights to adjudicate source data
if (!$DDP->userHasAdjudicationRights(true))
{
	// Output error message that user has no access
	$html = RCView::div(array('class'=>'red', 'style'=>'max-width:100%;padding:3px 5px 1px;font-size:11px;text-align:left;'),
				RCView::img(array('src'=>'exclamation_small.png', 'style'=>'vertical-align:middle;')) .
				RCView::span(array('id'=>'RTWS_sourceDataCheck_userAccessErrorTitle'), 
					$lang['ws_21'] . " " . $DDP->getSourceSystemName() .
					($realtime_webservice_type == 'FHIR' ? $lang['ws_203'] . " " . $DDP->getSourceSystemName() . $lang['period'] : "")
				) .
				RCView::a(array('href'=>'javascript:;', 'style'=>'text-decoration:underline;font-size:11px;margin-left:8px;', 'onclick'=>"
					simpleDialog( $('#RTWS_sourceDataCheck_userAccessError').html(), $('#RTWS_sourceDataCheck_userAccessErrorTitle').text() );
				"), $lang['ws_22']) .
				// Hidden message to be displayed in a dialog
				RCView::div(array('id'=>'RTWS_sourceDataCheck_userAccessError', 'style'=>'display:none;'),
					($realtime_webservice_type == 'FHIR' ? $lang['ws_204'] : $lang['ws_23']) . " " .
					RCView::a(array('href'=>"mailto:$project_contact_email", 'style'=>'text-decoration:underline;'), $lang['bottom_39']) .
					$lang['period']
				)
			);
	// Return content as JSON
	print json_encode(array('item_count'=>-1, 'html'=>$html));
	exit;
}


## PERFORMANCE: Kill any currently running processes by the current user/session on THIS page
System::killConcurrentRequests(5);


if (!is_numeric($_GET['event_id'])) {
	// If record is passed in query string, obtain it
	$record = strip_tags(html_entity_decode($_POST['record'], ENT_QUOTES));
	$form_data = array();
	$event_id = null;
	$output_html = (!isset($_GET['output_html']) || (isset($_GET['output_html']) && $_GET['output_html'] != '0'));
} else {
	// OR if ALL form data is passed as separate POST elements
	$record = strip_tags(html_entity_decode($_POST[$table_pk], ENT_QUOTES));
	$form_data = $_POST;
	$event_id = $_GET['event_id'];
	$output_html = true;
}
$record_exists = ($_GET['record_exists'] == '1') ? '1' : '0';
$show_excluded = (isset($_GET['show_excluded']) && $_GET['show_excluded'] == '1');
$forceDataFetch = (isset($_GET['forceDataFetch']) && $_GET['forceDataFetch'] == '1');

// Get number of items to adjudicate and the html to display inside the dialog
list ($itemsToAdjudicate, $tableHtml)
	= $DDP->fetchAndOutputData($record, $event_id, $form_data, $_GET['day_offset'], $_GET['day_offset_plusminus'],
								$output_html, $record_exists, $show_excluded, $forceDataFetch, $_GET['instance'], 
								(isset($_GET['page']) && $_GET['instance'] > 0 ? $_GET['page'] : null));


$response = array('item_count'=>$itemsToAdjudicate, 'html'=>$tableHtml);
// add errors to response if any
if(isset($DDP->fhirData) && $DDP->fhirData->hasErrors())
{
	$response['errors'] = $DDP->fhirData->getErrors();
}
// Output data returned from web service as JSON
HttpClient::printJSON($response);