<?php
namespace Vanderbilt\REDCap\Classes\Email\PlaceholderReplacers;

class UsernameReplacer extends BaseReplacer {
    use UserInformationTrait;

    public function __construct($ui_id) {
        $this->value = $this->getUserFieldByUIID($ui_id, 'username');
    }

    public static function token() { return 'username'; }
}