<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources\R4;

use Vanderbilt\REDCap\Classes\Fhir\Resources\AbstractResource;
use Vanderbilt\REDCap\Classes\Fhir\Resources\TimestampInterface;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Traits\CanNormalizeTimestamp;

class Binary extends AbstractResource
{
  use CanNormalizeTimestamp;

  const TIMESTAMP_FORMAT = 'Y-m-d H:i';

  

  public function getData()
  {
    $data = [

    ];
    return $data;
  }
  
}