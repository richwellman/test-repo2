<?php

$sql = "
REPLACE INTO redcap_config (field_name, value) VALUES
('oauth2_azure_ad_username_attribute', 'userPrincipalName');
";

// If db is using UTF8 instead of UTF8MB4, then remove MB4 from SQL
print SQLTableCheck::filterSqlCollation($sql);