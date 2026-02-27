<?php namespace Vanderbilt\REDCap\Classes\Fhir;

use DateTime;
use DateTimeZone;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\R4\Encounter;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\DSTU2\Condition;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\R4\AdverseEvent;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\AbstractEndpoint;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\DSTU2\AllergyIntolerance;
use Vanderbilt\REDCap\Classes\Fhir\FhirMapping\FhirMappingGroup;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\R4\ConditionProblems;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\R4\MedicationRequest;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\DSTU2\MedicationOrder;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\EndpointVisitorInterface;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\R4\Patient as Patient_R4;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\DSTU2\Patient as Patient_DSTU2;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\R4\ObservationCoreCharacteristics;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\R4\ObservationLabs as ObservationLabs_R4;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\R4\ObservationVitals as ObservationVitals_R4;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\DSTU2\ObservationLabs as ObservationLabs_DSTU2;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\R4\AllergyIntolerance as AllergyIntolerance_R4;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\DSTU2\ObservationVitals as ObservationVitals_DSTU2;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\R4\ObservationSocialHistory as ObservationSocialHistory_R4;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\DSTU2\ObservationSocialHistory as ObservationSocialHistory_DSTU2;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\EndpointsHelper;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\R4\AppointmentAppointments;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\R4\AppointmentScheduledSurgeries;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\R4\DocumentReferenceClinicalNotes;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\R4\Procedure;
use Vanderbilt\REDCap\Classes\Fhir\Utility\FhirVendorInspector;
use Vanderbilt\REDCap\Classes\Fhir\Utility\CernerAppointmentDateNormalizer;

/**
 * FHIR endpoint visitor that generates parameters
 * for FHIR endpoints using REDCap mapping, projects settings
 * and system settings.
 */
class RedcapEndpointVisitor implements EndpointVisitorInterface
{

  /**
   *
   * @var FhirClient
   */
  private $fhirClient;

  /**
   * @var string
   */
  private $patient_id;

  /**
   *
   * @var FhirMappingGroup
   */
  private $mappingGroup;

  /**
   * @var array [DateTime, DateTime]
   */
  private $dateRange;

  /**
   * @var DateTime|null
   */
  private $dateMin;

  /**
   * @var DateTime|null
   */
  private $dateMax;

  /**
   * @var FhirVendorInspector|null
   */
  private $vendorInspector;

  /**
   *
   * @param FhirClient $fhirClient
   * @param string $patient_id
   * @param FhirMappingGroup $mappingGroup
   */
  function __construct($fhirClient, $patient_id, $mappingGroup)
  {
    
    $this->fhirClient = $fhirClient;
    $this->patient_id = $patient_id;
    $this->mappingGroup = $mappingGroup;
    $dateMin = $mappingGroup->getDateMin();
    $dateMax = $mappingGroup->getDateMax();
    $this->dateMin = $dateMin instanceof DateTime ? clone $dateMin : null;
    $this->dateMax = $dateMax instanceof DateTime ? clone $dateMax : null;
    $this->dateRange = $this->makeDateRange($this->dateMin, $this->dateMax);
  }

