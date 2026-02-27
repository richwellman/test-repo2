<?php

$sql = "
ALTER TABLE `redcap_surveys` CHANGE `repeat_survey_btn_location` `repeat_survey_btn_location` ENUM('BEFORE_SUBMIT','AFTER_SUBMIT','HIDDEN') 
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'BEFORE_SUBMIT';
";
// If db is using UTF8 instead of UTF8MB4, then remove MB4 from SQL
print SQLTableCheck::filterSqlCollation($sql);