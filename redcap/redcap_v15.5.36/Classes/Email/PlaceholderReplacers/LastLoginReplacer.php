<?php
namespace Vanderbilt\REDCap\Classes\Email\PlaceholderReplacers;

class LastLoginReplacer extends BaseReplacer {
    use UserInformationTrait;

    public function __construct($ui_id) {
        $this->value = $this->getUserFieldByUIID($ui_id, 'user_lastlogin');
    }

    public static function token() { return 'last_login'; }
}