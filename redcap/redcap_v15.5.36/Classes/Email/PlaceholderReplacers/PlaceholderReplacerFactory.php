<?php
namespace Vanderbilt\REDCap\Classes\Email\PlaceholderReplacers;

class PlaceholderReplacerFactory {

    /**
     * Undocumented function
     *
     * @param string $placeholder
     * @param array $args
     * @return PlaceholderReplacerInterface|null
     */
    public static function make($placeholder, ...$args) {
        $replacer = null;
        switch ($placeholder) {
            case RedcapInstitutionReplacer::token():
                $replacer = new RedcapInstitutionReplacer();
                break;
            case RedcapUrlReplacer::token():
                $replacer = new RedcapUrlReplacer();
                break;
            case FirstNameReplacer::token():
                $ui_id = $args[0] ?? null;
                $replacer = new FirstNameReplacer($ui_id);
                break;
            case LastNameReplacer::token():
                $ui_id = $args[0] ?? null;
                $replacer = new LastNameReplacer($ui_id);
                break;
            case UsernameReplacer::token():
                $ui_id = $args[0] ?? null;
                $replacer = new UsernameReplacer($ui_id);
                break;
            case EmailReplacer::token():
                $ui_id = $args[0] ?? null;
                $replacer = new EmailReplacer($ui_id);
                break;
            case LastLoginReplacer::token():
                $ui_id = $args[0] ?? null;
                $replacer = new LastLoginReplacer($ui_id);
                break;
            default:
                break;
        }
        return $replacer;
    }
}
