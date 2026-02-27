<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Utility;

use Vanderbilt\REDCap\Classes\Fhir\FhirClient;
use Vanderbilt\REDCap\Classes\Fhir\FhirSystem\FhirSystem;
use Vanderbilt\REDCap\Classes\Fhir\Resources\ConformanceStatement;

/**
 * Utility for inferring the active EHR vendor from capability statements.
 *
 * The inspector pulls identifiers from the FHIR capability statement and the
 * configured EHR system record, then exposes convenience methods for matching
 * well-known vendors.
 */
class FhirVendorInspector
{
    /** @var FhirClient */
    private $fhirClient;

    /** @var ConformanceStatement|null */
    private $conformanceStatement;

    /** @var array<string,bool> */
    private $matchCache = [];

    /** @var string[]|null */
    private $candidates;

    /**
     * @param FhirClient $fhirClient Client used to access capability statements.
     */
    public function __construct(FhirClient $fhirClient)
    {
        $this->fhirClient = $fhirClient;
    }

    /**
     * Resolve the capability statement for the active FHIR client.
     *
     * @return ConformanceStatement|null
     */
    private function getConformanceStatement(): ?ConformanceStatement
    {
        if ($this->conformanceStatement instanceof ConformanceStatement) {
            return $this->conformanceStatement;
        }

        $manager = $this->fhirClient->getFhirVersionManager();
        if (!$manager) {
            return null;
        }

        $statement = $manager->getConformanceStatement();
        if ($statement instanceof ConformanceStatement) {
            $this->conformanceStatement = $statement;
        }

        return $this->conformanceStatement ?? null;
    }

    /**
     * Gather identifying strings exposed by the capability statement and EHR config.
     *
     * The capability statement may label the same system under different fields
     * (software name, publisher, implementation description, etc.), and REDCap’s
     * own EHR settings store additional descriptors such as the configured name
     * or identifier.  By aggregating all of these values once we can run simple
     * regex checks without worrying which field a given vendor chose to populate.
     *
     * @return string[]
     */
    private function getCandidateStrings(): array
    {
        if (is_array($this->candidates)) {
            return $this->candidates;
        }

        $candidates = [];

        if ($statement = $this->getConformanceStatement()) {
            $candidates[] = $statement->getSoftwareName();
            $candidates[] = $statement->getName();
            $candidates[] = $statement->getTitle();
            $candidates[] = $statement->getPublisher();
            $candidates[] = $statement->getImplementationDescription();
            $candidates[] = $statement->getImplementationUrl();
        }

        $fhirSystem = $this->fhirClient->getFhirSystem();
        if ($fhirSystem instanceof FhirSystem) {
            $candidates[] = $fhirSystem->getEhrName();
        }

        $filtered = array_filter($candidates, function ($value) {
            return is_string($value) && $value !== '';
        });

        $this->candidates = array_values(array_unique($filtered));

        return $this->candidates;
    }

    /**
     * Check whether any known identifiers match the provided regular expression.
     *
     * @param string $pattern PCRE pattern (delimiters included).
     * @return bool
     */
    private function matchesPattern(string $pattern): bool
    {
        if (array_key_exists($pattern, $this->matchCache)) {
            return $this->matchCache[$pattern];
        }

        $matched = false;
        foreach ($this->getCandidateStrings() as $candidate) {
            if (preg_match($pattern, $candidate)) {
                $matched = true;
                break;
            }
        }

        $this->matchCache[$pattern] = $matched;

        return $matched;
    }

    /**
     * Determine whether the active system is Epic.
     */
    public function isEpic(): bool
    {
        return $this->matchesPattern('/epic/i');
    }

    /**
     * Determine whether the active system is Cerner / Oracle Health.
     */
    public function isCerner(): bool
    {
        return $this->matchesPattern('/(cerner|oracle\s*health|millennium)/i');
    }

    /**
     * Semantic alias for Cerner detection.
     */
    public function isOracleHealth(): bool
    {
        return $this->isCerner();
    }
}
