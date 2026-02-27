<?php

// Set processed = '1' (action needed = no) to automated messages sent from app upon deleting project from app-side exa. "#DELETED PROJECT--PushID:{PushID}"
// These messages are not visible in message list and after executing below SQL, these will not counted as messages needed action
$sql = "
UPDATE 
    redcap_mycap_messages 
SET 
    processed = '1' 
WHERE body LIKE '#DELETED PROJECT%';
";

print $sql;
