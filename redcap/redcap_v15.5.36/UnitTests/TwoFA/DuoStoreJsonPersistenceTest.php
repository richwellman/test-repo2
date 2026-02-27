<?php

use PHPUnit\Framework\TestCase;
use Vanderbilt\REDCap\Classes\TwoFA\Duo\DuoStore;

class DuoStoreJsonPersistenceTest extends TestCase
{
    public function testSaveStoresPlaintextJsonPayloadAndFromStateHydratesStore(): void
    {
        $state = $this->makeState();
        $store = new DuoStore($state, $username = 'duo-unit-user', $launchPage = '/index.php?pid=1', true);

        try {
            $this->assertTrue($store->save());

            $raw = \Session::read($state);
            $this->assertIsString($raw);
            $this->assertNotSame('', $raw);
            $decodedPayload = json_decode($raw, true);
            $this->assertIsArray($decodedPayload);
            $this->assertSame('duo_store_v1', $decodedPayload['format'] ?? null);

            $restored = DuoStore::fromState($state);
            $this->assertInstanceOf(DuoStore::class, $restored);
            $this->assertSame($state, $restored->state());
            $this->assertSame($username, $restored->username());
            $this->assertSame($launchPage, $restored->launchPage());
            $this->assertTrue($restored->rememberMe());
        } finally {
            \Session::destroy($state);
        }
    }

    public function testFromStateReturnsFalseForInvalidJsonFormat(): void
    {
        $state = $this->makeState();
        $invalidPayload = json_encode([
            'format' => 'invalid_duo_format',
            'data' => [
                'state' => $state,
                'username' => 'duo-unit-user',
                'launchPage' => '/index.php?pid=1',
                'rememberMe' => true,
            ],
        ]);

        try {
            $this->assertIsString($invalidPayload);
            \Session::write($state, $invalidPayload);
            $this->assertFalse(DuoStore::fromState($state));
        } finally {
            \Session::destroy($state);
        }
    }

    private function makeState(): string
    {
        return substr(hash('sha256', uniqid('duo_state_', true)), 0, 32);
    }
}
