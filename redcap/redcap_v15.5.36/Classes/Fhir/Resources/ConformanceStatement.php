<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources;

/**
 * Factory for factories.
 * Get the factory that will build endpoints for a specific FHIR version.
 */
class ConformanceStatement extends AbstractResource
{

  public function getFhirVersion()
  {
    return $this->scraper()->fhirVersion->join('');
  }

  public function getSoftwareName()
  {
    return $this->scraper()->software->name->join('');
  }

  public function getName()
  {
    return $this->scraper()->name->join('');
  }

  public function getTitle()
  {
    return $this->scraper()->title->join('');
  }

  public function getPublisher()
  {
    return $this->scraper()->publisher->join('');
  }

  public function getImplementationDescription()
  {
    return $this->scraper()->implementation->description->join('');
  }

  public function getImplementationUrl()
  {
    return $this->scraper()->implementation->url->join('');
  }
  
  public function getResources() {
    
    return $this->scraper()->rest->resource->getData();
  }

   public function getData()
   {
     return [
      'fhirVersion' => $this->getFhirVersion(),
      'resources' => $this->getResources(),
     ];
   }
}