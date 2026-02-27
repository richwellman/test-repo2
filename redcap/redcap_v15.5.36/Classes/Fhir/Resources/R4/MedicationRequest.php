<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources\R4;

use Vanderbilt\REDCap\Classes\Fhir\Resources\AbstractResource;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Shared\CodeableConcept;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Shared\CodingSystem;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Traits\CanNormalizeTimestamp;

class MedicationRequest extends AbstractResource
{
  use CanNormalizeTimestamp;

  const TIMESTAMP_FORMAT = 'Y-m-d';

  public function getFhirID()
  {
    return $this->scraper()->id->join('');
  }

  public function getStatus()
  {
    return $this->scraper()
      ->status->join('');
  }

  public function getDosageInstructionRoute()
  {
    return $this->scraper()->dosageInstruction->route->text->join('');
  }

  public function getDosageInstructionTiming()
  {
    return $this->scraper()->dosageInstruction->timing->code->text->join('');
  }

  public function getDosageText()
  {
    return $this->scraper()->dosageInstruction->text->join('');
  }

  public function getDateWritten()
  {
    return $this->scraper()->authoredOn->join('');
  }

  public function getMedicationReference()
  {
    return $this->scraper()->medicationReference->display->join('');
  }

  public function rxnormDisplay()
  {
    return $this->scraper()
      ->medicationCodeableConcept->coding
      ->where('system', 'like', CodingSystem::RxNorm)
      ->orWhere('system', 'like', CodingSystem::RxNorm_2)
      ->display->join('');
  }

  public function rxnormCode()
  {
    return $this->scraper()
      ->medicationCodeableConcept->coding
      ->where('system', 'like', CodingSystem::RxNorm)
      ->orWhere('system', 'like', CodingSystem::RxNorm_2)
      ->code->join('');
  }
  
  public function getMedicationCodeableConcept()
  {
    $payload = $this->scraper()->medicationCodeableConcept->getData();
    return new CodeableConcept($payload);
  }

  public function split()
  {
    $codeableConcept = $this->getMedicationCodeableConcept();
    $codings = $codeableConcept->getCoding();
    if(empty($codings)) return [$this];
    if(count($codings)<=1) return [$this];
    $text = $codeableConcept->getText();
    $parentPayload = $this->getPayload();
    $list = array_map(function($coding) use($text, $parentPayload) {
      // make a new code payload that will replace the existing one
      $payload = [
        'coding' => [$coding],
        'text' => $text,
      ];
      return $this->replacePayload($parentPayload, $payload);
    }, $codings);
    return $list;
  }

  public function getNormalizedTimestamp()
  {
    $timestamp = $this->getDateWritten();
    return $this->getGmtTimestamp($timestamp, self::TIMESTAMP_FORMAT);
  }

  public function getText()
  {
    // add medication concept if available
    $medicationCodeableConcept = $this->getMedicationCodeableConcept();
    return $medicationCodeableConcept->getText();
  }

  public function getData()
  {
    $medicationCodeableConcept = $this->getMedicationCodeableConcept();
    $medicationCodeableConceptData = $medicationCodeableConcept->isEmpty() ? '' : $medicationCodeableConcept->getData();
    $data = [
      'fhir_id'=> $this->getFhirID(),
      'status' => $this->getStatus(),
      'display' => $this->getMedicationReference(),
      'timestamp' => $this->getDateWritten(),
      'normalized_timestamp' => $this->getNormalizedTimestamp(), // convert to proper date
      'dosage' => $this->getDosageText(),
      'dosage_instruction_route' => $this->getDosageInstructionRoute(),
      'dosage_instruction_timing' => $this->getDosageInstructionTiming(),
      'rxnorm_display' => $this->rxnormDisplay(),
      'rxnorm_code' => $this->rxnormCode(),
      'medicationCodeableConcept' => $medicationCodeableConceptData,
      'text' => $this->getText(),
    ];
    return $data;
  }
  
}