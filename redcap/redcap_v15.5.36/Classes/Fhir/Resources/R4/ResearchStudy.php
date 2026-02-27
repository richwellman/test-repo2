<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources\R4;

use Vanderbilt\REDCap\Classes\Fhir\Resources\AbstractResource;

class ResearchStudy extends AbstractResource
{
  
  public function getFhirID()
  {
    return $this->scraper()->id->join('');
  }

  public function getTitle()
  {
    return $this->scraper()->title->join('');
  }

  public function getStatus()
  {
    return $this->scraper()->status->join('');
  }

  public function getPrincipalInvestigator()
  {
    return $this->scraper()->principalInvestigator->display->join('');
  }

  public function getData()
  {
    $data = [
      'id' => $this->getFhirID(),
      'title' => $this->getTitle(),
      'status' => $this->getStatus(),
      'principal-investigator' => $this->getPrincipalInvestigator(),
    ];
    return $data;
  }
  
}