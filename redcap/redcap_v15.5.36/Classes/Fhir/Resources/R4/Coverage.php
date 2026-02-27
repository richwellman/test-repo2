<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources\R4;

use Vanderbilt\REDCap\Classes\Fhir\Resources\AbstractResource;

class Coverage extends AbstractResource
{

  public function getFhirID()
  {
    return $this->scraper()->id->join('');
  }

  public function getStatus() {
    return $this->scraper()
      ->status->join('');
  }

  public function getKind() {
    return $this->scraper()
      ->kind->join('');
  }

  public function getPlanName() {
    return $this->scraper()
      ->class
      ->where('type.coding.code', 'like', '/plan/i')
      ->name->join('');
  }

  public function getNetwork() {
    return $this->scraper()
      ->network->join('');
  }

  public function getPeridoStart() {
    return $this->scraper()
      ->period->start->join('');
  }

  public function getPeridoEnd() {
    return $this->scraper()
      ->period->end->join('');
  }

  public function getPeriod() {
    $start = $this->getPeridoStart();
    $end = $this->getPeridoEnd();
    $parts = [];
    $parts[] = $start !== '' ? $start : '---';
    $parts[] = $end !== '' ? $end : '---';
    return join(' / ', $parts);
  }

  public function getOrder() {
    return $this->scraper()
      ->order->join('');
  }

  public function getPayor($index=0) {
    return $this->scraper()
      ->payor[$index]->display->join('');
  }

  public function getTypeText() {
    return $this->scraper()
      ->type->text->join('');
  }

  public function getCostToBeneficiary() {
    return $this->scraper()
      ->costToBeneficiary->getData();
  }

  public function getData()
  {
    $data = [
      'fhir_id'                   => $this->getFhirID(),
      'plan_name'                 => $this->getPlanName(),
      'payor_1'                   => $this->getPayor(0),
      'network'                   => $this->getNetwork(),
      'status'                    => $this->getStatus(),
      'period_start'              => $this->getPeridoStart(),
      'period_end'                => $this->getPeridoEnd(),
      'period'                    => $this->getPeriod(),
      'order'                     => $this->getOrder(),
      'type_text'                 => $this->getTypeText(),
      'cost_to_beneficiary'       => $this->getCostToBeneficiary(),
    ];
    return $data;
  }
  
}