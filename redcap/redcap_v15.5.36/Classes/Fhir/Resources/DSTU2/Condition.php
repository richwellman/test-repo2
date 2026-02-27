<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources\DSTU2;

use Vanderbilt\REDCap\Classes\Fhir\Resources\AbstractResource;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Shared\CodingSystem;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Shared\CodeableConcept;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Traits\CanNormalizeTimestamp;

class Condition extends AbstractResource
{

  use CanNormalizeTimestamp;

  const TIMESTAMP_FORMAT = 'Y-m-d H:i';

  public function getFhirID()
  {
    return $this->scraper()->id->join('');
  }

  public function getClinicalStatus()
  {
    return $this->scraper()
      ->where('clinicalStatus', 'is string')
      ->join('');
  }

  public function getCategoryCode()
  {
    return $this->scraper()
      ->category->coding->code->join('');
  }

  public function getClinicalStatusCode()
  {
    return $this->scraper()
      ->clinicalStatus->coding->code->join('');
  }

  public function getRecordedDate()
  {
    return $this->scraper()
      ->dateRecorded->join('');
  }

  function getNormalizedTimestamp() {
    $timestamp = $this->getRecordedDate();
    return $this->getGmtTimestamp($timestamp, self::TIMESTAMP_FORMAT);
  }

  public function getVerificationStatus()
  {
    return $this->scraper()
    ->verificationStatus->coding->code
    ->join('');
  }

  public function getRecorder()
  {
    return $this->scraper()
      ->recorder->display->join('');
  }

  public function getLabel()
  {
    return $this->scraper()->code->text->join('');
  }

  /**
   *
   * @return CodeableConcept
   */
  public function getCode()
  {
    $payload = $this->scraper()
      ->code->getData();
    return new CodeableConcept($payload);
  }

  /**
   * create a CodeableConcept from the code
   * portion of the payload
   *
   * @return CodeableConcept
   */
  public function getIcd10()
  {
    return $this->scraper()
      ->code->coding
      ->where('system', 'like', CodingSystem::ICD_10_CM)
      ->orWhere('system', 'like', CodingSystem::ICD_10_CM_1)
      ->orWhere('system', 'like', CodingSystem::ICD_10_INTERNATIONAL_WHO)
      ->orWhere('system', 'like', CodingSystem::ICD_10_INTERNATIONAL_WHO_DUTCH_VARIANT)
      ->orWhere('system', 'like', CodingSystem::ICD_10_AE)
      ->orWhere('system', 'like', CodingSystem::ICD_10_PCS)
      ->orWhere('system', 'like', CodingSystem::ICD_10_AM)
      ->orWhere('system', 'like', CodingSystem::ICD_10_CANADA)
      ->orWhere('system', 'like', CodingSystem::ICD_10_CANADA_1)
      ->orWhere('system', 'like', CodingSystem::ICD_10_NL)
      ->orWhere('system', 'like', CodingSystem::ICD_10_NL_1)
      [0]->getData();
  }

  /**
   * create a CodeableConcept from the code
   * portion of the payload
   *
   * @return CodeableConcept
   */
  public function getIcd9()
  {
    return $this->scraper()
      ->code->coding
      ->where('system', 'like', CodingSystem::ICD_9_CM)
      [0]->getData();
  }

  /**
   * create a CodeableConcept from the code
   * portion of the payload
   *
   * @return CodeableConcept
   */
  public function getSnomed()
  {
    return $this->scraper()
      ->code->coding
      ->where('system', 'like', CodingSystem::SNOMED_CT)
      ->orWhere('system', 'like', CodingSystem::SNOMED_CT_1)
      [0]->getData();
  }

  public function getDisplayIcd10()
  {
    return $this->scraper()
        ->code->coding
        ->where('system', 'like', CodingSystem::ICD_10_CM)
        ->orWhere('system', 'like', CodingSystem::ICD_10_CM_1)
        ->orWhere('system', 'like', CodingSystem::ICD_10_INTERNATIONAL_WHO)
        ->orWhere('system', 'like', CodingSystem::ICD_10_INTERNATIONAL_WHO_DUTCH_VARIANT)
        ->orWhere('system', 'like', CodingSystem::ICD_10_AE)
        ->orWhere('system', 'like', CodingSystem::ICD_10_PCS)
        ->orWhere('system', 'like', CodingSystem::ICD_10_AM)
        ->orWhere('system', 'like', CodingSystem::ICD_10_CANADA)
        ->orWhere('system', 'like', CodingSystem::ICD_10_CANADA_1)
        ->orWhere('system', 'like', CodingSystem::ICD_10_NL)
        ->orWhere('system', 'like', CodingSystem::ICD_10_NL_1)
        ->display->join('');
  }

