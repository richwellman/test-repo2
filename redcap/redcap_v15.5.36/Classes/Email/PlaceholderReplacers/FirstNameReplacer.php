<?php
namespace Vanderbilt\REDCap\Classes\Email\PlaceholderReplacers;

class FirstNameReplacer extends BaseReplacer {
    use UserInformationTrait;
    
    public function __construct($ui_id) {
        $this->value = $this->getUserFieldByUIID($ui_id, 'user_firstname');
    }

    public static function token() { return 'first_name'; }
}