<?php

$sql = "
ALTER TABLE `redcap_projects` 
    ADD `edoc_upload_max` INT(10) NULL DEFAULT NULL AFTER `last_logged_event`, 
    ADD `file_attachment_upload_max` INT(10) NULL DEFAULT NULL AFTER `edoc_upload_max`;
";
// If db is using UTF8 instead of UTF8MB4, then remove MB4 from SQL
print SQLTableCheck::filterSqlCollation($sql);