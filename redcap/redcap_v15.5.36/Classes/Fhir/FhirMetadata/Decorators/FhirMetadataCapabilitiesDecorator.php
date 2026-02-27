<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators;

use Vanderbilt\REDCap\Classes\Fhir\FhirCategory;
use Vanderbilt\REDCap\Classes\Fhir\FhirVersionManager;

class FhirMetadataCapabilitiesDecorator extends FhirMetadataAbstractDecorator
{
  /**
   *
   * @var FhirVersionManager
   */
  private $fhirVersionManager;

  /**
   * @param FhirMetadata $fhirMetadata
   * @param FhirVersionManager $fhirVersionManager
   */
  public function __construct($fhirMetadata, $fhirVersionManager)
  {
    parent::__construct($fhirMetadata);
    $this->fhirVersionManager = $fhirVersionManager;
  }

  private function getFhirTypes() {
    $conformanceStatement = $this->fhirVersionManager->getConformanceStatement();
    $resources = $conformanceStatement->getResources();
    $types = array_column($resources, 'type');
    $types = array_flip($types); // Convert allowedCategories to a lookup for faster checking
    return $types;
  }

  private function getVendorname() {
    $conformanceStatement = $this->fhirVersionManager->getConformanceStatement();
    $software = $conformanceStatement->getSoftwareName();
    return $software;
  }

  /**
   * list of categories that are only available in Epic systems
   *
   * @var array
   */
  private $epicOnlyCategories = [
    FhirCategory::SMART_DATA,
    FhirCategory::CORE_CHARACTERISTICS,
    FhirCategory::CONDITION_DENTAL_FINDING,
    FhirCategory::CONDITION_GENOMICS,
    FhirCategory::CONDITION_INFECTION,
    FhirCategory::CONDITION_MEDICAL_HISTORY,
    FhirCategory::CONDITION_REASON_FOR_VISIT,
  ];

  /**
   * Map proprietary observation categories to supported alternatives.
   *
   * @var array<string,string>
   */
  private $observationCategoryTransforms = [
    FhirCategory::SMART_DATA => FhirCategory::LABORATORY,
    FhirCategory::CORE_CHARACTERISTICS => FhirCategory::LABORATORY,
  ];

  /**
   *
   * @param array $metadata_entry REDCap mapping metadata entry
   * @return array
   */
  private function checkVendor(&$metadata_array, &$metadata_entry, $vendorName) {
    $isEpic = preg_match("/epic/i", $vendorName) === 1;
    if($isEpic) return;
    $category = $metadata_entry['category'] ?? null;
    if(is_null($category)) return;
    $isEpicCategory = in_array($category, $this->epicOnlyCategories);
    if($isEpicCategory) {
      $metadata_entry['disabled'] = true;
      $metadata_entry['disabled_reason'] = 'This category is only available in Epic systems';
    }
  }

  /**
   * filter the FHIR types that are not found in the capability statement
   *
   * @param array $metadata_entry REDCap mapping metadata entry
   * @return array
   */
  private function checkAllowedCategory(&$metadata_array, &$metadata_entry, $allowedCategories) {
    $category = $metadata_entry['category'] ?? null;
    if($category==='') return; // allow items with category set as empty string (e.g.: id)
    if(is_null($category)) {
      $metadata_entry['disabled'] = true;
      $metadata_entry['disabled_reason'] = 'Categories must have a value.';
      return;
    };
    $fhirType = $this->getFhirType($category);
    if(!$fhirType) {
      $metadata_entry['disabled'] = true;
      $metadata_entry['disabled_reason'] = "The category '$fhirType' is not available in REDCap.";
      return;
    };
    $allowed = array_key_exists($fhirType, $allowedCategories);
    if(!$allowed) {
      $metadata_entry['disabled'] = true;
      $metadata_entry['disabled_reason'] = "Please ensure that your FHIR app has the  '$fhirType' category enabled.";
      return;
    };
  }

  /**
   * Normalize observation categories that require fallback mappings.
   *
   * @param array $metadata_entry
   */
  private function normalizeObservationCategory(array &$metadata_entry)
  {
    $category = $metadata_entry['category'] ?? null;
    if(!$category) return;
    if(!array_key_exists($category, $this->observationCategoryTransforms)) return;
    $metadata_entry['category'] = $this->observationCategoryTransforms[$category];
  }

  // Cache for faster access to FHIR types based on REDCap categories
  private $redcapToFhirTypes = [];

  /**
   * PLEASE NOTE:
   * Get the FHIR type for the specified REDCap category.
   * make sure the resources available in redcap were mapped in
   * FhirCategory::$categoryData
   *
   * @param string $redcapCategory The REDCap category.
   * @return mixed|null The FHIR type data associated with the category, or null if not found.
   */
  function getFhirType($redcapCategory) {
    if(!isset($this->redcapToFhirTypes[$redcapCategory])) {
      $categoryData = FhirCategory::getDataArray($redcapCategory);
      if(!$categoryData) return;
      $fhirVersion = $this->fhirVersionManager->getFhirCode();
      $data = is_string($fhirVersion) ? $categoryData[ strtolower($fhirVersion)] : null;
      $this->redcapToFhirTypes[$redcapCategory] = $data;
    }
    return $this->redcapToFhirTypes[$redcapCategory];
  }
  
  /**
   *
   * @param array $list
   * @return array
   */
  public function getList()
  {
    $metadata_array = $this->fhirMetadata->getList();
    $allowedCategories = $this->getFhirTypes();
    $vendorName = $this->getVendorname();
    foreach ($metadata_array as $index => &$metadata_entry) {
      $this->normalizeObservationCategory($metadata_entry);
      $this->checkAllowedCategory($metadata_array, $metadata_entry, $allowedCategories);
      $this->checkVendor($metadata_array, $metadata_entry, $vendorName);
    }

    return $metadata_array;
  }
}
