<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources\Shared;

use Vanderbilt\REDCap\Classes\Fhir\Resources\AbstractResource;

class Contact 
{
    private $contact;
    
    public function __construct($contact)
    {
        $this->contact = $contact;
    }
    
    public function getRelationship()
    {
        $text = $this->contact
            ->relationship
            ->text
            ->join('');
        if(!empty($text)) return $text;
        return $this->contact
            ->relationship
            ->coding
            ->display
            ->join('');
    }
    
    public function getRelationshipCode()
    {
        return $this->contact
            ->relationship
            ->coding
            ->code
            ->join('');
    }
    
    public function getName()
    {
        return $this->contact
            ->name
            ->text
            ->join('');
    }
    
    public function getNameGiven()
    {
        return $this->contact
            ->name
            ->given
            ->join(' ');
    }
    
    public function getNameFamily()
    {
        return $this->contact
            ->name
            ->family
            ->join('');
    }
    
    public function getPhone($use='home|work')
    {
        return $this->contact
            ->telecom
            ->where('system', '=', 'phone')
            ->where('use', '~', $use)
            ->value
            ->join('');
    }
    
    public function getOrganization()
    {
        return $this->contact
            ->organization
            ->display
            ->join('');
    }
    
    public function getAddressLine()
    {
        return $this->contact
            ->address
            ->line
            ->join(' ');
    }
    
    public function getAddressCity()
    {
        return $this->contact
            ->address
            ->city
            ->join('');
    }
    
    public function getAddressState()
    {
        return $this->contact
            ->address
            ->state
            ->join('');
    }
    
    public function getAddressPostalCode()
    {
        return $this->contact
            ->address
            ->postalCode
            ->join('');
    }
    
    public function getAddressCountry()
    {
        return $this->contact
            ->address
            ->country
            ->join('');
    }
    
    public function getPeriodEnd()
    {
        return $this->contact
            ->period
            ->end
            ->join('');
    }
    
    public function getData()
    {
        $data = [
            'relationship'         => $this->getRelationship(),
            'relationship-code'    => $this->getRelationshipCode(),
            'name'                 => $this->getName(),
            'name-given'           => $this->getNameGiven(),
            'name-family'          => $this->getNameFamily(),
            'phone'                => $this->getPhone(),
            'organization'         => $this->getOrganization(),
            'address-line'         => $this->getAddressLine(),
            'address-city'         => $this->getAddressCity(),
            'address-state'        => $this->getAddressState(),
            'address-postalCode'   => $this->getAddressPostalCode(),
            'address-country'      => $this->getAddressCountry(),
            'period-end'           => $this->getPeriodEnd(),
        ];
        return $data;
    }
}