<?php
namespace Vanderbilt\REDCap\Classes\Email\PlaceholderReplacers;

trait UserInformationTrait {

    public function getUserFieldByMail($useremail, $dbField) {
        $sql = "SELECT $dbField FROM redcap_user_information WHERE user_email = ?";
        $value = '';
        $result = db_query($sql, [$useremail]);
        if($result && ($row = db_fetch_assoc($result))) $value = $row[$dbField] ?? '';
        return $value;
    }

    public function getUserFieldByUIID($ui_id, $dbField) {
        $sql = "SELECT $dbField FROM redcap_user_information WHERE ui_id = ?";
        $value = '';
        $result = db_query($sql, [$ui_id]);
        if($result && ($row = db_fetch_assoc($result))) $value = $row[$dbField] ?? '';
        return $value;
    }
}