<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources\R4;

use Vanderbilt\REDCap\Classes\Fhir\Resources\AbstractResource;


class Practitioner extends AbstractResource
{

  public function getFhirID()
  {
    return $this->scraper()->id->join('');
  }
  
  public function getNameGiven()
  {
    return $this->scraper()
    ->name->where('use', '=', 'usual')
    ->given->join('');
  }
  
  public function getNameFamily()
  {
    return $this->scraper()
      ->name->where('use', '=', 'usual')
      ->family->join('');
  }

  public function getData()
  {
    $data = [
      'fhir_id'     =>  $this->getFhirID(),
      'name-given'  =>  $this->getNameGiven(),
      'name-family' =>  $this->getNameFamily(),
    ];
    return $data;
  }
  
}