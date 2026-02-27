<?php

require_once dirname(dirname(__FILE__)) . '/Config/init_global.php';

// Must be an admin
if (!ACCESS_CONTROL_CENTER) redirect(APP_PATH_WEBROOT);


## INIT VARS
$sqlFixTableData1 = "";
$sqlFixTableData2 = "";
$sqlFixTableData3 = "";
$sqlFixTableData4 = "";
// Populate array of tables and columns to fix
list ($redcapCols, $allColsAllTables, $nonProjectTables) = SQLTableCheck::getRedcapColsToConvert();

// Does db need fixing?
$fixData = ($GLOBALS['db_character_set'] == 'latin1');
$fixStructure = !SQLTableCheck::using_utf8mb4();
$connectionIsMb3 = in_array(strtolower($GLOBALS['db_character_set']),['utf8mb3', 'utf8']);

// With regard to fixing data, set multiple flags in redcap_config (if not exist yet) to indicate progress of data fixed
if (!isset($GLOBALS['db_fix_data_nonproject']) && $fixData) {
	$db_fix_val = $fixData ? "0" : "1";
	db_query("replace into redcap_config (field_name, value) values ('db_fix_data_nonproject', '$db_fix_val')");
	db_query("replace into redcap_config (field_name, value) values ('db_fix_data_project_active', '$db_fix_val')");
	db_query("replace into redcap_config (field_name, value) values ('db_fix_data_project_inactive', '$db_fix_val')");
	db_query("replace into redcap_config (field_name, value) values ('db_fix_data_extra', '$db_fix_val')");
	// Redirect so that these new config values get pulled into GLOBALS
	redirect(PAGE_FULL);
}

// Reset fixData based on config flag completeness
$fixData = isset($GLOBALS['db_fix_data_nonproject']) && !($GLOBALS['db_fix_data_nonproject'] == '1' && $GLOBALS['db_fix_data_project_active'] == '1' && $GLOBALS['db_fix_data_project_inactive'] == '1' && $GLOBALS['db_fix_data_extra'] == '1');


// HTML
$html = RCView::h4([], "<i class=\"fas fa-hat-wizard fs18\"></i> Updating your REDCap Database Tables to support full Unicode");
$html .= RCView::p(['class'=>'mt-4'],
	"REDCap installations that were first installed on a version prior to REDCap 8.5.0 will have an older, legacy type of database encoding or charset (character set) for its columns, known in MariaDB/MySQL as <i>UTF8</i> or <i>UTF8MB3</i>. The recommended character set supporting languages in the Unicode Standard is known as <a href=\"https://dev.mysql.com/doc/refman/8.0/en/charset-unicode-utf8mb4.html\" target=\"_blank\" rel=\"noopener noreferrer\"><i>UTF8<u>MB4</u></i></a>.
	<p>Additionally, if these installations were paired with a version of MariaDB/MySQL whose server-level charset was left unchanged from its default value of \"latin1,\" then any special/non-<u><a href=\"https://en.wikipedia.org/wiki/ASCII#Character_set\" target=\"_blank\" rel=\"noopener noreferrer\">ASCII</a></u> characters will appear garbled in the backend database due to a mismatch between the <i>latin1</i> connection charset presumed by PHP and the <i>utf8*</i> column charset in the database. For instance, the string \"qué\" may be stored as \"quÃ©\" when viewed with a database client. Fortunately, in this setup, since any garbled text stored in the database is also un-garbled when read and rendered in the browser, this issue has been mostly transparent (invisible) to users and administrators on the frontend. However, since special characters in this setup are still stored as malformed text, this configuration is not ideal and may require extra care when restoring from a backup, etc. to ensure data integrity.
	Mixed-encoding content may also occasionally get imported through data uploads, etc., appearing as question marks, black diamonds �, or \"<a href=\"https://en.wikipedia.org/wiki/Mojibake\" target=\"_blank\" rel=\"noopener noreferrer\"><u>Mojibake</u></a>\" when rendered in REDCap.</p>
	<p>As part of the v8.5 upgrade (2018), REDCap began detecting and storing this connection charset and its collation in the <i>redcap_config</i> table (i.e., <i>db_character_set</i> and <i>db_collation</i>) as a \"stopgap\" to ensure consistent encoding behavior, as well as implementing full <i>UTF8MB4</i> databases on *new* installations of REDCap.</p>
	<p>This page is designed to resolve these legacy issues and modernize the character encoding on affected installations by:
	<ol type=\"1\"><li>upgrading database columns from the legacy <i>UTF8/UTF8MB3</i> charset to <i>UTF8MB4</i>, and</li>
	<li>converting database values with non-ASCII characters from 'garbled' UTF8 to 'clean' UTF8 and resetting the connection encoding from <i>latin1</i> to <i>UTF8MB4</i>&mdash;thus restoring consistency and enabling full Unicode 'from front to back.'</li></ol>
	If you see red text below that says \"Issues Exist\", then this affects your REDCap installation, in which it is *highly* recommended that you follow the steps below to update your database.
	<b>Please note that if you are affected, this is NOT an urgent issue, but it is something we recommend you address sooner rather than later since your current database charset and collation
	(<i>UTF8</i> or <i>UTF8MB3</i>) have been deprecated in the latest versions of MySQL/MariaDB and thus will eventually be removed altogether in future versions of MySQL/MariaDB.</b></p>"
);