  /**
   * adjust the options for the endpoint
   * @param AbstractEndpoint $endpoint
   * @return array
   */
  function visit($endpoint)
  {
    $options = [];
    $class = get_class($endpoint);
    switch ($class) {
      case Patient_DSTU2::class:
      case Patient_R4::class:
        $options = $this->visitPatient($endpoint, $options);
        break;
      case AdverseEvent::class:
        $options = $this->visitAdverseEvents($endpoint, $options);
        break;
      case AllergyIntolerance::class:
        $options = $this->visitAllergy($endpoint, $options);
        break;
      case AllergyIntolerance_R4::class:
          $options = $this->visitAllergyR4($endpoint, $options);
          break;
      case AppointmentAppointments::class:
      case AppointmentScheduledSurgeries::class:
          $options = $this->visitAppointment($endpoint, $options);
          break;
      case Condition::class:
        $options = $this->visitCondition($endpoint, $options);
        break;
      case ConditionProblems::class:
        $options = $this->visitConditionProblems($endpoint, $options);
        break;
      case MedicationOrder::class:
      case MedicationRequest::class:
        $options = $this->visitMedication($endpoint, $options);
        break;
      case ObservationSocialHistory_DSTU2::class:
      case ObservationSocialHistory_R4::class:
      case ObservationVitals_DSTU2::class:
      case ObservationVitals_R4::class:
      case ObservationLabs_DSTU2::class:
      case ObservationLabs_R4::class:
      case ObservationCoreCharacteristics::class:
        $options = $this->visitObservation($endpoint, $options);
        break;
      case MedicationRequest::class:
        $options = $this->visitMedication($endpoint, $options);
        break;
      case Encounter::class:
        $options = $this->visitEncounter($endpoint, $options);
        break;
      case DocumentReferenceClinicalNotes::class:
        $options = $this->visitDocumentReferenceClinicalNotes($endpoint, $options);
        break;
      case Procedure::class:
        $options = $this->visitProcedure($endpoint, $options);
        break;
      default:
        $options['patient'] = $this->patient_id;
        break;
    }
    return $options;
  }

  protected function getVendorInspector()
  {
    if (!$this->vendorInspector instanceof FhirVendorInspector) {
      $this->vendorInspector = new FhirVendorInspector($this->fhirClient);
    }
    return $this->vendorInspector;
  }

  /**
   * Check if the current FHIR system is provided by Epic.
   */
  protected function isEpicSystem()
  {
    $inspector = $this->getVendorInspector();
    return $inspector ? $inspector->isEpic() : false;
  }

  /**
   * Check if the current FHIR system is provided by Cerner / Oracle Health.
   */
  protected function isCernerSystem()
  {
    $inspector = $this->getVendorInspector();
    return $inspector ? $inspector->isCerner() : false;
  }

  /**
   *
   * @param Patient_DSTU2|Patient_R4 $endpoint
   * @return array
   */
  public function visitPatient($endpoint, $options)
  {
    $options['_id'] = $this->patient_id;
    return $options;
  }

  /**
   * PLEASE NOTE: date will not be applied in Epic systems
   * @param AllergyIntolerance $endpoint
   * @return array
   */
  public function visitAllergy($endpoint, $options)
  {
    $options['patient'] = $this->patient_id;
    $options['date'] = $this->dateRange;
    return $options;
  }

  /**
   * PLEASE NOTE: date will not be applied in Epic systems
   * @param AllergyIntolerance_R4 $endpoint
   * @return array
   */
  public function visitAllergyR4($endpoint, $options)
  {
    $options['patient'] = $this->patient_id;
    $options['clinical-status'] = $endpoint::CLINICAL_STATUS_ACTIVE;
    $options['date'] = $this->dateRange;
    return $options;
  }

  public function visitAppointment($endpoint, $options)
  {
    $options['patient'] = $this->patient_id;
    if ($this->isCernerSystem()) {
      $options['date'] = CernerAppointmentDateNormalizer::fromDateRange($this->dateMin, $this->dateMax);
    } else {
      $options['date'] = $this->dateRange;
    }
    return $options;
  }

  /**
   *
   * @param AdverseEvent $endpoint
   * @return array
   */
  public function visitAdverseEvents($endpoint, $options)
  {
    $endpointsHelper = new EndpointsHelper();

    $irbNumber = $endpointsHelper->getProjectIrbNumber();
    if(empty($irbNumber)) return;

    $studyFhirId = $endpointsHelper->getFhirStudyID($this->fhirClient, $irbNumber);

    $options['study'] = $studyFhirId;
    $options['subject'] = $this->patient_id;
    return $options;
  }

  /**
   *
   * @param Condition $endpoint
   * @return array
   */
  public function visitCondition($endpoint, $options)
  {
    $fields = $this->mappingGroup->getFields();
    $options['patient'] = $this->patient_id;
    $options['onset'] = $this->dateRange;
    $options['clinicalStatus'] = $endpoint->getStatusParam($fields);
    return $options;
  }

