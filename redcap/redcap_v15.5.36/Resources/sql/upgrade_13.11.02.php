<?php

$sql = "
-- new config setting
REPLACE INTO redcap_config (field_name, value) VALUES ('allow_auto_variable_naming', '1');
";
// If db is using UTF8 instead of UTF8MB4, then remove MB4 from SQL
print SQLTableCheck::filterSqlCollation($sql);