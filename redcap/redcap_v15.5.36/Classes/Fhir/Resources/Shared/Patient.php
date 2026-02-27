<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources\Shared;

use Vanderbilt\REDCap\Classes\Fhir\Resources\AbstractResource;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Traits\CanNormalizeTimestamp;

class Patient extends AbstractResource
{

  use CanNormalizeTimestamp;

  public function getFhirID()
  {
    return strval($this->scraper()->id); // strval equals calling ->join(' ')
  }

  public function getIdentifier($system='')
  {
    return $this->scraper()
      ->identifier
      ->where('system', '=', $system)
      ->value
      ->join(' ');
  }

  public function getIdentifiers()
  {
    return $this->scraper()
      ->identifier
      ->getData();
  }
  
  public function getNameGiven($index=0)
  {
    return $this->scraper()
      ->name
      ->where('use', '=', 'official')
      ->given
      ->join(' ');
  }
  
  public function getNameFamily($index=0)
  {
    return $this->scraper()
      ->name
      ->where('use', '=', 'official')
      ->family
      ->join(' ');
  }
  
  public function getBirthDate()
  {
    return $this->scraper()
      ->birthDate
      ->join(' ');
  }
  
  public function getGenderCode()
  {
    $valueCodeableConcept = $this->scraper()
      ->extension
      ->where('url', 'like', 'birth-?sex$')
      ->valueCodeableConcept
      ->coding->code->join();
    
    if(!empty($valueCodeableConcept)) return $valueCodeableConcept;

    $valueCode = $this->scraper()
      ->extension
      ->where('url', 'like', 'birth-?sex$')
      ->valueCode->join(' ');

    return $valueCode;
  }

  public function getGenderText()
  {
    $valueCodeableConcept = $this->scraper()
      ->extension
      ->where('url', 'like', 'birth-?sex$')
      ->valueCodeableConcept
      ->coding->display->join();
    if(!(empty($valueCodeableConcept))) return $valueCodeableConcept;

    $gender = $this->scraper()
      ->gender
      ->join(' ');
      return $gender;
  }

  public function getGender()
  {
    $getCode = function($value) {
      if(empty($value)) return '';
      $gender_mapping = [
        'female' => 'F',
        'male' => 'M',
        'f' => 'F',
        'm' => 'M',
        'unknown' => 'UNK',
        'unk' => 'UNK',
      ];
      $code = $gender_mapping[strtolower($value)] ?? 'UNK';
      return $code;
    };
    $genderCode = $this->getGenderCode();
    if($genderCode) return $genderCode;
    $genderText = $this->getGenderText();
    $code = $getCode($genderText);
    return $code;
  }

  public function getLegalSex() {
    return $this->scraper()
      ->extension
      ->where('url', 'like', 'legal-sex$')
      ->valueCodeableConcept
      ->coding->display->join();
  }

  public function getSexForClinicalUse() {
    return $this->scraper()
      ->extension
      ->where('url', 'like', 'sex-for-clinical-use$')
      ->valueCodeableConcept
      ->coding->display->join();
  }
  
  public function getRaceCode($index=0)
  {
    $data = $this->scraper()
      ->extension
      ->where('url', 'like', 'race$')
      ->any('=', 'code'); // select any 'code' child
    return $data[$index]->join(''); // only get one race
  }

  public function getEthnicityCode()
  {
    return $this->scraper()
      ->extension
      ->where('url', 'like', 'ethnicity$')
      ->any('=', 'code')->join('');
  }
  
  public function getAddressLine()
  {
    $address = $this->scraper()
      ->address
      ->where('use', '=', 'home')
      ->line->join(' ');
    if (!$address) {
      $address= $this->scraper()
        ->address
        ->line->join(' ');
    }
    return $address;
  }

  public function getAddressDistrict()
  {
    $address = $this->scraper()
      ->address
      ->where('use', '=', 'home')
      ->district->join('');
    if (!$address) {
      $address= $this->scraper()
        ->address
        ->district->join('');
    }
    return $address;
  }
  
  public function getAddressCity()
  {
    $address = $this->scraper()
      ->address
      ->where('use', '=', 'home')
      ->city->join('');
    if (!$address) {
      $address= $this->scraper()
        ->address
        ->city->join('');
    }
    return $address;
  }
  
  public function getAddressState()
  {
    $address = $this->scraper()
      ->address
      ->where('use', '=', 'home')
      ->state->join('');
    if (!$address) {
      $address= $this->scraper()
        ->address
        ->state->join('');
    }
    return $address;
  }
  
  public function getAddressPostalCode()
  {
    $address = $this->scraper()
      ->address
      ->where('use', '=', 'home')
      ->postalCode->join('');
    if (!$address) {
      $address= $this->scraper()
        ->address
        ->postalCode->join('');
    }
    return $address;
  }
  
