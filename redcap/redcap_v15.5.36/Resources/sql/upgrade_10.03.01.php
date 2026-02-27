<?php

$sql = "
ALTER TABLE `redcap_queue` DROP INDEX `key_index`;
ALTER TABLE `redcap_queue` ADD INDEX `key_index` (`key`(191));
ALTER TABLE `redcap_queue` ADD INDEX(`created_at`);
ALTER TABLE `redcap_queue` ADD INDEX(`updated_at`);
";
// If db is using UTF8 instead of UTF8MB4, then remove MB4 from SQL
print SQLTableCheck::filterSqlCollation($sql);