  public function getCodeIcd10()
  {
    return $this->scraper()->code->coding
      ->where('system', 'like', CodingSystem::ICD_10_CM)
      ->orWhere('system', 'like', CodingSystem::ICD_10_CM_1)
      ->orWhere('system', 'like', CodingSystem::ICD_10_INTERNATIONAL_WHO)
      ->orWhere('system', 'like', CodingSystem::ICD_10_INTERNATIONAL_WHO_DUTCH_VARIANT)
      ->orWhere('system', 'like', CodingSystem::ICD_10_AE)
      ->orWhere('system', 'like', CodingSystem::ICD_10_PCS)
      ->orWhere('system', 'like', CodingSystem::ICD_10_AM)
      ->orWhere('system', 'like', CodingSystem::ICD_10_CANADA)
      ->orWhere('system', 'like', CodingSystem::ICD_10_CANADA_1)
      ->orWhere('system', 'like', CodingSystem::ICD_10_NL)
      ->orWhere('system', 'like', CodingSystem::ICD_10_NL_1)
      ->code->join('');
  }

  public function getDisplayIcd9()
  {
    return $this->scraper()
      ->code->coding
      ->where('system', 'like', CodingSystem::ICD_9_CM)
      ->display->join('');
  }

  public function getCodeIcd9()
  {
    return $this->scraper()
      ->code->coding
      ->where('system', 'like', CodingSystem::ICD_9_CM)
      ->code->join('');
  }

  public function getDisplaySnomedCt()
  {
    return $this->scraper()->code->coding
      ->where('system', 'like', CodingSystem::SNOMED_CT)
      ->orWhere('system', 'like', CodingSystem::SNOMED_CT_1)
      ->dislpay->join('');
  }

  public function getCodeSnomedCt()
  {
    return $this->scraper()->code->coding
    ->where('system', 'like', CodingSystem::SNOMED_CT)
    ->orWhere('system', 'like', CodingSystem::SNOMED_CT_1)
    ->code->join('');
  }

  public function getNote()
  {
    return $this->scraper()->note->text->join('');
  }

  public function getEncounterReference()
  {
    return $this->scraper()->encounter->reference->join('');
  }

  public function getEncounterDisplay()
  {
    return $this->scraper()
      ->encounter->display->join('');
  }

  public function getData()
  {
    $data = [
      'fhir_id'               => $this->getFhirID(),
      'clinical_status'       => $this->getClinicalStatus() ?: ($this->getClinicalStatusCode() ?: ''),
      'timestamp'             => $this->getRecordedDate(),
      'normalized_timestamp'  => $this->getNormalizedTimestamp(),
      'label'                 => $label = $this->getLabel(),
      'icd-10-code'           => $icd10_code = $this->getCodeIcd10(),
      'icd-10-display'        => $this->getDisplayIcd10() ?: ($icd10_code ? $label : ''), //fallback to label is code and !display
      'icd-9-code'            => $icd9_code =$this->getCodeIcd9(),
      'icd-9-display'         => $this->getDisplayIcd9() ?: ($icd9_code ? $label : ''), //fallback to label is code and !display
      'snomed-ct-code'        => $snomedCt_code = $this->getCodeSnomedCt(),
      'snomed-ct-display'     => $this->getDisplaySnomedCt() ?: ($snomedCt_code ? $label : ''), //fallback to label is code and !display
      'verification-status'   => $this->getVerificationStatus(),
      'recorder'              => $this->getRecorder(),
      'note'                  => $this->getNote(),
      'encounter_reference'   => $this->getEncounterReference(),
      'encounter_label'       => $this->getEncounterDisplay(),
      'category_code'         => $this->getCategoryCode(),
    ];
    return $data;
  }
  
}