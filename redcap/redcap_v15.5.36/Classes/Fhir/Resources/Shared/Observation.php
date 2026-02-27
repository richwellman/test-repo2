<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources\Shared;

use Vanderbilt\REDCap\Classes\Fhir\Resources\AbstractResource;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Traits\CanNormalizeTimestamp;

class Observation extends AbstractResource
{

  use CanNormalizeTimestamp;

  const TIMESTAMP_FORMAT = 'Y-m-d H:i';

  /**
   * get the local or GMT timestamp
   * 
   * @param boolean $localTimestamp
   * @return string
   */
  public function getTimestamp($localTimestamp=false)
  {
    $callable = $this->getTimestampCallable($this->getDate(), self::TIMESTAMP_FORMAT);
    return $callable($localTimestamp);
  }

  public function getId()
  {
    return $this->scraper()->id->join('');
  }

  /**
   * return the category of the observation (Laboratory, Lab, Vital Signs)
   *
   * @return void
   */
  public function getCategory()
  {
    return $this->scraper()->category->text->getData();
  }

  /**
   * select a loinc code
   * valid selections are:
   * - '#/code/coding/\d+': \d+, any number, means that we are selecting the list of coding
   * - '#/code/coding/.*': other version the dot (.) means any character
   * @return array
   */
  public function getLoincCodes()
  {
    return $this->scraper()
      ->code->coding
      ->where('system', 'like', CodingSystem::LOINC)
      ->code->getData();
  }

  public function getLoincDisplays()
  {
    return $this->scraper()
      ->code->coding
      ->where('system', 'like', CodingSystem::LOINC)
      ->display->getData();
  }

  public function getDate()
  {
    $scraper =  $this->scraper();
    $effectiveDateTime = $scraper->effectiveDateTime->join('');
    $issued = $scraper->issued->join(''); // fallback to issued time
    return $effectiveDateTime ?? $issued ?? '';
  }

  public function getValueQuantity()
  {
    return $this->scraper()->valueQuantity[0]->getData();
  }

  public function getValueString()
  { 
    return $this->scraper()->valueString->join('');
  }
  public function getValueCodeableConcept()
  {
    return $this->scraper()->valueCodeableConcept[0]->getData();
  }
  public function getValueCodeableConceptCode()
  {
    return $this->scraper()->valueCodeableConcept->coding->code->getData();
  }
  public function getValueCodeableConceptText()
  {
    return $this->scraper()->valueCodeableConcept->text->join('');
  }
  public function getValueCodeableConceptValue()
  {
    $valueCodeableConceptParts = [
      'codeableConceptCode' => $codeableConceptCode = $this->getValueCodeableConceptCode(),
      'codeableConceptText' => $codeableConceptText = $this->getValueCodeableConceptText(),
    ];
    $valueCodeableConcept = ($codeableConceptCode || $codeableConceptText) ? implode(' - ', $valueCodeableConceptParts) : '';
    return $valueCodeableConcept;
  }

  public function getValueBoolean()
  {
    return $this->scraper()->valueBoolean->join('');
  }
  public function getValueInteger()
  {
    return $this->scraper()->valueInteger->join('');
  }
  public function getValueRange()
  {
    return $this->scraper()->valueRange->getData();
  }
  public function getValueRatio()
  {
    return $this->scraper()->valueRatio->getData();
  }
  public function getValueSampledData()
  {
    return $this->scraper()->valueSampledData->getData();
  }
  public function getValueTime()
  {
    return $this->scraper()->valueTime->join('');
  }
  public function getValueDateTime()
  {
    return $this->scraper()->valueDateTime->join('');
  }
  public function getValuePeriod()
  {
    return $this->scraper()->valuePeriod->getData();
  }

  /**
   * Normalize scalar values so 0/false are preserved while empty strings are ignored.
   */
  private function normalizeScalarValue($value): ?string
  {
    $value = $this->unwrapListValue($value);
    if($value === null) return null;
    if(is_bool($value)) return $value ? 'true' : 'false';
    if(is_string($value)) return $value === '' ? null : $value;
    if(is_int($value) || is_float($value)) return (string)$value;
    if(is_scalar($value)) return (string)$value;
    return null;
  }

  /**
   * Many scraper values come back as single-item lists; unwrap to a scalar.
   */
  private function unwrapListValue($value)
  {
    if(!is_array($value)) return $value;
    if(array_key_exists(0, $value) && count($value) === 1) return $value[0];
    if(array_key_exists(0, $value) && !array_key_exists(1, $value)) return $value[0];
    return $value;
  }

