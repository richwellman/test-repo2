<?php

$sql = "
-- new config value
replace into redcap_config (field_name, value) values ('read_replica_enable', '0');
";
// If db is using UTF8 instead of UTF8MB4, then remove MB4 from SQL
print SQLTableCheck::filterSqlCollation($sql);