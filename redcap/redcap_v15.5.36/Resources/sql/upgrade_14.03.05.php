<?php

$sql = "
ALTER TABLE `redcap_mycap_projects`
	ADD `event_display_format` enum('ID','LABEL','NONE') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NONE';
";
// If db is using UTF8 instead of UTF8MB4, then remove MB4 from SQL
print SQLTableCheck::filterSqlCollation($sql);