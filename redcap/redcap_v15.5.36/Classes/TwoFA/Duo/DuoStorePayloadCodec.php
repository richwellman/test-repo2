<?php
namespace Vanderbilt\REDCap\Classes\TwoFA\Duo;

/**
 * Canonical JSON encoding/decoding for persisted Duo state payloads.
 */
final class DuoStorePayloadCodec
{
    private const FORMAT = 'duo_store_v1';

    private const PAYLOAD_KEY_FORMAT = 'format';
    private const PAYLOAD_KEY_DATA = 'data';
    private const DATA_KEY_STATE = 'state';
    private const DATA_KEY_USERNAME = 'username';
    private const DATA_KEY_LAUNCH_PAGE = 'launchPage';
    private const DATA_KEY_REMEMBER_ME = 'rememberMe';

    /**
     * Encode Duo state fields as versioned JSON.
     */
    public static function encode(DuoStore $store): ?string
    {
        $payload = [
            self::PAYLOAD_KEY_FORMAT => self::FORMAT,
            self::PAYLOAD_KEY_DATA => [
                self::DATA_KEY_STATE => strval($store->state()),
                self::DATA_KEY_USERNAME => strval($store->username()),
                self::DATA_KEY_LAUNCH_PAGE => strval($store->launchPage()),
                self::DATA_KEY_REMEMBER_ME => boolval($store->rememberMe()),
            ],
        ];

        try {
            return json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return null;
        }
    }

    /**
     * Decode and validate a versioned JSON Duo payload.
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
        $username = $data[self::DATA_KEY_USERNAME] ?? null;
        $launchPage = $data[self::DATA_KEY_LAUNCH_PAGE] ?? null;
        $rememberMe = $data[self::DATA_KEY_REMEMBER_ME] ?? null;

        if (!is_string($state) || !is_string($username) || !is_string($launchPage)) {
            return null;
        }

        if (is_bool($rememberMe)) {
            $rememberMeValue = $rememberMe;
        } elseif (is_int($rememberMe) || is_string($rememberMe)) {
            $rememberMeValue = filter_var($rememberMe, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($rememberMeValue === null) {
                return null;
            }
        } else {
            return null;
        }

        return [
            self::DATA_KEY_STATE => $state,
            self::DATA_KEY_USERNAME => $username,
            self::DATA_KEY_LAUNCH_PAGE => $launchPage,
            self::DATA_KEY_REMEMBER_ME => $rememberMeValue,
        ];
    }
}
