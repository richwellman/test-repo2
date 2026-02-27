<?php

$sql = "
ALTER TABLE `redcap_alerts` CHANGE `cron_repeat_for` `cron_repeat_for` FLOAT NOT NULL DEFAULT '0' COMMENT 'Repeat every # of days';
";
// If db is using UTF8 instead of UTF8MB4, then remove MB4 from SQL
print SQLTableCheck::filterSqlCollation($sql);