<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources;


use JsonSerializable;
use Vanderbilt\REDCap\Classes\JsonParser\Nodes\Node;
use Vanderbilt\REDCap\Classes\JsonParser\Nodes\NodeFactory;

abstract class AbstractResource implements JsonSerializable, ResourceInterface
{
  
  /**
   *
   * @var Node
   */
  private $scraper;
  
  private $payload;

  public function __construct($payload)
  {
    $this->payload = $payload;
  }

  public function getPayload()
  {
    return $this->payload;
  }

  public function isEmpty()
  {
    return empty($this->payload);
  }

  public function scraper() {
    if(!$this->scraper) $this->scraper = NodeFactory::make($this->payload);
    return $this->scraper;
  }

  /**
   *
   * @param ResourceVisitorInterface $visitor
   * @return mixed
   */
  public function accept($visitor)
  {
    return $visitor->visit($this);
  }

  public function getMetadata()
  {
    $metadata = [
      'payload' => $this->payload,
    ];
    return $metadata;
  }

  #[\ReturnTypeWillChange]
  public function jsonSerialize()
  {
    $data = $this->getData();
    $metadata = $this->getMetadata();
    return compact('data', 'metadata');
  }

  /**
   * create a new observation
   * replacing the existing payload with
   * the provided one
   *
   * @param string $key
   * @param array $payload
   * @return AbstractResource
   */
  protected function replacePayload($parentPayload, $payload) {
    $merged = array_merge($parentPayload, $payload);
    return new static($merged);
  }

}