  public function getAddressCountry()
  {
    $address = $this->scraper()
      ->address
      ->where('use', '=', 'home')
      ->country->join('');
    if (!$address) {
      $address= $this->scraper()
        ->address
        ->country->join('');
    }
    return $address;
  }
  
  public function getPhoneHome($index=0)
  {
    return $this->scraper()
      ->telecom
      ->where('system', '=', 'phone')
      ->where('use', '=', 'home')
      ->value[$index]->join('');
  }
  
  public function getPhoneMobile($index=0)
  {
    return $this->scraper()
      ->telecom
      ->where('system', '=', 'phone')
      ->where('use', '=', 'mobile')
      ->value[$index]->join('');
  }
  
  /**
   * return 0 or 1, as expected by a radio in REDCap
   *
   * @return 0|1
   */
  public function isDeceased() 
  {
    $deceasedDateTime = $this->getDeceasedDateTime();
    if(!empty($deceasedDateTime)) return 1;
    return $this->getDeceasedBoolean();
  }

  /**
   * return 0 or 1, as expected by a radio in REDCap
   *
   * @return 0|1
   */
  public function getDeceasedBoolean()
  {
    $deceased = $this->scraper()->deceasedBoolean->join('');
    $booleanValue = filter_var($deceased, FILTER_VALIDATE_BOOLEAN);
    return $booleanValue ? 1 : 0;
  }
  
  public function getDeceasedDateTimeGMT()
  {
    return $this->scraper()->deceasedDateTime->join('');
  }

  function normalizedDeceasedTimestamp() {
    $date = $this->getDeceasedDateTimeGMT();
    return $this->formatTimestamp($date);
  }

  function getDeceasedDateTime() {
    $date = $this->getDeceasedDateTimeGMT();
    return $this->formatTimestamp($date, ['local'=>true]);
  }
  
  public function getPreferredLanguage()
  {
    return $this->scraper()
      ->communication
      ->where('preferred', '=', true)
      ->language->text->join('');
  }
  
  public function getEmail($index=0)
  {
    return $this->scraper()
      ->telecom
      ->where('system', '=', 'email')
      ->value[$index]->join('');
  }

  /**
   * list of general practitioners, semicolon separated
   *
   * @return string
   */
  public function getGeneralPractitioner() {
    return $this->scraper()->generalPractitioner->display->join('; ');
  }

  public function getManagingOrganization()
  {
    return $this->scraper()->managingOrganization->display->join('');
  }

  public function getPronounsCode()
  {
    return $this->scraper()
      ->extension
      ->where('url', 'like', 'pronouns-to-use-for-text$')
      ->any('=', 'code')->join('');
  }

  public function getPronouns()
  {
    return $this->scraper()
      ->extension
      ->where('url', 'like', 'pronouns-to-use-for-text$')
      ->valueCodeableConcept
      ->coding->display->join();
  }

  public function getMaritalStatus() {
    return $this->scraper()
      ->maritalStatus
      ->text->join('');
  }

  /**
   * Get a specific contact by index
   *
   * @param int $index The contact index
   * @return Contact
   */
  public function getContact($index = 0)
  {
    $contact = $this->scraper()->contact[$index];
    return new Contact($contact);
  }

