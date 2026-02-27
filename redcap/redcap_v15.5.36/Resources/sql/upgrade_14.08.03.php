<?php

$sql = "
ALTER TABLE `redcap_error_log` DROP INDEX `log_view_id`, ADD INDEX `log_view_id` (`log_view_id`);
";

// If db is using UTF8 instead of UTF8MB4, then remove MB4 from SQL
print SQLTableCheck::filterSqlCollation($sql);