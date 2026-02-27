<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Utility;

use DateInterval;
use DateTime;
use DateTimeZone;

class CernerAppointmentDateNormalizer
{
    /**
     * Normalize raw date filters into Cerner-compliant search values.
     *
     * @param array<int,string> $dateFilters
     * @param callable|null $fallback Producer returning an array of raw values when input is empty.
     * @return array<int,string>
     */
    public static function normalizeStrings(array $dateFilters, ?callable $fallback = null): array
    {
        $normalized = [];

        foreach ($dateFilters as $rawValue) {
            if (!is_string($rawValue)) continue;
            $value = trim($rawValue);
            if ($value === '') continue;

            $prefix = 'ge';
            $payload = $value;

            if (preg_match('/^(ge|gt|le|lt|eq)(.+)$/i', $value, $matches)) {
                $prefix = strtolower($matches[1]);
                $payload = trim($matches[2]);
            }

            switch ($prefix) {
                case 'le':
                    $prefix = 'lt';
                    break;
                case 'gt':
                case 'eq':
                case '':
                    $prefix = 'ge';
                    break;
                default:
                    if (!in_array($prefix, ['ge', 'lt'], true)) {
                        $prefix = 'ge';
                    }
            }

            $payload = self::ensureUtcPayload($payload);
            if ($payload === null) continue;

            $normalized[] = $prefix.$payload;
        }

        if (empty($normalized) && $fallback) {
            $fallbackValues = call_user_func($fallback);
            if (is_array($fallbackValues) && !empty($fallbackValues)) {
                $normalized = self::normalizeStrings($fallbackValues);
            }
        }

        return $normalized;
    }

    /**
     * Build Cerner-compliant filters from a date range.
     *
     * @param DateTime|null $dateMin
     * @param DateTime|null $dateMax
     * @return array<int,string>
     */
    public static function fromDateRange(?DateTime $dateMin, ?DateTime $dateMax): array
    {
        $filters = [];
        if ($dateMin instanceof DateTime) {
            $filters[] = 'ge'.self::formatUtc($dateMin);
        }
        if ($dateMax instanceof DateTime) {
            $filters[] = 'lt'.self::formatUtc($dateMax);
        }

        return self::normalizeStrings($filters, function () {
            $start = new DateTime('now', new DateTimeZone('UTC'));
            $end = (clone $start)->add(new DateInterval('P1D'));
            return [
                'ge'.self::formatUtc($start),
                'lt'.self::formatUtc($end),
            ];
        });
    }

    /**
     * Ensure the payload contains a UTC designator when a time is present.
     */
    private static function ensureUtcPayload(string $value): ?string
    {
        if ($value === '') return null;

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value.'T00:00:00Z';
        }

        if (strpos($value, 'T') === false) {
            return $value.'T00:00:00Z';
        }

        if (preg_match('/(Z|[\+\-]\d{2}:?\d{2})$/', $value)) {
            return $value;
        }

        return $value.'Z';
    }

    /**
     * Format a DateTime instance as UTC with a trailing Z.
     */
    private static function formatUtc(DateTime $dateTime): string
    {
        $utcDate = clone $dateTime;
        $utcDate->setTimezone(new DateTimeZone('UTC'));
        return $utcDate->format('Y-m-d\TH:i:s').'Z';
    }
}

