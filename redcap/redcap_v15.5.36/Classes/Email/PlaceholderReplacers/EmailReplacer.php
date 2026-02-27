<?php
namespace Vanderbilt\REDCap\Classes\Email\PlaceholderReplacers;

class EmailReplacer extends BaseReplacer {
    use UserInformationTrait;

    public function __construct($ui_id) {
        $this->value = $this->getUserFieldByUIID($ui_id, 'user_email');
    }

    public static function token() { return 'email'; }
}