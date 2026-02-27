<?php

$sql = "
REPLACE INTO `redcap_config` (`field_name`, `value`) VALUES ('fhir_cdp_allow_auto_adjudication', '1');
ALTER TABLE `redcap_projects` ADD `fhir_cdp_auto_adjudication_enabled` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'If true, auto adjudicate data in CDP projects';
";
// If db is using UTF8 instead of UTF8MB4, then remove MB4 from SQL
print SQLTableCheck::filterSqlCollation($sql);