if (!$fixStructure && !$fixData) {
	include 'header.php';
	$html .= RCView::p(['class'=>'text-success mt-4'],
		"<b>STEP 1:</b> Database table structure is correct! The tables already have a UTF8MB4 charset and collation."
	);
	$html .= RCView::p(['class'=>'text-success'],
		"<b>STEP 2:</b> Database table data is correct! All table data is correctly encoded with the UTF8MB4 character set."
	);
	$html .= RCView::p(['class'=>'mt-4 fs15 text-success'],
		"<b><i class=\"fas fa-check\"></i> You're all good!</b> It appears that your REDCap database tables and their data are in the correct format. So there is nothing to do here. Enjoy REDCapping!"
	);
	print $html;
	include 'footer.php';
    exit;
}
$html .= RCView::p(['class'=>'mb-5'],
	"If you are affected, it is important to know that <u>the changes required to update your database character set/collation are significant</u>, 
	in which *every* REDCap database table will need to have its table structure modified, 
	and additionally some of the data in the tables must be modified and converted into the new character encoding. If you have a large REDCap installation
	(e.g., thousands of REDCap projects or a redcap_data table with tens of millions or more rows), then this process could take anywhere from 30 minutes to many hours to complete. 
	It is not possible to know exactly how long this process will take for you because it is affected by server CPU/RAM, the size of your database tables, and many other factors.
	Please follow the steps below to begin updating your database. <b>This is a multi-step process that must be performed one step at a time until all steps have been completed.</b>"
);

// Return all redcap tables as an array
$redcapTables = SQLTableCheck::getRedcapTables();

