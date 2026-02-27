<?php

$sql = "
ALTER TABLE `redcap_mycap_projects` ADD `notification_time` time DEFAULT '08:00:00';
";
// If db is using UTF8 instead of UTF8MB4, then remove MB4 from SQL
print SQLTableCheck::filterSqlCollation($sql);