  /**
   * Normalize joined node text to null when empty.
   */
  private function normalizeNodeText($node): ?string
  {
    $text = $node->join('');
    return $text === '' ? null : $text;
  }

  /**
   * Format CodeableConcept values using the first coding code when present.
   */
  private function formatValueCodeableConcept(): ?string
  {
    $data = $this->getValueCodeableConcept();
    if(is_array($data)) $data = $this->unwrapListValue($data);
    $codings = $data['condings'] ?? [];
    $parts = [];
    foreach ($codings as $coding) {
      $code = $this->normalizeScalarValue($coding['code'] ?? null);
      if($code !== null) {
        $parts[] = $code;
        break;
      }
    }
    $text = $this->normalizeScalarValue($data['text'] ?? null);
    if($text !== null) $parts[] = $text;
    return $parts ? implode(' - ', $parts) : null;
  }

  private function formatValueQuantity(): ?string
  {
    $data = $this->getValueQuantity();
    $data = $this->unwrapListValue($data);
    if(!is_array($data)) return null;
    $parts = [];
    foreach (['comparator', 'value'] as $key) {
      if(!array_key_exists($key, $data)) continue;
      $part = $this->normalizeScalarValue($data[$key]);
      if($part !== null) $parts[] = $part;
    }
    return $parts ? implode(' ', $parts) : null;
  }

  private function formatValuePeriod(): ?string
  {
    $data = $this->getValuePeriod();
    $data = $this->unwrapListValue($data);
    if(!is_array($data)) return null;
    $parts = [];
    $start = $this->normalizeScalarValue($data['start'] ?? null);
    if($start !== null) $parts[] = $start;
    $end = $this->normalizeScalarValue($data['end'] ?? null);
    if($end !== null) $parts[] = $end;
    return $parts ? implode(' -> ', $parts) : null;
  }

  private function formatValueRatio(): ?string
  {
    $data = $this->getValueRatio();
    $data = $this->unwrapListValue($data);
    if(!is_array($data)) return null;
    $parts = [];
    $numerator = $this->normalizeScalarValue($data['numerator']['value'] ?? null);
    if($numerator !== null) $parts[] = $numerator;
    $denominator = $this->normalizeScalarValue($data['denominator']['value'] ?? null);
    if($denominator !== null) $parts[] = $denominator;
    return $parts ? implode(' / ', $parts) : null;
  }

  private function formatValueRange(): ?string
  {
    $data = $this->getValueRange();
    $data = $this->unwrapListValue($data);
    if(!is_array($data)) return null;
    $parts = [];
    $lowValue = $this->normalizeScalarValue($data['low']['value'] ?? null);
    if($lowValue !== null) {
      $lowUnit = $data['low']['unit'] ?? '';
      $parts[] = $lowValue . ($lowUnit ? ' ' . $lowUnit : '');
    }
    $highValue = $this->normalizeScalarValue($data['high']['value'] ?? null);
    if($highValue !== null) {
      $highUnit = $data['high']['unit'] ?? '';
      $parts[] = $highValue . ($highUnit ? ' ' . $highUnit : '');
    }
    return $parts ? implode(' - ', $parts) : null;
  }

  private function formatValueSampledData(): ?string
  {
    $data = $this->getValueSampledData();
    $data = $this->unwrapListValue($data);
    if(!is_array($data)) return null;
    $parts = [];
    $lowValue = $this->normalizeScalarValue($data['low']['value'] ?? null);
    if($lowValue !== null) {
      $lowUnit = $data['low']['unit'] ?? '';
      $parts[] = $lowValue . ($lowUnit ? ' ' . $lowUnit : '');
    }
    $highValue = $this->normalizeScalarValue($data['high']['value'] ?? null);
    if($highValue !== null) {
      $highUnit = $data['high']['unit'] ?? '';
      $parts[] = $highValue . ($highUnit ? ' ' . $highUnit : '');
    }
    return $parts ? implode(' - ', $parts) : null;
  }

  public function getValue()
  {
    // Preserve value precedence while keeping booleans/numerics explicit.
    $value = $this->normalizeNodeText($this->scraper()->valueString);
    if($value !== null) return $value;

    $value = $this->normalizeScalarValue($this->scraper()->valueBoolean->getData());
    if($value !== null) return $value;

    $value = $this->normalizeNodeText($this->scraper()->valueInteger);
    if($value !== null) return $value;

    $value = $this->normalizeNodeText($this->scraper()->valueTime);
    if($value !== null) return $value;

    $value = $this->normalizeNodeText($this->scraper()->valueDateTime);
    if($value !== null) return $value;

    $value = $this->formatValueCodeableConcept();
    if($value !== null) return $value;

    $value = $this->formatValueQuantity();
    if($value !== null) return $value;

    $value = $this->formatValuePeriod();
    if($value !== null) return $value;

    $value = $this->formatValueRatio();
    if($value !== null) return $value;

    $value = $this->formatValueRange();
    if($value !== null) return $value;

    $value = $this->formatValueSampledData();
    if($value !== null) return $value;

    return '';
  }