## FIX TABLES
// Get db table structure changes
$sqlFixTableStructure = "";
if ($fixStructure)
{
	$tablesToFixStructure = [];
	$sqlFixTableStructureConfig = "";
	$tableCheck = new SQLTableCheck();
	$sql_fixes = $tableCheck->build_table_fixes(false, true);
	$dbStructureFixed = ($sql_fixes == "");
	$sqlUpdateConnectionMb3toMb4 = "";	
	if (!$dbStructureFixed) {
		// Group SQL by table
		$sql_fixes2 = [];
		foreach (explode("\n", $sql_fixes) as $line) {
			$posThirdSpace = SQLTableCheck::strposX($line, " ", 3);
			$firstPart = substr($line, 0, $posThirdSpace);
			$secondPart = substr($line, $posThirdSpace + 1);
			$sql_fixes2[$firstPart][] = $secondPart;
			$tablesToFixStructure[] = trim(str_replace(["ALTER TABLE `", "`"], "", $firstPart));
		}
		$tablesToFixStructure = array_unique($tablesToFixStructure);
		// Fix tables that don't need columns fix
		foreach (array_diff($redcapTables, $tablesToFixStructure) as $table) {
			$sql_fixes2["ALTER TABLE `$table`"] = [];
		}
		ksort($sql_fixes2);
		$tableRowFormats = array();
        	$tableRowFormatQry = db_query("SHOW TABLE STATUS FROM `$db` where Name like 'redcap%'");
		while ($row2 = db_fetch_assoc($tableRowFormatQry)) {
			$tableRowFormats[$row2['Name']] = $row2['Row_format'];
		}
		foreach ($sql_fixes2 as $alterTable => $lines) {
			foreach ($lines as &$line) $line = rtrim(trim($line), ";");
			$table = trim(str_replace(["ALTER TABLE `", "`"], "", $alterTable));
			// Add ROW_FORMAT=DYNAMIC if it's in another format such as COMPACT or REDUNDANT - but COMPRESSED is okay. (do this in one fell swoop instead of separately)
			$alterFormat = "";
			if (isset($tableRowFormats[$table]) && strtolower($tableRowFormats[$table]) != 'dynamic' && strtolower($tableRowFormats[$table]) != 'compressed') {
				$alterFormat = "\n\tROW_FORMAT=DYNAMIC,";
            		}
            		// Set whole query
			$thisTableStructure = $alterTable . $alterFormat . "\n\tCHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci" . (empty($lines) ? "" : ",\n\t" . implode(",\n\t", $lines)) . ";\n";
			// Set config table separate because we'll append it manually later
			if ($table == "redcap_config") {
				$sqlFixTableStructureConfig = $thisTableStructure;
			} else {
				$sqlFixTableStructure .= $thisTableStructure;
			}
		}
	}
	if ($fixStructure && $connectionIsMb3) {
		$sqlUpdateConnectionMb3toMb4 = "\n-- Update Connection Encoding to UTF8MB4 --\n-- The config for the connection encoding (db_character_set) is set to 3-byte UTF-8 or 'utf8[mb3],' which is a subset of full UTF-8 encoding (utf8mb4).\n-- Since no text conversions are needed, update the connection encoding/collation utf8mb4/utf8mb4_unicode_ci at this step to complete the conversion process.";
		$sqlUpdateConnectionMb3toMb4 .= "\nUPDATE redcap_config\n\tSET `value` = 'utf8mb4' \n\tWHERE field_name = 'db_character_set' AND lower(`value`) IN ('utf8', 'utf8mb3');";	
		$sqlUpdateConnectionMb3toMb4 .= "\nUPDATE redcap_config\n\tSET `value` = 'utf8mb4_unicode_ci'\n\tWHERE field_name = 'db_collation' AND lower(`value`) like 'utf8%';\n";
	}
    $sqlFixTableStructure = "{$sqlFixTableStructure}{$sqlFixTableStructureConfig}{$sqlUpdateConnectionMb3toMb4}";
    // In certain cases, a double-comma mistakenly occurs. If so, replace with single comma. Unsure as to the ultimate cause of this.
    $sqlFixTableStructure = str_replace(",,", ",", $sqlFixTableStructure);
	// Append the config table at the very end since all this depends on its collation
	$sqlFixTableStructure = SQLTableCheck::wrapSqlDbFlags($sqlFixTableStructure, false, "TABLE STRUCTURE: SQL to fix the table charset and collation of the REDCap database tables", true);
}


## FIX DATA
## Separate into 3 steps with 3 separate blocks of SQL to execute: Fix data for 1) Non-project tables (don't contain project_id field),
## 2) Projects with activity in the past year, 3) Projects with no activity in the past year, and 4) redcap_log_event and redcap_log_view where project_id is null.
## The config db_collation value can be set after Step 2, and thus Steps 3 and 4 can be run at a slightly later time if needed.

// Get project_ids, survey_ids, and event_ids of projects active in the past year (exclude any that have been individually converted via project_db_character_set in redcap_projects)
$pidsActivePastYear = $eventIdsActivePastYear = $surveyIdsActivePastYear = [];
$sql = "select project_id from redcap_projects where last_logged_event > DATE_SUB(NOW(), INTERVAL 1 YEAR) and project_db_character_set is null";
$q = db_query($sql);
if (!$q) print_array("DB QUERY ERROR: ".db_error()."<br>on the query:<br>$sql");
while ($row = db_fetch_assoc($q)) {
	$pidsActivePastYear[] = $row['project_id'];
}
if (!empty($pidsActivePastYear)) {
	$sql = "select survey_id from redcap_surveys where project_id in (" . implode(",", $pidsActivePastYear) . ")";
	$q = db_query($sql);
	if (!$q) print_array("DB QUERY ERROR: " . db_error() . "<br>on the query:<br>$sql");
	while ($row = db_fetch_assoc($q)) {
		$surveyIdsActivePastYear[] = $row['survey_id'];
	}
	$sql = "select event_id from redcap_events_arms a, redcap_events_metadata e 
			where a.arm_id = e.arm_id and a.project_id in (" . implode(",", $pidsActivePastYear) . ")";
	$q = db_query($sql);
	if (!$q) print_array("DB QUERY ERROR: " . db_error() . "<br>on the query:<br>$sql");
	while ($row = db_fetch_assoc($q)) {
		$eventIdsActivePastYear[] = $row['event_id'];
	}
}

