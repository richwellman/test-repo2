<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources\R4;

use Vanderbilt\REDCap\Classes\Fhir\Resources\AbstractResource;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Traits\CanNormalizeTimestamp;

class Appointment extends AbstractResource
{

  use CanNormalizeTimestamp;

  const TIMESTAMP_FORMAT = 'Y-m-d H:i';

  public function getFhirID()
  {
    return $this->scraper()->id->join('');
  }

  /**
   * Undocumented function
   *
   * @return string proposed | pending | booked | arrived | fulfilled | cancelled | noshow | entered-in-error | checked-in | waitlist
   */
  public function getStatus()
  {
    return $this->scraper()->status->join('');
  }

  public function getMinutesDuration()
  {
    return $this->scraper()->minutesDuration->join('');
  }

  public function getCreated()
  {
    return $this->scraper()->created->join('');
  }

  function getNormalizedCreated() {
    $timestamp = $this->getCreated();
    return $this->getGmtTimestamp($timestamp, self::TIMESTAMP_FORMAT);
  }

  public function getStart()
  {
    return $this->scraper()->start->join('');
  }
  
  function getNormalizedStart() {
    $timestamp = $this->getStart();
    return $this->getGmtTimestamp($timestamp, self::TIMESTAMP_FORMAT);
  }

  public function getEnd()
  {
    return $this->scraper()->end->join('');
  }
  
  function getNormalizedEnd() {
    $timestamp = $this->getEnd();
    return $this->getGmtTimestamp($timestamp, self::TIMESTAMP_FORMAT);
  }

  public function getCancellationDate()
  {
    return $this->scraper()->cancellationDate->join('');
  }

  public function getCancellationReason()
  {
    return $this->scraper()->cancellationReason->getData();
  }

  public function getCancellationReasonText()
  {
    return $this->scraper()->cancellationReason->text->join('');
  }

  public function getDescription()
  {
    return $this->scraper()->description->join('');
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

  public function getAppointmentType($index=0) {
    return $this->scraper()
      ->appointmentType->coding[$index]->display->join('');
  }

  public function getServiceType($index=0) {
    return $this->scraper()
      ->serviceType->coding[$index]->display->join('');
  }

  public function getActor($index=0) {
    return $this->scraper()
      ->participant[$index]->actor->display->join('');
  }

  public function getPractitionerDisplay() {
    return $this->scraper()
      ->participant->actor
      ->where('reference', 'like', '/practitioner/i')
      ->display->join('');
  }

  public function getLocationDisplay() {
    return $this->scraper()
      ->participant->actor
      ->where('reference', 'like', '/location/i')
      ->display->join('');
  }

  public function getPatientInstruction() {
    return $this->scraper()
      ->patientInstruction->join('');
  }

  public function getData()
  {
    $data = [
      'fhir_id'                   => $this->getFhirID(),
      'status'                    => $this->getStatus(),
      'created'                   => $this->getCreated(),
      'start'                     => $this->getStart(),
      'end'                       => $this->getEnd(),
      'normalized_created'        => $this->getNormalizedCreated(),
      'normalized_start'          => $this->getNormalizedStart(),
      'normalized_end'            => $this->getNormalizedEnd(),
      'minutes_duration'          => $this->getMinutesDuration(),
      'cancellation_date'         => $this->getCancellationDate(),
      'practitioner'              => $this->getPractitionerDisplay(),
      'location'                  => $this->getLocationDisplay(),
      'description'               => $this->getDescription(),
      'patient_instruction'       => $this->getPatientInstruction(),
      'note_time_1'               => $this->getNoteTime(0),
      'note_text_1'               => $this->getNoteText(0),
      'appointment_type_1'        => $this->getAppointmentType(0),
      'service_type_1'            => $this->getServiceType(0),
      // 'actor_1'                   => $this->getActor(0),
      // 'actor_2'                   => $this->getActor(1),
      // 'actor_3'                   => $this->getActor(2),
      'cancellation_reason'       => $this->getCancellationReason(),
      'cancellation_reason_text'  => $this->getCancellationReasonText(),
    ];
    return $data;
  }
  
}