<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\DTOs;

/**
 * Canonical JSON encoding/decoding for persisted FHIR launch cookie payloads.
 */
final class FhirCookiePayloadCodec
{
    private const FORMAT = 'fhir_launch_cookie_v1';

    private const PAYLOAD_KEY_FORMAT = 'format';
    private const PAYLOAD_KEY_DATA = 'data';
    private const DATA_KEY_STATE = 'state';
    private const DATA_KEY_LAUNCH_TYPE = 'launchType';

    /**
     * Encode cookie fields as versioned JSON.
     */
    public static function encode(FhirCookieDTO $cookie): ?string
    {
        $payload = [
            self::PAYLOAD_KEY_FORMAT => self::FORMAT,
            self::PAYLOAD_KEY_DATA => [
                self::DATA_KEY_STATE => strval($cookie->state ?? ''),
                self::DATA_KEY_LAUNCH_TYPE => strval($cookie->launchType ?? ''),
            ],
        ];

        try {
            return json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return null;
        }
    }

    /**
     * Decode and validate a versioned JSON cookie payload.
     */
    public static function decode(string $payload): ?array
    {
        if ($payload === '') {
            return null;
        }

        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return null;
        }

        if (!is_array($decoded)) {
            return null;
        }
        if (($decoded[self::PAYLOAD_KEY_FORMAT] ?? null) !== self::FORMAT) {
            return null;
        }

        $data = $decoded[self::PAYLOAD_KEY_DATA] ?? null;
        if (!is_array($data)) {
            return null;
        }

        $state = $data[self::DATA_KEY_STATE] ?? null;
        $launchType = $data[self::DATA_KEY_LAUNCH_TYPE] ?? null;
        if (!is_string($state) || !is_string($launchType)) {
            return null;
        }

        return [
            self::DATA_KEY_STATE => $state,
            self::DATA_KEY_LAUNCH_TYPE => $launchType,
        ];
    }
}