  /**
   * get a callable based on a mapping field
   *
   * @param string $field
   * @return callable
   */
  public function getCallable($field)
  {
    $callablesMap = [
      'fhir_id' => function() { return $this->getFhirID(); },
      'name-given' => function() { return $this->getNameGiven(); },
      'name-family' => function() { return $this->getNameFamily(); },
      'birthDate' => function() { return $this->getBirthDate(); },
      'gender' => function() { return $this->getGender(); },
      'gender-code' => function() { return $this->getGenderCode(); },
      'gender-text' => function() { return $this->getGenderText(); },
      'legal-sex' => function() { return $this->getLegalSex(); },
      'sex-for-clinical-use' => function() { return $this->getSexForClinicalUse(); },
      'race' => function() { return $this->getRaceCode(0); },
      'ethnicity' => function() { return $this->getEthnicityCode(); },
      'address-line' => function() { return $this->getAddressLine(); },
      'address-district' => function() { return $this->getAddressDistrict(); },  
      'address-city' => function() { return $this->getAddressCity(); },
      'address-state' => function() { return $this->getAddressState(); },
      'address-postalCode' => function() { return $this->getAddressPostalCode(); },
      'address-country' => function() { return $this->getAddressCountry(); },
      'phone-home' => function() { return $this->getPhoneHome(); },
      'phone-home-2' => function() { return $this->getPhoneHome(1); },
      'phone-home-3' => function() { return $this->getPhoneHome(2); },
      'phone-mobile' => function() { return $this->getPhoneMobile(); },
      'phone-mobile-2' => function() { return $this->getPhoneMobile(1); },
      'phone-mobile-3' => function() { return $this->getPhoneMobile(2); },
      'general-practitioner' => function() { return $this->getGeneralPractitioner(); },
      'managing-organization' => function() { return $this->getManagingOrganization(); },
      'deceasedBoolean' => function() { return intval($this->isDeceased()); },
      'deceasedDateTimeGMT' => function() { return $this->getDeceasedDateTimeGMT(); },
      'normalized_deceased_timestamp' => function() { return $this->normalizedDeceasedTimestamp(); },
      'deceasedDateTime' => function() { return $this->getDeceasedDateTime(); }, // local dat time, normalized
      'preferred-language' => function() { return $this->getPreferredLanguage(); },
      'email' => function() { return $this->getEmail(); },
      'email-2' => function() { return $this->getEmail(1); },
      'email-3' => function() { return $this->getEmail(2); },
      // 'pronouns-code' => function() { return $this->getPronounsCode(); },
      'pronouns' => function() { return $this->getPronouns(); },
      'marital-status' => function() { return $this->getMaritalStatus(); },
      'contact-relationship-1' => function() { return $this->getContact(0)->getRelationship(); },
      'contact-name-1' => function() { return $this->getContact(0)->getName(); },
      'contact-phone-1' => function() { return $this->getContact(0)->getPhone(); },
      'contact-relationship-2' => function() { return $this->getContact(1)->getRelationship(); },
      'contact-name-2' => function() { return $this->getContact(1)->getName(); },
      'contact-phone-2' => function() { return $this->getContact(1)->getPhone(); },
      'contact-relationship-3' => function() { return $this->getContact(2)->getRelationship(); },
      'contact-name-3' => function() { return $this->getContact(2)->getName(); },
      'contact-phone-3' => function() { return $this->getContact(2)->getPhone(); },
    ];
    $defaultCallable = function() { return ''; };
    $callable = $callablesMap[$field] ?? $defaultCallable;
    return $callable;
  }

  public function getData()
  {
    $data = [
      'fhir-id'                           => $this->getFhirID(),
      'name-given'                        => $this->getNameGiven(),
      'name-family'                       => $this->getNameFamily(),
      'birthDate'                         => $this->getBirthDate(),
      'gender'                            => $this->getGender(),
      'gender-code'                       => $this->getGenderCode(),
      'gender-text'                       => $this->getGenderText(),
      'legal-sex'                         => $this->getLegalSex(),
      'sex-for-clinical-use'              => $this->getSexForClinicalUse(),
      'race'                              => $this->getRaceCode(0),
      'ethnicity'                         => $this->getEthnicityCode(),
      'address-line'                      => $this->getAddressLine(),
      'address-district'                  => $this->getAddressDistrict(),
      'address-city'                      => $this->getAddressCity(),
      'address-state'                     => $this->getAddressState(),
      'address-postalCode'                => $this->getAddressPostalCode(),
      'address-country'                   => $this->getAddressCountry(),
      'phone-home'                        => $this->getPhoneHome(),
      'phone-home-2'                      => $this->getPhoneHome(1),
      'phone-home-3'                      => $this->getPhoneHome(2),
      'phone-mobile'                      => $this->getPhoneMobile(),
      'phone-mobile-2'                    => $this->getPhoneMobile(1),
      'phone-mobile-3'                    => $this->getPhoneMobile(2),
      'general-practitioner'              => $this->getGeneralPractitioner(),
      'managing-organization'             => $this->getManagingOrganization(),
      'deceasedBoolean'                   => intval($this->isDeceased()), // cast to int
      'deceasedBooleanRaw'                => $this->getDeceasedBoolean(), // cast to int
      'deceasedDateTimeGMT'               => $this->getDeceasedDateTimeGMT(),
      'normalized_deceased_timestamp'     => $this->normalizedDeceasedTimestamp(),
      'deceasedDateTime'                  => $this->getDeceasedDateTime(),
      'preferred-language'                => $this->getPreferredLanguage(),
      'email'                             => $this->getEmail(),
      'email-2'                           => $this->getEmail(1),
      'email-3'                           => $this->getEmail(2),
      // 'pronouns-code'                     => $this->getPronounsCode(),
      'pronouns'                          => $this->getPronouns(),
      'marital-status'                    => $this->getMaritalStatus(),
      'contact-relationship-1'            => $this->getContact(0)->getRelationship(),
      'contact-name-1'                    => $this->getContact(0)->getName(),
      'contact-phone-1'                   => $this->getContact(0)->getPhone(),
      'contact-relationship-2'            => $this->getContact(1)->getRelationship(),
      'contact-name-2'                    => $this->getContact(1)->getName(),
      'contact-phone-2'                   => $this->getContact(1)->getPhone(),
      'contact-relationship-3'            => $this->getContact(2)->getRelationship(),
      'contact-name-3'                    => $this->getContact(2)->getName(),
      'contact-phone-3'                   => $this->getContact(2)->getPhone(),
    ];
    return $data;
  }
  
}