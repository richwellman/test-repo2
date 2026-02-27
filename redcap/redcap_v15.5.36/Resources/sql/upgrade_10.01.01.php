<?php

$sql = "
CREATE TABLE `redcap_outgoing_email_counts` (
`date` date NOT NULL,
`send_count` int(10) DEFAULT '1',
PRIMARY KEY (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO redcap_config (field_name, value) VALUES ('sendgrid_api_key', '');
";
// If db is using UTF8 instead of UTF8MB4, then remove MB4 from SQL
print SQLTableCheck::filterSqlCollation($sql);