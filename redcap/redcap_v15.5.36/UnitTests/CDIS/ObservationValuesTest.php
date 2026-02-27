<?php

use PHPUnit\Framework\TestCase;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Shared\Observation;

/**
 * Validates getValue() output per supported FHIR observation value type.
 */
class ObservationValuesTest extends TestCase
{
    private function buildObservation(array $payloadOverrides): Observation
    {
        $payload = array_merge(['resourceType' => 'Observation'], $payloadOverrides);
        return new Observation($payload);
    }

    public function testGetValueFromString(): void
    {
        $observation = $this->buildObservation([
            'valueString' => 'negative',
        ]);

        $this->assertSame('negative', $observation->getValue());
    }

    public function testGetValueFromBoolean(): void
    {
        $observation = $this->buildObservation([
            'valueBoolean' => false,
        ]);

        $this->assertSame('false', $observation->getValue());
    }

    public function testGetValueFromInteger(): void
    {
        $observation = $this->buildObservation([
            'valueInteger' => 7,
        ]);

        $this->assertSame('7', $observation->getValue());
    }

    public function testGetValueFromTime(): void
    {
        $observation = $this->buildObservation([
            'valueTime' => '05:20:00',
        ]);

        $this->assertSame('05:20:00', $observation->getValue());
    }

    public function testGetValueFromDateTime(): void
    {
        $observation = $this->buildObservation([
            'valueDateTime' => '2021-09-11T05:20:00Z',
        ]);

        $this->assertSame('2021-09-11T05:20:00Z', $observation->getValue());
    }

    public function testGetValueFromCodeableConceptText(): void
    {
        $observation = $this->buildObservation([
            'valueCodeableConcept' => [
                'text' => 'Positive',
            ],
        ]);

        $this->assertSame('Positive', $observation->getValue());
    }

    public function testGetValueFromQuantityZero(): void
    {
        $observation = $this->buildObservation([
            'valueQuantity' => [
                'value' => 0,
                'unit' => '10*3/UL',
                'system' => 'http://unitsofmeasure.org',
                'code' => '10*3/UL',
            ],
        ]);

        $this->assertSame('0', $observation->getValue());
    }

    public function testGetValueFromPeriod(): void
    {
        $observation = $this->buildObservation([
            'valuePeriod' => [
                'start' => '2021-09-11',
                'end' => '2021-09-12',
            ],
        ]);

        $this->assertSame('2021-09-11 -> 2021-09-12', $observation->getValue());
    }

    public function testGetValueFromRatio(): void
    {
        $observation = $this->buildObservation([
            'valueRatio' => [
                'numerator' => ['value' => 1],
                'denominator' => ['value' => 2],
            ],
        ]);

        $this->assertSame('1 / 2', $observation->getValue());
    }

    public function testGetValueFromRange(): void
    {
        $observation = $this->buildObservation([
            'valueRange' => [
                'low' => ['value' => 0, 'unit' => 'mg'],
                'high' => ['value' => 1.2, 'unit' => 'mg'],
            ],
        ]);

        $this->assertSame('0 mg - 1.2 mg', $observation->getValue());
    }

    public function testGetValueFromSampledData(): void
    {
        $observation = $this->buildObservation([
            'valueSampledData' => [
                'low' => ['value' => 0, 'unit' => 'mg'],
                'high' => ['value' => 2, 'unit' => 'mg'],
            ],
        ]);

        $this->assertSame('0 mg - 2 mg', $observation->getValue());
    }
}
