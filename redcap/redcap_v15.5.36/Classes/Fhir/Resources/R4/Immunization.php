<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources\R4;

use Vanderbilt\REDCap\Classes\Fhir\Resources\AbstractResource;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Shared\CodingSystem;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Traits\CanNormalizeTimestamp;

class Immunization extends AbstractResource
{
  use CanNormalizeTimestamp;

  const TIMESTAMP_FORMAT = 'Y-m-d';

  public function getFhirID()
  {
    return $this->scraper()->id->join('');
  }

  public function getStatus()
  {
    return $this->scraper()->status->join('');
  }

  public function getText()
  {
    return $this->scraper()->vaccineCode->text->join('');
  }

  public function getDate()
  {
    return $this->scraper()->occurrenceDateTime->join('');
  }

  public function getCvxCode()
  {
    return $this->scraper()
      ->vaccineCode->coding
      ->where('system', 'like', CodingSystem::CVX)
      ->code->join('');
  }


  public function getNormalizedTimestamp()
  {
    $timestamp = $this->getDate();
    return $this->getGmtTimestamp($timestamp, self::TIMESTAMP_FORMAT);
  }

  public function getData()
  {
    $data = [
      'fhir_id' => $this->getFhirID(),
      'text' => $this->getText(),
      'date' => $this->getDate(),
      'normalized_date' => $this->getNormalizedTimestamp(),
      'status' => $this->getStatus(),
      'cvx_code' => $this->getCvxCode(),
    ];
    return $data;
  }
  
}