  /**
   *
   * @param ConditionProblems $endpoint
   * @return array
   */
  public function visitConditionProblems($endpoint, $options)
  {
    $fields = $this->mappingGroup->getFields();
    $options['patient'] = $this->patient_id;
    $options['clinical-status'] = $endpoint->getStatusParam($fields);
    $options['onset-date'] = $this->dateRange;
    return $options;
  }

  /**
   *
   * @param Procedure $endpoint
   * @return array
   */
  public function visitProcedure($endpoint, $options)
  {
    $options['patient'] = $this->patient_id;
    $options['date'] = $this->dateRange;
    return $options;
  }

  /**
   *
   * @param AbstractObservation_DSTU2|AbstractObservation_R4 $endpoint
   * @return array
   */
  public function visitObservation($endpoint, $options)
  {
    $options['patient'] = $this->patient_id;
    $options['date'] = $this->dateRange;
    return $options;
  }

  /**
   *
   * @param Encounter $endpoint
   * @return array
   */
  public function visitEncounter($endpoint, $options)
  {
    $options['patient'] = $this->patient_id;
    $options['date'] = $this->dateRange;
    // $options['_include'] = 'encounter:Practitioner'; // this also load data for the referenced practitioners
    return $options;
  }

  /**
   * Undocumented function
   *
   * @param MedicationOrder|MedicationRequest $endpoint
   * @return array
   */
  public function visitMedication($endpoint, $options)
  {
    $fields = $this->mappingGroup->getFields();
    $options['patient'] = $this->patient_id;
    $options['status'] = $endpoint->getStatusParam($fields);
    return $options;
  }

  /**
   * Undocumented function
   *
   * @param DocumentReferenceClinicalNotes $endpoint
   * @return array
   */
  public function visitDocumentReferenceClinicalNotes($endpoint, $options)
  {
    $options['patient'] = $this->patient_id;
    $options['date'] = $this->dateRange;
    return $options;
  }

  /**
   * Build a default UTC window when Cerner requires dates but none are mapped.
   */
  /**
   * create a date range to use when filtering by date
   *
   * @param DateTime $date_min
    * @param DateTime $date_max
   * @return array
   * 
   * @see https://www.hl7.org/fhir/search.html#date
   */
  /**
   * Format the configured date range for FHIR queries.
   *
   * @param DateTime|null $date_min
   * @param DateTime|null $date_max
   * @param array{min_prefix?:string|null,max_prefix?:string|null,force_utc?:bool} $options
   * @return array<int,string>
   */
  protected function makeDateRange($date_min, $date_max, array $options = [])
  {
    $defaults = [
      'min_prefix' => 'ge',
      'max_prefix' => 'le',
      'force_utc' => false,
    ];
    $options = array_merge($defaults, $options);

    $timezoneID = getTimeZone();

    $formatDate = function(DateTime $datetime) use ($options, $timezoneID) {
      $fhirCode = $this->fhirClient->getFhirVersionCode();
      if($fhirCode===FhirVersionManager::FHIR_DSTU2) {
        return $datetime->format('Y-m-d');
      }

      $workingDate = clone $datetime;

      if($options['force_utc']) {
        $workingDate->setTimezone(new DateTimeZone('UTC'));
        return $workingDate->format('Y-m-d\TH:i:s').'Z';
      }

      if($timezoneID) {
        $workingDate->setTimezone(new DateTimeZone($timezoneID));
      }

      return $workingDate->format('Y-m-d\TH:i:sP');
    };

    $params = [];
    if($date_min instanceof DateTime && $options['min_prefix']) {
      $params[] = $options['min_prefix'].$formatDate($date_min);
    }
    if($date_max instanceof DateTime && $options['max_prefix']) {
      $params[] = $options['max_prefix'].$formatDate($date_max);
    }
    return $params;
  }

}