  public function getValueUnit()
  {
    return $this->scraper()->valueQuantity->unit->join('');
  }

  /**
   * components are subset that need to be split.
   * each component only contains
   * - 1 `code` with 1 or more coding systems
   * - 0 or 1 `value`
   * @see https://www.hl7.org/fhir/observation.html
   *
   * @return array
   */
  public function getComponent()
  {
    return $this->scraper()->component->getData();
  }

  
  public function getCodingSystems()
  {
    return $this->scraper()->code->coding->getData();
  }

  /**
   * create a CodeableConcept from the code
   * portion of the payload
   *
   * @return CodeableConcept
   */
  public function getCode()
  {
    $payload = $this->scraper()->code->getData();
    return new CodeableConcept($payload);
  }

  /**
   * create separate observations
   * for each component
   *
   * @return Observation[]
   */
  public function splitComponents()
  {
    $components = $this->getComponent();
    if(empty($components)) return [$this];
    $parentPayload = $this->getPayload();
    unset($parentPayload['component']);
    $list = array_map(function($payload) use($parentPayload) {
      return $this->replacePayload($parentPayload, $payload);
    }, $components);
    return $list;
  }

  /**
   * create separate observations
   * for each coding
   *
   * @return Observation[]
   */
  public function splitCodings()
  {
    $codeableConcept = $this->getCode();
    $codings = $codeableConcept->getCoding();
    if(empty($codings)) return [$this];
    if(count($codings)<=1) return [$this];
    $text = $codeableConcept->getText();
    $parentPayload = $this->getPayload();
    $list = array_map(function($coding) use($parentPayload, $text) {
      // make a new code payload that will replace the existing one
      $payload = [
        'code' => [
          'coding' => [$coding],
          'text' => $text,
        ]
      ];
      return $this->replacePayload($parentPayload, $payload);
    }, $codings);
    return $list;
  }

  /**
   * observation resources should always be returned
   * as array because could contain components.
   * 
   * splits first based on components, then based on codings
   * @see https://www.hl7.org/fhir/observation-definitions.html#Observation.component
   *
   * @return Observation[]
   */
  public function split()
  {
    $reduceObservations = function($carry, $observation) {
      $list = $observation->splitCodings();
      foreach ($list as $splitted) {
          $carry[] = $splitted;
      }
      return $carry;
    };
    $observations = $this->splitComponents();
    $list = array_reduce($observations, $reduceObservations, []);
    return $list;
  }

  public function getNormalizedTimestamp()
  {
    $timestamp = $this->getDate();
    return $this->getGmtTimestamp($timestamp, self::TIMESTAMP_FORMAT);
  }

  public function getNormalizedLocalTimestamp() {
    $timestamp = $this->getDate();
    return $this->getLocalTimestamp($timestamp, self::TIMESTAMP_FORMAT);
  }

  /**
   * get plain version of the data
   * 
   * @return array
   */
  public function getData()
  {
    $data = [
      'fhir-id' => $this->getId(),
      'code' => $this->getCode()->getData(),
      'category' => $this->getCategory(),
      'timestamp' => $this->getDate(),
      'normalized_timestamp' => $this->getNormalizedTimestamp(),
      'local_timestamp' => $this->getNormalizedLocalTimestamp(),
      'value' => $this->getValue(),
      'valueUnit' => $this->getValueUnit(),
      'valueQuantity' => $this->getValueQuantity(),
      'valueString' => $this->getValueString(),
      'valueCodeableConcept' => $this->getValueCodeableConcept(),
      'valueBoolean' => $this->getValueBoolean(),
      'valueInteger' => $this->getValueInteger(),
      'valueRange' => $this->getValueRange(),
      'valueRatio' => $this->getValueRatio(),
      'valueSampledData' => $this->getValueSampledData(),
      'valueTime' => $this->getValueTime(),
      'valueDateTime' => $this->getValueDateTime(),
      'valuePeriod' => $this->getValuePeriod(),
      'component' => $this->getComponent(),
    ];
    return $data;
  }
  
}
