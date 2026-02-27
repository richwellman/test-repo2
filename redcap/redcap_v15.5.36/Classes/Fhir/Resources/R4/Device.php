<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources\R4;

use Vanderbilt\REDCap\Classes\Fhir\Resources\AbstractResource;

class Device extends AbstractResource
{

  public function getFhirID()
  {
    return $this->scraper()->id->join('');
  }

  public function getStatus() {
    return $this->scraper()
      ->status->join('');
  }

  public function getDeviceName() {
    return $this->scraper()
      ->deviceName->name->join('');
  }

  public function getType() {
    return $this->scraper()
      ->type->text->join('');
  }

  public function getModelNumber() {
    return $this->scraper()
      ->modelNumber->join('');
  }

  public function getExpirationDate() {
    return $this->scraper()
      ->expirationDate->join('');
  }

  public function getProperty($textRegExp) {
    $siteProperty = $this->scraper()
      ->property
      ->where('type.coding.text', 'like', $textRegExp);
    $display = $siteProperty->valueCode->coding->display->join('');
    $text = $siteProperty->valueCode->text->join('');
    return ($display !== '') ? $display : $text;
  }

  public function getSite() {
    return $this->getProperty('/site/i');
  }

  public function getPermanence() {
    return $this->getProperty('/permanence/i');
  }

  public function getLaterality() {
    return $this->getProperty('/laterality/i');
  }

  public function getRadioactive() {
    return $this->getProperty('/radioactive/i');
  }


  public function getNoteTime($index=0)
  {
    return $this->scraper()
      ->note[$index]->time->join('');
  }

  public function getNoteText($index=0)
  {
    return $this->scraper()
      ->note[$index]->text->join('');
  }

  /* public function getSite1() {
    $syteCodingSystem = $this->scraper()
                        ->property
                        ->where('type.coding.system', 'like', CodingSystem::SNOMED_CT)
                        ->orWhere('type.coding.system', 'like', CodingSystem::SNOMED_CT_1)[0];
    return $syteCodingSystem
              ->where('type.coding.code', '=', '442083009')
              ->getData();
  } */

  public function getData()
  {
    $data = [
      'fhir_id'         => $this->getFhirID(),
      'device_name'     => $this->getDeviceName(),
      'type'            => $this->getType(),
      'model_number'    => $this->getModelNumber(),
      'expiration_date' => $this->getExpirationDate(),
      'site'            => $this->getSite(),
      'permanence'      => $this->getPermanence(),
      'laterality'      => $this->getLaterality(),
      'radioactive'     => $this->getRadioactive(),
      'note_time_1'     => $this->getNoteTime(0),
      'note_text_1'     => $this->getNoteText(0),
    ];
    return $data;
  }
  
}