<?php
namespace Vanderbilt\REDCap\Classes\Email\PlaceholderReplacers;

class LastNameReplacer extends BaseReplacer {
    use UserInformationTrait;

    public function __construct($ui_id) {
        $this->value = $this->getUserFieldByUIID($ui_id, 'user_lastname');
    }

    public static function token() { return 'last_name'; }
}