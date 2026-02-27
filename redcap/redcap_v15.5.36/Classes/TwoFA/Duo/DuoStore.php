<?php
namespace Vanderbilt\REDCap\Classes\TwoFA\Duo;

use Session;
use Exception;
use Duo\DuoUniversal\Client;

/**
 * Value object responsible for persisting and restoring Duo authentication state.
 */
final class DuoStore {

    const CACHE_NAMESPACE_PREFIX = 'DUO_STATE';
    const CACHE_DATA_KEY = 'data';

    /**
     * state generate by Duo
     *
     * @var string
     */
    private $state;

    /**
     * username in the launch context
     *
     * @var string
     */
    private $username;

    /**
     * page that originated the 2FA process
     *
     * @var string
     */
    private $launchPage;

    /**
     * remember user based on REDCap settings
     *
     * @var boolean
     */
    private $rememberMe;

    public function __construct($state, $username, $launchPage, $rememberMe=false) {
        $this->state = $state;
        $this->username = $username;
        $this->launchPage = $launchPage;
        $this->rememberMe = $rememberMe;
    }

    /**
     * maximum number of tries allowed to get a unique state
     */
    const MAX_UNIQUE_STATE_GENERATION_ATTEMPTS = 100;

    /**
     * generate a unique state making sure that there is not an active
     * session with the same ID
     * 
     * @param Client $duoClient
     * @return string
     * @throws Exception if fails to generate a unique state after MAX_UNIQUE_STATE_GENERATION_ATTEMPTS
     */
    public static function makeUniqueState($client, $attemptNumber=0) {
        if($attemptNumber>self::MAX_UNIQUE_STATE_GENERATION_ATTEMPTS)
            throw new Exception("Could not generate a unique state for the custom session after $attemptNumber attempts.", 1);
        $state = $client->generateState();
        $existingSession = Session::read($state) == true;
        if(!$existingSession) return $state;
        return self::makeUniqueState($client, ++$attemptNumber);
    }

    public function state() { return $this->state; }
    public function username() { return $this->username; }
    public function launchPage() { return $this->launchPage; }
    public function rememberMe() { return $this->rememberMe; }
    public function setRememberMe($value) { $this->rememberMe = boolval($value); }

    /**
     * Persist the store in REDCap session storage.
     *
     * @return bool
     */
    public function save() {
        $payload = DuoStorePayloadCodec::encode($this);
        if (!is_string($payload) || $payload === '') {
            return false;
        }

        $state = $this->state();
        // Session storage is server-side; persist plaintext JSON payload.
        return Session::write($state, $payload) === true;
        /* $namespace = self::CACHE_NAMESPACE_PREFIX.$state;
        $cache = new FileCache($namespace);
        $cache->set(self::CACHE_DATA_KEY, $payload); */
    }

    /**
     * create a store from a cached file
     *
     * @param string $state
     * @return DuoStore|false
     */
    public static function fromState($state) {
        if (!is_string($state) || trim($state) === '') {
            return false;
        }

        $data = Session::read($state);
        if (!is_string($data) || $data === '') {
            return false;
        }

        $decoded = DuoStorePayloadCodec::decode($data);
        if (!is_array($decoded)) {
            return false;
        }

        if (($decoded['state'] ?? '') !== $state) {
            return false;
        }

        return new self(
            $decoded['state'],
            $decoded['username'],
            $decoded['launchPage'],
            $decoded['rememberMe']
        );
    }

}