// Find all table columns whose data needs to be converted
if ($fixData)
{
	// #1: Fix data in non-project tables
	// SQL to fix data in non-project-tables
	foreach ($nonProjectTables as $table => $cols) {
		foreach ($cols as $col) {
			$sqlFixTableData1 .= SQLTableCheck::generateUpdateSql($allColsAllTables, $table, $col);
		}
	}
	// Fix redcap_user_information manually since we're ignoring all "user*" fields
	$sqlFixTableData1 .= SQLTableCheck::generateUpdateSql($allColsAllTables, "redcap_user_information", "user_firstname");
	$sqlFixTableData1 .= SQLTableCheck::generateUpdateSql($allColsAllTables, "redcap_user_information", "user_lastname");
	$sqlFixTableData1 .= SQLTableCheck::generateUpdateSql($allColsAllTables, "redcap_user_information", "user_comments");
	$sqlFixTableData1 = SQLTableCheck::wrapSqlDbFlags($sqlFixTableData1, true, "NON-PROJECT-RELATED TABLES & ACTIVE PROJECTS\n\n-- NON-PROJECT-RELATED TABLES: SQL to fix the garbled data in non-project-related REDCap database tables (as a single transaction)", false, 'db_fix_data_nonproject');

	// #2: Fix data in project-related tables, separating active and non-active projects
    $sqlFixTableData2 .= "-- Clear rows that might fail FK update
update redcap_outgoing_email_sms_log l left join redcap_projects p on p.project_id = l.project_id set l.project_id = null where l.project_id is not null and p.project_id is null;
delete l.* from redcap_user_rights l left join redcap_projects p on p.project_id = l.project_id where l.project_id is not null and p.project_id is null;
delete l.* from redcap_user_roles l left join redcap_projects p on p.project_id = l.project_id where l.project_id is not null and p.project_id is null;\n";
	foreach ($redcapCols as $table => $cols) {
		foreach ($cols as $col) {
			// For projects that have been recently active
			$sqlFixTableData2 .= SQLTableCheck::generateUpdateSql($allColsAllTables, $table, $col, $pidsActivePastYear, $surveyIdsActivePastYear, $eventIdsActivePastYear, true, true);
			// For projects that have not been recently active
			$sqlFixTableData3 .= SQLTableCheck::generateUpdateSql($allColsAllTables, $table, $col, $pidsActivePastYear, $surveyIdsActivePastYear, $eventIdsActivePastYear, false);
		}
	}
	// Update config table
	$sqlFixTableData2 .= "UPDATE `redcap_config` SET `value` = 'utf8mb4' WHERE `field_name` = 'db_character_set';\n"
		. "UPDATE `redcap_config` SET `value` = 'utf8mb4_unicode_ci' WHERE `field_name` = 'db_collation';\n";
	
	// Put everything inside a transaction
	$sqlFixTableData2 = SQLTableCheck::wrapSqlDbFlags($sqlFixTableData2, true, "ACTIVE PROJECTS: SQL to fix the garbled data in project-related REDCap database tables (as a single transaction)", false, 'db_fix_data_project_active');

	// Put everything inside a transaction
	$sqlFixTableData3 = SQLTableCheck::wrapSqlDbFlags($sqlFixTableData3, true, "NON-ACTIVE PROJECTS: SQL to fix the garbled data in project-related REDCap database tables (as a single transaction)", false, 'db_fix_data_project_inactive');

	// #4: Manually add redcap_log_event and redcap_log_view where PROJECT_ID IS NULL
	$sqlFixTableData4 .= SQLTableCheck::generateUpdateSql($allColsAllTables, "redcap_log_event", "pk", [0]); // redcap_log_event project_id's are not nullable, default to 0
	$sqlFixTableData4 .= SQLTableCheck::generateUpdateSql($allColsAllTables, "redcap_log_event", "data_values", [0]);
	$sqlFixTableData4 .= SQLTableCheck::generateUpdateSql($allColsAllTables, "redcap_log_event", "change_reason", [0]);
	$sqlFixTableData4 .= SQLTableCheck::generateUpdateSql($allColsAllTables, "redcap_log_view", "record", null);
	$sqlFixTableData4 .= SQLTableCheck::generateUpdateSql($allColsAllTables, "redcap_log_view", "miscellaneous", null);
	$sqlFixTableData4 = SQLTableCheck::wrapSqlDbFlags($sqlFixTableData4, true, "LOW-PRIORITY: SQL to fix the garbled data in non-project-related log tables (as a single transaction)", false, 'db_fix_data_extra');
}


