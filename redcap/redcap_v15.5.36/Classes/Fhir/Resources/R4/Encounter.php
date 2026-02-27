<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources\R4;

use Vanderbilt\REDCap\Classes\Fhir\Resources\AbstractResource;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Shared\CodeableConcept;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Traits\CanNormalizeTimestamp;

class Encounter extends AbstractResource
{

  use CanNormalizeTimestamp;

  const TIMESTAMP_FORMAT = 'Y-m-d H:i';

  public function getFhirID()
  {
    return $this->scraper()->id->join('');
  }

  /**
   * get the local or GMT timestamp
   * of the start encounter
   * 
   * @param boolean $localTimestamp
   * @return string
   */
  public function getTimestampStart($localTimestamp=false)
  {
    $callable = $this->getTimestampCallable($this->getPeriodStart(), self::TIMESTAMP_FORMAT);
    return $callable($localTimestamp);
  }

  /**
   * get the local or GMT timestamp
   * of the end encounter
   * 
   * @param boolean $localTimestamp
   * @return string
   */
  public function getTimestampEnd($localTimestamp=false)
  {
    $callable = $this->getTimestampCallable($this->getPeriodEnd(), self::TIMESTAMP_FORMAT);
    return $callable($localTimestamp);
  }

  
  public function getTypeDisplay()
  {
    return $this->scraper()->type->coding[0]->display->join('');
  }

  public function getTypeText()
  {
    return $this->scraper()->type->text->join('');
  }

  public function getPeriodStart()
  {
    return $this->scraper()->period->start->join('');
  }

  public function getPeriodEnd()
  {
    return $this->scraper()->period->end->join('');
  }

  public function getLocation()
  {
    return $this->scraper()->location->location->display->join('');
  }

  public function getReasonCodeDisplay()
  {
    return $this->scraper()->reasonCode->coding[0]->display->join('');
  }

  public function getReasonCodeText()
  {
    return $this->scraper()->reasonCode->text->join('');
  }

  /**
   * create a CodeableConcept from the code
   * portion of the payload
   *
   * @return CodeableConcept
   */
  public function getReasonCode()
  {
    $payload = $this->scraper()->reasonCode->getData();
    return new CodeableConcept($payload);
  }

  public function getClass()
  {
    return $this->scraper()->class->display->join('');
  }

  public function getStatus()
  {
    return $this->scraper()->status->join('');
  }

  public function getData()
  {
    $data = [
      'fhir_id'                 => $this->getFhirID(),
      'type'                    => $this->getTypeDisplay() ?? $this->getTypeText(),
      'reason'                  => $this->getReasonCodeDisplay() ?? $this->getReasonCodeText(),
      'class'                   => $this->getClass(),
      'status'                  => $this->getStatus(),
      'location'                => $this->getLocation(),
      'period-start'            => $this->getPeriodStart(),
      'period-end'              => $this->getPeriodEnd(),
    ];
    return $data;
  }
  
}