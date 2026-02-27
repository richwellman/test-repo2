<?php

use Vanderbilt\REDCap\Classes\ProjectDesigner;

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

if (isset($_POST['forms'])) {
	// Parse and validate the forms
	$forms = array();
    $i = 1;
    global $myCapProj;
	foreach (explode(",", $_POST['forms']) as $this_form) {
        if ($i == 1 && isset($myCapProj->tasks[$this_form]['task_id'])) {
            print "2"; exit;
        }
        $i++;
		if (!empty($this_form)) {
			$forms[] = $this_form;
		}
	}
    $projectDesigner = new ProjectDesigner($Proj);
	print ($projectDesigner->updateFormsOrder($forms) === true) ? "1" : "0";
}