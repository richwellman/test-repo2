<?php

use PHPUnit\Framework\TestCase;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\FhirLauncher;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\DTOs\FhirCookieDTO;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\DTOs\FhirCookiePayloadCodec;

class FhirCookieDTOJsonPayloadTest extends TestCase
{
    private array $cookieBackup = [];
    private array $sessionBackup = [];

    protected function setUp(): void
    {
        $this->cookieBackup = $_COOKIE;
        $this->sessionBackup = (isset($_SESSION) && is_array($_SESSION)) ? $_SESSION : [];
    }

    protected function tearDown(): void
    {
        $_COOKIE = $this->cookieBackup;
        $_SESSION = $this->sessionBackup;
    }

    public function testFromNameHydratesDtoFromJsonCookiePayload(): void
    {
        $name = FhirLauncher::COOKIE_NAME . '-unit-test';

        $cookie = FhirCookieDTO::make($name);
        $cookie->state = 'state-123';
        $cookie->launchType = FhirLauncher::LAUNCHTYPE_EHR;

        $payload = FhirCookiePayloadCodec::encode($cookie);
        $this->assertIsString($payload);
        $this->assertNotSame('', $payload);

        $_COOKIE[$name] = $payload;
        unset($_SESSION[$name]);

        $decoded = FhirCookieDTO::fromName($name);
        $this->assertInstanceOf(FhirCookieDTO::class, $decoded);
        $this->assertSame($name, $decoded->name);
        $this->assertSame('state-123', $decoded->state);
        $this->assertSame(FhirLauncher::LAUNCHTYPE_EHR, $decoded->launchType);
    }

    public function testFromNameHydratesDtoFromSessionPayloadWhenCookieMissing(): void
    {
        $name = FhirLauncher::COOKIE_NAME . '-unit-test';

        $cookie = FhirCookieDTO::make($name);
        $cookie->state = 'state-456';
        $cookie->launchType = FhirLauncher::LAUNCHTYPE_STANDALONE;

        $payload = FhirCookiePayloadCodec::encode($cookie);
        $this->assertIsString($payload);

        unset($_COOKIE[$name]);
        $_SESSION[$name] = $payload;

        $decoded = FhirCookieDTO::fromName($name);
        $this->assertInstanceOf(FhirCookieDTO::class, $decoded);
        $this->assertSame($name, $decoded->name);
        $this->assertSame('state-456', $decoded->state);
        $this->assertSame(FhirLauncher::LAUNCHTYPE_STANDALONE, $decoded->launchType);
    }

    public function testFromNameReturnsSafeDefaultsForInvalidPayload(): void
    {
        $name = FhirLauncher::COOKIE_NAME . '-unit-test';

        $_COOKIE[$name] = '{"format":"wrong_format","data":{"state":"x","launchType":"ehr"}}';
        unset($_SESSION[$name]);

        $decoded = FhirCookieDTO::fromName($name);
        $this->assertInstanceOf(FhirCookieDTO::class, $decoded);
        $this->assertSame($name, $decoded->name);
        $this->assertSame('', $decoded->state);
        $this->assertSame('', $decoded->launchType);
    }
}
