<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\DTOs;

use Vanderbilt\REDCap\Classes\DTOs\DTO;

/**
 * HTTP only cookie used to collect information
 * about the FHIR authenication process
 * 
 * It is created during before the authentication phase (both EHR and standalone launch).
 * It is deleted in NoState and ErrorState
 */
class FhirCookieDTO extends DTO {

	/**
	 * name
	 *
	 * @var string
	 */
	public $name;

	/**
	 * data stored in the cookie
	 * must be used as a string->string dictionary
	 *
	 * @var string
	 */
	public $launchType = '';

	/**
	 * state (identifier) of the EHR session
	 *
	 * @var string
	 */
	public $state = '';

	/**
	 * static creator
	 *
	 * @param string $name
	 * @return FhirCookie
	 */
	public static function make($name)
	{
		$instance = new self();
		$instance->name = $name;
		return $instance;
	}

	/**
	 * save a cookie
	 *
	 * @param integer $lifespan
	 * @param string $path
	 * @param string $domain
	 * @param boolean $secure
	 * @param boolean $httponly
	 * @return FhirCookie
	 */
	public function save($lifespan=0, $path = "/", $domain = '', $secure = false, $httponly = true) {
		$payload = FhirCookiePayloadCodec::encode($this);
		if(!is_string($payload) || $payload==='') return $this;

		$success = self::set(
			$name = $this->name,
			$data = $payload,
			...func_get_args()
		);
		if($success) {
			// make it immediately available
			$_COOKIE[$name] = $data;
			// also add to session if available
			if (session_status() != PHP_SESSION_ACTIVE) session_start();
			// Keep the same payload in session to support cookie-less follow-up requests.
			$_SESSION[$name] = $data;
		}
		return $this;
	}

	/**
	 * make a FhirCookie using the data available
	 * in the COOKIE superglobal
	 *
	 * @param string $name
	 * @return FhirCookie
	 */
	public static function fromName($name) {
		$payload = $_COOKIE[$name] ?? ''; // try cookie first
		if(!is_string($payload) || $payload==='') {
			$payload = $_SESSION[$name] ?? '';
		}

		$data = is_string($payload) ? FhirCookiePayloadCodec::decode($payload) : null;
		$instance = self::make($name);
		if(is_array($data)) $instance->loadData($data);
		return $instance;
	}

	/**
	 * make a cookie friendly duration based 
	 * on the provided seconds
	 *
	 * @param int $lifespan in seconds
	 * @return int Unix timestamp or 0 for session cookies
	 */
	public static function makeExpiration(int $lifespan): int {
		$expiration = ($lifespan==0) ? 0 : time()+$lifespan;
		return $expiration;
	}

	
	public static function destroy(string $name): void {
		self::set($name, '', -3600);
		unset($_COOKIE[$name]);
		unset($_SESSION[$name]);
	}

	/**
	 * set a cookie
	 *
	 * @param string $name
	 * @param string $data
	 * @param int $lifespan 0 = destroy when browser session is closed
	 * @param string $path
	 * @param string $domain
	 * @param bool $secure
	 * @param bool $httponly
	 * @return bool
	 */
	public static function set(string $name, string $data, int $lifespan=0, string $path = "/", string $domain = '', bool $secure = false, bool $httponly = true): bool
	{
		$expiration = self::makeExpiration($lifespan);
		$success = setcookie(
			$name,
			$data,
			$expiration,
			$path,
			$domain,
			$secure,
			$httponly
		);
		return $success;
	}
}