// Only list the "fix data" steps we haven't completed yet (based on the config values)
if ($GLOBALS['db_fix_data_nonproject'] == '1') $sqlFixTableData1 = "";
if ($GLOBALS['db_fix_data_project_active'] == '1') $sqlFixTableData2 = "";
if ($GLOBALS['db_fix_data_project_inactive'] == '1') $sqlFixTableData3 = "";
if ($GLOBALS['db_fix_data_extra'] == '1') $sqlFixTableData4 = "";



$addlInstructions = '
	<p class="mt-5"><b style="font-size:13px;">RECOMMENDATION: Save the SQL above as a file and run it via command line.</b></p>
	<ol>
		<li>Copy and paste the SQL upgrade script to a file, and place the file somewhere on your MySQL database server
		(via FTP or however you access the database server\'s file system). Make sure the filename remains as "redcap_db_fix.sql".</li>
		<li>Open a terminal window (command line interface) to your MySQL database server, and on that server navigate to the directory where you placed the 
		upgrade script file (using "cd" or other similar command).</li>
		<li>Via command line, execute the line below (replacing USERNAME with the username of the MySQL user you are connecting with).</li>
	</ol>
	<div style=\'margin:6px 0 20px 22px;\'>
		<textarea style=\'margin:0 0 0 8px;padding: 3px 5px; background: none repeat scroll 0 0 #F6F6F6;border-color: #A4A4A4 #B9B9B9 #B9B9B9; border-radius: 3px;border-right: 1px solid #B9B9B9; border-style: solid; border-width: 1px;box-shadow: 0 1px 0 #FFFFFF, 0 1px 1px rgba(0, 0, 0, 0.17) inset;color:#444;font-size:12px;width:96%;height:30px;\' readonly=\'readonly\' onclick=\'this.select();\'>mysql -u USERNAME -p -h '.$GLOBALS['hostname'].' '.$GLOBALS['db'].' < redcap_db_fix.sql</textarea>
	</div>
	<p class="text-danger fs14"><b>After you execute the SQL above, refresh this page</b> to see if there is anything else to do afterward.</p>';


// 1) DB structure needs to be fixed
if ($sqlFixTableStructure != "") {
	$html .= RCView::p(['class'=>'mb-4 text-danger fs15 font-weight-bold'],
		"<i class=\"fas fa-exclamation-circle\"></i> ISSUES EXIST IN YOUR REDCAP DATABASE: Follow all the instructions below to fix them."
	);
	$html .= RCView::p([],
		"<b>PRE-STEP:</b> It is *HIGHLY* recommended that you <b>perform a database backup</b> before running any of the SQL generated below (in case of the unlikely event that something goes wrong
		and you have to restore your database). Also prior to performing Step 1, it is recommended that you take REDCap offline (via the General Configuration page in the Control Center)
		so that users are not using the system while doing this. You might additionally <u>consider performing this process on a REDCap test server first or (even better) a MySQL replica
		of your production server</u>."
	);
	$html .= RCView::p([],
		"<b>STEP 1:</b> Changes need to be made to the REDCap database tables. Copy, paste, and execute the SQL below to change the tables. Please note that this process may
		take a VERY LONG TIME (possibly hours) to run depending on the size of your database. 
		For this step, it is recommended that you <b>take REDCap offline</b> (via the General Configuration page in the Control Center)
		so that users are not using the system while doing this."
	);
	$html .= "<textarea style='margin:8px 0 8px 10px;padding: 3px 5px; background: none repeat scroll 0 0 #F6F6F6;border-color: #A4A4A4 #B9B9B9 #B9B9B9; border-radius: 3px;border-right: 1px solid #B9B9B9; border-style: solid; border-width: 1px;box-shadow: 0 1px 0 #FFFFFF, 0 1px 1px rgba(0, 0, 0, 0.17) inset;color:#444;font-size:11px;width:90%;height:210px;' readonly='readonly' onclick='this.select();'>$sqlFixTableStructure</textarea>";
	$html .= RCView::p(['style'=>'color:#999;'],
		"<b>STEP 2:</b> Changes need to be made to fix the incorrectly encoded (garbled) data in the REDCap database tables. (Instructions for this step will only be revealed after performing Step 1.)"
	);
	$html .= $addlInstructions;
}
// 2) DB data needs to be fixed
elseif ($sqlFixTableData1.$sqlFixTableData2.$sqlFixTableData3.$sqlFixTableData4 != "") {

    // Check if Step 2 Alt cron job is running, and if so, what is its progress?
    $sqlEnableCron = "UPDATE `redcap_crons` SET `cron_enabled` = 'ENABLED' WHERE `cron_name` = 'UnicodeFixProjectLevel';";
    // Is cron enabled?
    $sql = "select 1 from redcap_crons where `cron_name` = 'UnicodeFixProjectLevel' and `cron_enabled` = 'ENABLED'";
    $cronEnabled = (db_num_rows(db_query($sql)) > 0);
    $totalProjects = db_result(db_query("SELECT count(*) FROM redcap_projects"), 0);
    $convertedProjects = 0;
    if ($cronEnabled) {
        $convertedProjects = db_result(db_query("SELECT count(*) FROM redcap_projects WHERE project_db_character_set is not null"), 0);
    }

	$html .= RCView::p(['class'=>'mb-4 text-danger fs15 font-weight-bold'],
		"<i class=\"fas fa-exclamation-circle\"></i> ISSUES EXIST IN YOUR REDCAP DATABASE: Follow all the instructions below to fix them."
	);
	$html .= RCView::p(['class'=>'text-success'],
		"<b>STEP 1:</b> Database table structure is correct! The tables already have a UTF8MB4 charset and collation."
	);
	$html .= RCView::p(['class'=>'mb-3'],
		"<b>STEP 2:</b> Changes need to be made to <u>fix the incorrectly encoded (garbled) data</u> in the REDCap database tables. 
        There are two options for doing this. You may either perform <b>Step 2 Alternative: Project-By-Project Transformation Cron Job</b>,
        or you may instead perform <b>Step 2A, 2B, and 2C</b>. How to choose which to do: If your REDCap installation was installed roughly 8+ years ago OR if it contains more than 1000 projects,
        it is recommended that you use the Alternative method, which will run the Unicode Transformation SQL on each project one at a time via the cron job. <u>NOTE: If you enable
        Step 2 Alternative, it is recommended that you initiate it during off-hours</u>, such as in the evening or (for very large REDCap installations) on a Friday night to let it run 
        over the weekend. It is fine if REDCap users are using the system while the cron job runs, but it is preferred to let it run when there is less activity so that it may complete faster and won't impact performance.
        Once it has completed, it will disable itself. You can return to this page to view its progress at any time after it has been initiated."
	);
	$html .= RCView::p(['class'=>'mb-1'],
		"<b style='color:#A00000'>Step 2 Alternative - Project-By-Project Transformation Cron Job:</b>"
	);
    if ($cronEnabled) {
        $html .= RCView::p(['class' => 'mb-3'],
            "PROGRESS: Thus far, the cron job has <b class='fs14 text-successrc'><u>converted $convertedProjects out of $totalProjects projects</u></b> in the system. While the cron job is running, please do NOT perform
            Step 2A, 2B, or 2C below. Wait until the cron job has finished, after which all steps on this page will appear green."
        );
    } else {
        $html .= RCView::p(['class' => 'mb-3'],
            "Copy, paste, and execute the SQL below to initiate the cron job. Once it has finished, it will disable itself. After executing the SQL, refresh this page to view its progress.
            <textarea style='display:block;margin:8px 0 18px 10px;padding: 3px 5px; background: none repeat scroll 0 0 #F6F6F6;border-color: #A4A4A4 #B9B9B9 #B9B9B9; border-radius: 3px;border-right: 1px solid #B9B9B9; border-style: solid; border-width: 1px;box-shadow: 0 1px 0 #FFFFFF, 0 1px 1px rgba(0, 0, 0, 0.17) inset;color:#444;font-size:11px;width:90%;height:40px;' readonly='readonly' onclick='this.select();'>{$sqlEnableCron}</textarea>"
        );
    }
	$html .= RCView::p(['class'=>'mb-3 ml-2 text-secondary'],
		"-- OR --"
	);
    $html .= RCView::p(['class'=>'mb-1'],
        "<b style='color:#A00000'>Step 2A, 2B, and 2C:</b>"
    );
	$html .= RCView::p(['class'=>'mb-3'],
		"Copy, paste, and execute the SQL below to update the tables. Please note that this process may
		take a VERY LONG TIME (possibly hours) to run depending on the size of your database and your server's performance. 
		You should <b>execute the SQL in the textboxes below <u>one box at a time</u></b> -
		i.e., execute the first, then refresh this page, then execute the next, then refresh this page, until they have all been executed."
	);
	if ($sqlFixTableData1.$sqlFixTableData2 != '') {
        if (mb_strlen($sqlFixTableData1.$sqlFixTableData2) > 30*1024*1024) {
            $sqlFixTableData2 = "";
            $sqlFixTableData1 = $sqlFixTableData3 = $sqlFixTableData4 = "-- Sorry, but this REDCap installation contains too many projects for Step 2A, 2B, and 2C.\n-- Please try Step 2 Alternative instead.";
        }
		$html .= "<b>STEP 2A:</b> For this step, it is recommended that you <b>take REDCap offline</b> (via the General Configuration page in the Control Center)
				so that users are not using the system while doing this.
				<textarea style='display:block;margin:8px 0 18px 10px;padding: 3px 5px; background: none repeat scroll 0 0 #F6F6F6;border-color: #A4A4A4 #B9B9B9 #B9B9B9; border-radius: 3px;border-right: 1px solid #B9B9B9; border-style: solid; border-width: 1px;box-shadow: 0 1px 0 #FFFFFF, 0 1px 1px rgba(0, 0, 0, 0.17) inset;color:#444;font-size:11px;width:90%;height:210px;' readonly='readonly' onclick='this.select();'>{$sqlFixTableData1}\n\n{$sqlFixTableData2}</textarea>";
	} else {
		$html .= RCView::div(['class'=>'text-success mb-3'],
			"<b>STEP 2A:</b> Done!"
		);
	}
	if ($sqlFixTableData3 != '') {
		$html .= "<b>STEP 2B:</b> For the following steps, <b>it is okay to bring REDCap back online again</b> when executing these. Executing the rest of the SQL should not affect 
				users currently using the system. If you are worried that server performance might be impacted by running these, it is perfectly fine to keep REDCap offline for this step too.
				<textarea style='display:block;margin:8px 0 18px 10px;padding: 3px 5px; background: none repeat scroll 0 0 #F6F6F6;border-color: #A4A4A4 #B9B9B9 #B9B9B9; border-radius: 3px;border-right: 1px solid #B9B9B9; border-style: solid; border-width: 1px;box-shadow: 0 1px 0 #FFFFFF, 0 1px 1px rgba(0, 0, 0, 0.17) inset;color:#444;font-size:11px;width:90%;height:210px;' readonly='readonly' onclick='this.select();'>$sqlFixTableData3</textarea>";
	} else {
		$html .= RCView::div(['class'=>'text-success mb-3'],
			"<b>STEP 2B:</b> Done!"
		);
	}
	if ($sqlFixTableData4 != '') {
		$html .= "<b>STEP 2C:</b> Because the SQL in this step is much lower priority, is unlikely to affect users if not executed.
				This SQL can be executed at a later time, if needed (e.g., hours or days later). But it still must be done
				to complete the overall process of updating your database tables and data.
				<textarea style='display:block;margin:8px 0 18px 10px;padding: 3px 5px; background: none repeat scroll 0 0 #F6F6F6;border-color: #A4A4A4 #B9B9B9 #B9B9B9; border-radius: 3px;border-right: 1px solid #B9B9B9; border-style: solid; border-width: 1px;box-shadow: 0 1px 0 #FFFFFF, 0 1px 1px rgba(0, 0, 0, 0.17) inset;color:#444;font-size:11px;width:90%;height:210px;' readonly='readonly' onclick='this.select();'>$sqlFixTableData4</textarea>";
	}
	$html .= $addlInstructions;
}

// Render page
include 'header.php';
print $html;
include 'footer.php';
