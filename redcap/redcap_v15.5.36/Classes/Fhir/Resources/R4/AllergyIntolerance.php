<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources\R4;

use Vanderbilt\REDCap\Classes\Fhir\Resources\AbstractResource;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Shared\CodingSystem;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Traits\CanNormalizeTimestamp;

/**
 * please note that coding results for
 * this resource can return multiple entries for the same coding system.
 * this behavior is similar to Observation resources
 */
class AllergyIntolerance extends AbstractResource
{
  use CanNormalizeTimestamp;

  const TIMESTAMP_FORMAT = 'Y-m-d';

  public function getFhirID()
  {
    return $this->scraper()->id->join('');
  }

  public function recordedDate()
  {
    return $this->scraper()->recordedDate->join('');
  }

  public function clinicalStatus()
  {
    return $this->scraper()->clinicalStatus->coding->display->join('');
  }

  public function getText()
  {
    return $this->scraper()->code->text->join('');
  }

  public function getNdfRt()
  {
    return $this->scraper()
      ->code->coding
      ->where('system', 'like', CodingSystem::NDF_RT)[0]->getData();
  }

  public function getFdaUnii()
  {
    return $this->scraper()
      ->code->coding
      ->where('system', 'like', CodingSystem::FDA_UNII)
      ->orWhere('system', 'like', CodingSystem::FDA_UNII_2)
      ->orWhere('system', 'like', CodingSystem::FDA_UNII_3)[0]
      ->getData();
  }

  public function getRxnorm()
  {
    return $this->scraper()
      ->code->coding
      ->where('system', 'like', CodingSystem::RxNorm)
      ->orWhere('system', 'like', CodingSystem::RxNorm_2)[0]
      ->getData();
  }

  public function getSnomed()
  {
    return $this->scraper()
      ->code->coding
      ->where('system', 'like', CodingSystem::SNOMED_CT)
      ->orWhere('system', 'like', CodingSystem::SNOMED_CT_1)[0]
      ->getData();
  }


  public function ndfRtDisplay()
  {
    return $this->scraper()
      ->code->coding
      ->where('system', 'like', CodingSystem::NDF_RT)
      ->orWhere('system', 'like', CodingSystem::NDF_RT_1)[0]
      ->display->join('');
  }

  public function ndfRtCode()
  {
    return $this->scraper()
      ->code->coding
      ->where('system', 'like', CodingSystem::NDF_RT)
      ->orWhere('system', 'like', CodingSystem::NDF_RT_1)[0]
      ->code->join('');
  }

  public function fdaUniiDisplay()
  {
    return $this->scraper()
      ->code->coding
      ->where('system', 'like', CodingSystem::FDA_UNII)
      ->orWhere('system', 'like', CodingSystem::FDA_UNII_2)
      ->orWhere('system', 'like', CodingSystem::FDA_UNII_3)[0]
      ->display->join('');
  }

  public function fdaUniiCode()
  {
    return $this->scraper()
      ->code->coding
      ->where('system', 'like', CodingSystem::FDA_UNII)
      ->orWhere('system', 'like', CodingSystem::FDA_UNII_2)
      ->orWhere('system', 'like', CodingSystem::FDA_UNII_3)[0]
      ->code->join('');
  }

  public function rxnormDisplay()
  {
    return $this->scraper()
      ->code->coding
      ->where('system', 'like', CodingSystem::RxNorm)
      ->orWhere('system', 'like', CodingSystem::RxNorm_2)[0]
      ->display->join('');
  }

  public function rxnormCode()
  {
    return $this->scraper()
      ->code->coding
      ->where('system', 'like', CodingSystem::RxNorm)
      ->orWhere('system', 'like', CodingSystem::RxNorm_2)[0]
      ->code->join('');
  }
  
  public function snomedDisplay()
  {
    return $this->scraper()
      ->code->coding
      ->where('system', 'like', CodingSystem::SNOMED_CT)
      ->orWhere('system', 'like', CodingSystem::SNOMED_CT_1)[0]
      ->display->join('');
  }

  public function snomedCode()
  {
    return $this->scraper()
      ->code->coding
      ->where('system', 'like', CodingSystem::SNOMED_CT)
      ->orWhere('system', 'like', CodingSystem::SNOMED_CT_1)[0]
      ->code->join('');
  }

  public function getNormalizedTimestamp()
  {
    $timestamp = $this->recordedDate();
    return $this->getGmtTimestamp($timestamp, self::TIMESTAMP_FORMAT);
  }

  /**
   * return the relevant data for the resource
   *
   * @return array
   */
  public function getData()
  {
    return [
      'fhir_id' => $this->getFhirID(),
      'recorded_date' => $timestamp = $this->recordedDate(),
      'normalized_timestamp' => $this->getGmtTimestamp($timestamp, self::TIMESTAMP_FORMAT), // convert to proper date
      'clinical_status' => $this->clinicalStatus(),
      'text' => $this->getText(),
      'ndf_rt_display' => $this->ndfRtDisplay(),
      'ndf_rt_code' => $this->ndfRtCode(),
      'fda_unii_display' => $this->fdaUniiDisplay(),
      'fda_unii_code' => $this->fdaUniiCode(),
      'snomed_display' => $this->snomedDisplay(),
      'snomed_code' => $this->snomedCode(),
      'rxnorm_display' => $this->rxnormDisplay(),
      'rxnorm_code' => $this->rxnormCode(),
    ];
  }
  
}