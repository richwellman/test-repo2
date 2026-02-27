<?php
namespace Vanderbilt\REDCap\Classes\Traits;

use DateTime;

trait CanMakeDateTimeFromInterval {
    

	  /**
     * get a DateTime to use as reference
     *
     * @param string $time_string
     * @return DateTime
     */
    public function getDateTimeFromInterval(string $time_string='30 minutes') : DateTime {
        $start = new \DateTime();
        $interval = \DateInterval::createFromDateString($time_string);
        if ($interval === false) {
            $message = sprintf("Invalid time interval string provided: '%s'", $time_string);
            throw new \InvalidArgumentException($message);
        }
        return $start->add($interval);
    }
}
