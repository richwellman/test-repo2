<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources\R4;

use Vanderbilt\REDCap\Classes\Fhir\Resources\AbstractResource;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Shared\CodeableConcept;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Shared\CodingSystem;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Traits\CanNormalizeTimestamp;
use Vanderbilt\REDCap\Classes\Traits\CanConvertMimeTypeToExtension;

class Procedure extends AbstractResource
{
  use CanNormalizeTimestamp;
  use CanConvertMimeTypeToExtension;

  const TIMESTAMP_FORMAT = 'Y-m-d H:i';

  public function getFhirID() {
    return $this->scraper()->id->join('');
  }

  public function getStatus() {
    return $this->scraper()->status->join('');
  }

  public function getEncounterReference()
  {
    return $this->scraper()->encounter->reference->join('');
  }

  public function getCodeText() {
    return $this->scraper()->code->text->join('');
  }

  public function getCodeCpt() {
    return $this->scraper()->code->coding->where('system','~', CodingSystem::AMA_CPT)->code->join('');
  }

  public function getDisplayCpt() {
    return $this->scraper()->code->coding->where('system','~', CodingSystem::AMA_CPT)->display->join('');
  }

  public function getCategory() {
    return $this->scraper()->category->text->join('');
  }

  public function getCategoryDisplay($index=0) {
    return $this->scraper()->category->coding[$index]->display->join('');
  }

  public function getReason() {
    return $this->scraper()->reasonCode->text->join('');
  }

  public function getReasonDisplay($index=0) {
    return $this->scraper()->reasonCode->coding[$index]->display->join('');
  }

  public function getAuthorType($index=0) {
    return $this->scraper()->author[$index]->type->join('');
  }

  public function getPerformedDateTime() {
    return $this->scraper()->performedDateTime->join('');
  }


  public function getOutcome() {
    $codingsPayload = $this->scraper()->type[0]->getData();
    return new CodeableConcept($codingsPayload);
  }

  public function getComplication() {
    $codingsPayload = $this->scraper()->type[0]->getData();
    return new CodeableConcept($codingsPayload);
  }

  public function getNote() {
    $notes = $this->scraper()->note->getData();
    $text = '';
    if(!is_array(($notes))) return $text;
    foreach ($notes as $note) {
      $text .= $note['text'];
    }
    return $text;
  }


  public function getData()
  {
    $data = [
      'fhir_id' => $this->getFhirID(),
      'status' => $this->getStatus(),
      'category' => $this->getCategoryDisplay() ?: ($this->getCategory() ?: ''),
      'category-display' => $this->getCategoryDisplay(),
      'category-text' => $this->getCategory(),
      'reason' => $this->getReasonDisplay() ?: ($this->getReason() ?: ''),
      'reason-display' => $this->getReasonDisplay(),
      'reason-text' => $this->getReason(),
      'outcome' => $this->getOutcome(),
      'complication' => $this->getComplication(),
      'encounter_reference' => $this->getEncounterReference(),
      'performed-date-time' => $this->getPerformedDateTime(),
      'code' => $this->getCodeText(),
      'cpt-code' => $this->getCodeCpt(),
      'cpt-display' => $this->getDisplayCpt(),
      'note' => $this->getNote(),
    ];
    return $data;
  }




}