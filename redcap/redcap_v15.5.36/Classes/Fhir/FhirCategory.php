<?php
namespace Vanderbilt\REDCap\Classes\Fhir;

use Vanderbilt\REDCap\Classes\Fhir\Endpoints\EndpointIdentifier;

/**
 * define the list of FHIR categories available in REDCap.
 * These categories are used by the Endpoint Factories and
 * match the categories in the metadata file.
 */
abstract class FhirCategory
{
  /**
   * list of available FHIR categories in REDCap
   */
  const ALLERGY_INTOLERANCE = 'Allergy Intolerance';
  const ADVERSE_EVENT = 'Adverse Event';
  const DEMOGRAPHICS = 'Demographics';
  const CONDITION = 'Condition';
  const CONDITION_PROBLEMS = 'Condition - Problems';
  
  const CONDITION_DENTAL_FINDING = 'Condition - Dental Finding'; // new!!!!!
  const CONDITION_GENOMICS = 'Condition - Genomics'; // new!!!!!
  const CONDITION_INFECTION = 'Condition - Infection'; // new!!!!!
  const CONDITION_MEDICAL_HISTORY = 'Condition - Medical History'; // new!!!!!
  const CONDITION_REASON_FOR_VISIT = 'Condition - Reason for Visit'; // new!!!!!
  const COVERAGE = 'Coverage'; // new!!!!!
  const DEVICE_IMPLANTS = 'Device - Implants'; // new!!!!!
  const APPOINTMENT_APPOINTMENTS = 'Appointment - Appointments';
  const APPOINTMENT_SCHEDULED_SURGERIES = 'Appointment - Scheduled Surgeries';

  const LABORATORY = 'Laboratory'; // observation
  const VITAL_SIGNS = 'Vital Signs'; // observation
  const CORE_CHARACTERISTICS = 'Core Characteristics'; // an Epic only observation
  const SMART_DATA = 'SmartData'; // an Epic only observation
  const SOCIAL_HISTORY = 'Social History';
  const ENCOUNTER = 'Encounter';
  const IMMUNIZATION = 'Immunization';
  const MEDICATIONS = 'Medications';
  const RESEARCH_STUDY = 'Research Study';
  const DIAGNOSIS = 'Diagnosis'; // another type of condition
  const PROCEDURE = 'Procedure';
  const PRACTITIONER = 'Practitioner';
  const DOCUMENT_REFERENCE_CLINICAL_NOTES = 'Document Reference - Clinical Notes';
  
  protected static $categoryData = [
    self::ALLERGY_INTOLERANCE => [
      'r4' => EndpointIdentifier::ALLERGY_INTOLERANCE,
      'dstu2' => EndpointIdentifier::ALLERGY_INTOLERANCE,
    ],
    self::ADVERSE_EVENT => [
      'r4' => EndpointIdentifier::ADVERSE_EVENT,
      'dstu2' => null,
    ],
    self::DEMOGRAPHICS => [
      'r4' => EndpointIdentifier::PATIENT,
      'dstu2' => EndpointIdentifier::PATIENT,
    ],
    self::CONDITION_PROBLEMS => [
      'r4' => EndpointIdentifier::CONDITION_PROBLEMS,
      'dstu2' => EndpointIdentifier::CONDITION_PROBLEMS,
    ],
    self::CONDITION => [
      'r4' => EndpointIdentifier::CONDITION,
      'dstu2' => EndpointIdentifier::CONDITION,
    ],
    self::CORE_CHARACTERISTICS => [
      'r4' => EndpointIdentifier::OBSERVATION_CORE_CHARACTERSITICS,
      'dstu2' => null,
    ],
    self::SOCIAL_HISTORY => [
      'r4' => EndpointIdentifier::OBSERVATION_SOCIAL_HISTORY,
      'dstu2' => EndpointIdentifier::OBSERVATION_SOCIAL_HISTORY,
    ],
    self::ENCOUNTER => [
      'r4' => EndpointIdentifier::ENCOUNTER,
      'dstu2' => null,
    ],
    self::IMMUNIZATION => [
      'r4' => EndpointIdentifier::IMMUNIZATION,
      'dstu2' => null,
    ],
    self::MEDICATIONS => [
      'r4' => EndpointIdentifier::MEDICATION_REQUEST,
      'dstu2' => EndpointIdentifier::MEDICATION_ORDER,
    ],
    self::LABORATORY => [
      'r4' => EndpointIdentifier::OBSERVATION_LABS,
      'dstu2' => EndpointIdentifier::OBSERVATION_LABS,
    ],
    self::VITAL_SIGNS => [
      'r4' => EndpointIdentifier::OBSERVATION_VITALS,
      'dstu2' => EndpointIdentifier::OBSERVATION_VITALS,
    ],
    self::RESEARCH_STUDY => [
      'r4' => EndpointIdentifier::RESEARCHSTUDY,
      'dstu2' => null,
    ],
    self::DIAGNOSIS => [
      'r4' => EndpointIdentifier::CONDITION_ENCOUNTER_DIAGNOSIS,
      'dstu2' => null,
    ],
    self::PRACTITIONER => [
      'r4' => EndpointIdentifier::PRACTITIONER,
      'dstu2' => null,
    ],
    self::DOCUMENT_REFERENCE_CLINICAL_NOTES => [
      'r4' => EndpointIdentifier::DOCUMENT_REFERENCE_CLINICAL_NOTES,
      'dstu2' => null,
    ],
    self::PROCEDURE => [
      'r4' => EndpointIdentifier::PROCEDURE,
      'dstu2' => null,
    ],
    self::SMART_DATA => [
      'r4' => EndpointIdentifier::OBSERVATION_SMART_DATA,
      'dstu2' => null,
    ],
    self::CONDITION_DENTAL_FINDING => [
      'r4' => EndpointIdentifier::CONDITION,
      'dstu2' => null,
    ],
    self::CONDITION_GENOMICS => [
      'r4' => EndpointIdentifier::CONDITION,
      'dstu2' => null,
    ],
    self::CONDITION_INFECTION => [
      'r4' => EndpointIdentifier::CONDITION,
      'dstu2' => null,
    ],
    self::CONDITION_MEDICAL_HISTORY => [
      'r4' => EndpointIdentifier::CONDITION,
      'dstu2' => null,
    ],
    self::CONDITION_REASON_FOR_VISIT => [
      'r4' => EndpointIdentifier::CONDITION,
      'dstu2' => null,
    ],
    self::COVERAGE => [
      'r4' => EndpointIdentifier::COVERAGE,
      'dstu2' => null,
    ],
    self::DEVICE_IMPLANTS => [
      'r4' => EndpointIdentifier::DEVICE,
      'dstu2' => null,
    ],
    self::APPOINTMENT_APPOINTMENTS => [
      'r4' => EndpointIdentifier::APPOINTMENT,
      'dstu2' => null,
    ],
    self::APPOINTMENT_SCHEDULED_SURGERIES => [
      'r4' => EndpointIdentifier::APPOINTMENT,
      'dstu2' => null,
    ],
  ];

  /**
   * Undocumented function
   *
   * @param string $category
   * @return array|null
   */
  public static function getDataArray($category)
    {
        if (isset(self::$categoryData[$category])) {
            return self::$categoryData[$category];
        }
    }


}