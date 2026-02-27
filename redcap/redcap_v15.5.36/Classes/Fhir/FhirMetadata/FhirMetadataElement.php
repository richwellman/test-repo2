<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirMetadata;

use Vanderbilt\REDCap\Classes\DTOs\DTO;

class FhirMetadataElement extends DTO
{
  
    public $field;
    public $temporal;
    public $label;
    public $description;
    public $category;
    public $subcategory;
    public $identifier;
    // metadata. used for special cases (e.g. decorators)
    public $disabled;
    public $disabled_reason;
    
    public function __construct($data=[]) {
        parent::__construct($data);
        if ($this->identifier) {
            // Always set the source id field's cat and subcat to blank so that it's viewed separate from the other fields
            $this->category = $this->subcategory = '';
        }
    }

    public function visitProperty($key, $value)
    {
        switch ($key) {
            case 'field':
            case 'label':
            case 'description':
            case 'category':
            case 'subcategory':
            case 'disabled_reason':
                $value = strval($value ?? '');
                break;
            case 'temporal':
            case 'disabled':
            case 'identifier':
                $value = boolval($value ?? false);
                break;
            default:
                break;
        }
        return $value;
    }
}