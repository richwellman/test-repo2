<?php

namespace Vanderbilt\REDCap\Classes\Cache\StorageSystems {
    use RedcapUnitTests\RapidRetrieval\DatabaseStorageTestHarness;

    function db_query($sql, $params = [], $conn = null, $resultmode = null, $forceUsePrimaryDbConnection = false, $forceReplaceDataTable = false)
    {
        return DatabaseStorageTestHarness::handleDbQuery($sql, $params);
    }

    function db_errno()
    {
        return DatabaseStorageTestHarness::getCurrentErrno();
    }

    function db_error()
    {
        return DatabaseStorageTestHarness::getCurrentError();
    }

    function db_insert_id()
    {
        return 1;
    }

    function checkNull($value, $replaceMSchars = true)
    {
        return DatabaseStorageTestHarness::checkNull($value);
    }

    function db_escape($value, $replaceMSchars = true)
    {
        return addslashes($value);
    }

    class TestableDatabaseStorage extends DatabaseStorage
    {
        public array $loggedMessages = [];

        protected function logRetryExhausted(string $cache_key, int $attempt, int $errno, string $errorMessage): void
        {
            $message = sprintf(
                'Cache database write retry exhausted (project_id=%u, cache_key=%s, attempts=%d, errno=%s): %s',
                $this->project_id,
                $cache_key,
                $attempt,
                $errno,
                $errorMessage
            );
            DatabaseStorageTestHarness::recordErrorLog($message);
            $this->loggedMessages[] = $message;
        }
    }
}

namespace RedcapUnitTests\RapidRetrieval {

use PHPUnit\Framework\TestCase;
use Vanderbilt\REDCap\Classes\Cache\StorageSystems\StorageItem;
use Vanderbilt\REDCap\Classes\Cache\StorageSystems\TestableDatabaseStorage;

/**
 * Lightweight harness to script database behaviour during DatabaseStorage tests.
 *
 * The production code calls global helper functions (db_query, db_errno, etc.).
 * Inside the test namespace we redeclare those helpers to forward into this
 * harness, allowing us to queue deterministic responses without touching the
 * real database layer.
 */
class DatabaseStorageTestHarness
{
    private static array $queryResponses = [];
    private static array $errnoResponses = [];
    private static array $errorMessages = [];
    private static array $executedQueries = [];
    private static array $errorLogMessages = [];
    private static string $currentError = '';
    private static int $currentErrno = 0;

    /** Reset queued responses and any captured state. */
    public static function reset(): void
    {
        self::$queryResponses = [];
        self::$errnoResponses = [];
        self::$errorMessages = [];
        self::$executedQueries = [];
        self::$errorLogMessages = [];
        self::$currentErrno = 0;
        self::$currentError = '';
    }

    /**
     * Prime the harness with the sequence of outcomes the next db_query calls should return.
     *
     * @param array $responses Boolean (success/failure) responses for db_query.
     * @param array $errno     Matching MySQL error numbers to surface via db_errno.
     * @param array $errors    Matching error messages to surface via db_error.
     */
    public static function primeQueries(array $responses, array $errno, array $errors): void
    {
        self::$queryResponses = $responses;
        self::$errnoResponses = $errno;
        self::$errorMessages = $errors;
    }

    /**
     * Simulate db_query() using the queued responses.
     * Captures statements written to redcap_error_log so tests can assert logging.
     */
    public static function handleDbQuery($sql, $params)
    {
        self::$executedQueries[] = $sql;
        $response = array_shift(self::$queryResponses);
        if ($response === null) {
            $response = true;
        }

        if ($response === false) {
            self::$currentErrno = array_shift(self::$errnoResponses) ?? 0;
            self::$currentError = array_shift(self::$errorMessages) ?? '';
        } else {
            self::$currentErrno = 0;
            self::$currentError = '';
        }

        return $response;
    }

    /** Record an error-log entry generated through the System stub. */
    public static function recordErrorLog(string $message): void
    {
        self::$errorLogMessages[] = $message;
    }

    /** Expose the errno the harness is currently holding (mirrors db_errno). */
    public static function getCurrentErrno(): int
    {
        return self::$currentErrno;
    }

    /** Expose the error message the harness is currently holding (mirrors db_error). */
    public static function getCurrentError(): string
    {
        return self::$currentError;
    }

    /** Return the SQL statements seen so far (excluding error-log inserts). */
    public static function getExecutedQueries(): array
    {
        return self::$executedQueries;
    }

    /** Return the messages that were written to the error log during the test. */
    public static function getErrorLogMessages(): array
    {
        return self::$errorLogMessages;
    }

    public static function checkNull($value): string
    {
        if ($value === null || $value === '' || $value === false) {
            return 'NULL';
        }

        return "'" . addslashes((string) $value) . "'";
    }
}

/**
 * Exercises DatabaseStorage retry/backoff behaviour without requiring a live DB connection.
 */
class DatabaseStorageTest extends TestCase
{
    protected function setUp(): void
    {
        DatabaseStorageTestHarness::reset();
    }

    /**
     * Ensure a transient ER_LOCK_WAIT_TIMEOUT triggers a single retry and eventually persists the cache item.
     */
    public function testAddRetriesAndEventuallySucceeds(): void
    {
        DatabaseStorageTestHarness::primeQueries([
            false,
            true,
        ], [
            1205,
        ], [
            'Lock wait timeout exceeded; try restarting transaction',
        ]);

        $storage = new TestableDatabaseStorage(99);
        $item = $storage->add('retry-success-key', ['foo' => 'bar']);

        $this->assertInstanceOf(StorageItem::class, $item);
        $this->assertSame(2, count(DatabaseStorageTestHarness::getExecutedQueries()));
        $this->assertSame([], DatabaseStorageTestHarness::getErrorLogMessages());
    }

    /**
     * When every attempt hits a retryable error we should log the exhaustion and return false (skip caching).
     */
    public function testAddRetriesExhaustedLogsAndReturnsFalse(): void
    {
        DatabaseStorageTestHarness::primeQueries([
            false,
            false,
            false,
        ], [
            1205,
            1205,
            1205,
        ], [
            'Lock wait timeout 1',
            'Lock wait timeout 2',
            'Lock wait timeout 3',
        ]);

        $storage = new TestableDatabaseStorage(77);
        $item = $storage->add('retry-failure-key', ['baz' => 'qux']);

        $this->assertFalse($item);
        $this->assertSame(3, count(DatabaseStorageTestHarness::getExecutedQueries()));

        $logged = DatabaseStorageTestHarness::getErrorLogMessages();
        $this->assertCount(1, $logged);
        $this->assertStringContainsString('Cache database write retry exhausted', $logged[0]);
        $this->assertStringContainsString('retry-failure-key', $logged[0]);
    }
}

}
