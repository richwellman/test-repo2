<?php

$sql = "
set @config_settings_key = (select value from redcap_config where field_name = 'config_settings_key' limit 1);
REPLACE INTO redcap_config (field_name, value) VALUES ('config_settings_key', ifnull(@config_settings_key,''));
";

// If db is using UTF8 instead of UTF8MB4, then remove MB4 from SQL
print SQLTableCheck::filterSqlCollation($sql);