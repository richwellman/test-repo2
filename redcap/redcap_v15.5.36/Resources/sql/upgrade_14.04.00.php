<?php

$sql = "
ALTER TABLE `redcap_data_import` CHANGE `date_format` `date_format` enum('YMD','MDY','DMY') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'YMD';    
";

// If db is using UTF8 instead of UTF8MB4, then remove MB4 from SQL
print SQLTableCheck::filterSqlCollation($sql);