<?php
namespace Vanderbilt\REDCap\Classes\Cache\StorageSystems;

use System;
use Vanderbilt\REDCap\Classes\Cache\REDCapCache;

/**
 * use the database to store cache
 */
class DatabaseStorage implements StorageInterface
{
    protected $project_id;

    const CACHE_TABLE = 'redcap_cache';

    const MAX_RETRY_ATTEMPTS = 3;
    const INITIAL_BACKOFF_MICROSECONDS = 100000; // 100ms
    const MAX_BACKOFF_MICROSECONDS = 1600000; // ~1.6s cap
    /**
     * MySQL error codes that signal transient lock/contention situations where a retry is appropriate.
     * 1205: ER_LOCK_WAIT_TIMEOUT ("Lock wait timeout exceeded; try restarting transaction")
     * 1213: ER_LOCK_DEADLOCK ("Deadlock found when trying to get lock; try restarting transaction")
     * @see: https://dev.mysql.com/doc/mysql-errors/8.0/en/server-error-reference.html
     */
    const RETRYABLE_ERROR_CODES = [1205, 1213];

    public function __construct($project_id)
    {
        $this->project_id = $project_id;
    }

    public function get($cache_key) {
        $tableName = self::CACHE_TABLE;
        $query = sprintf(
            "SELECT * FROM $tableName WHERE project_id = %u AND `cache_key` = %s LIMIT 1",
            $this->project_id, checkNull($cache_key)
        );
        $result = db_query($query);
        if($row = db_fetch_assoc($result)) {
            $ts = $row['ts'] ?? '';
            $expiration = $row['expiration'] ?? '';
            $invalidation_strategies = unserialize($row['invalidation_strategies'] ?? '', ['allowed_classes'=> false]);
            $data = unserialize($row['data'] ?? '', ['allowed_classes'=> false]);
            return new StorageItem($cache_key, $ts, $expiration, $invalidation_strategies, $data);
        }
        return false;
    }

    public function add($cache_key, $data, $ttl=null, $invalidationStrategies=[]) {
        $tableName = self::CACHE_TABLE;
        $serialized = serialize($data);
        $serializedInvalidationStrategies = serialize($invalidationStrategies);
        // TODO: adjust this based on the final decided size of the data field in the database
        if($this->isSizeExceeded($serialized, self::SIZE_LONG_BLOB)) return; // do not store if potentially truncated when saved

        $ts = date(REDCapCache::TIMESTAMP_FORMAT);
        $expiration = null;
        if(is_int($ttl)) $expiration = date(REDCapCache::TIMESTAMP_FORMAT, time() + $ttl);

        $query = sprintf(
            "INSERT INTO $tableName (`project_id`, `cache_key`, `data`, `ts`, `expiration`, `invalidation_strategies`)
                VALUES (%u, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                `data` = %s, `ts` = %s, `expiration` = %s, `invalidation_strategies` = %s;",
            $this->project_id,
            $db_cache_key = checkNull($cache_key),
            $db_data = checkNull($serialized),
            $db_ts = checkNull($ts),
            $db_expiration = checkNull($expiration),
            $db_InvalidationStrategies = checkNull($serializedInvalidationStrategies),
            $db_data,
            $db_ts,
            $db_expiration,
            $db_InvalidationStrategies
        );
        
        $attempt = 0;
        $backoff = self::INITIAL_BACKOFF_MICROSECONDS;

        while (true) {
            $result = db_query($query);
            if ($result !== false) {
                return new StorageItem($cache_key, $ts, $expiration, $invalidationStrategies, $data);
            }

            $errno = db_errno();
            $shouldRetry = $this->shouldRetry($errno);
            $attempt++;

            if (!$shouldRetry || $attempt >= self::MAX_RETRY_ATTEMPTS) {
                if ($shouldRetry) {
                    $this->logRetryExhausted($cache_key, $attempt, $errno, db_error());
                    return false; // gracefully skip caching
                }

                throw new \Exception("Error saving cache in the database: ".db_error(), 1);
            }

            $backoff = $this->applyBackoffDelay($backoff);
        }
    }

    public function delete($cache_key) {
        $tableName = self::CACHE_TABLE;
        $query = sprintf(
            "DELETE FROM `$tableName` WHERE project_id = %u AND `cache_key` = %s",
            $this->project_id, checkNull($cache_key)
        );
        $result = db_query($query);
        return $result;
    }

    public function getList() {
        $tableName = self::CACHE_TABLE;
        $query = sprintf(
            "SELECT `cache_key`, `ts`, `expiration`, `invalidation_strategies`
            FROM $tableName WHERE project_id = %u",
            $this->project_id
        );
        $result = db_query($query);
        $list = [];
        while($row = db_fetch_assoc($result)) {
            $cache_key = $row['cache_key'] ?? null;
            if(!$cache_key) continue;
            $ts = $row['ts'] ?? '';
            $expiration = $row['expiration'] ?? '';
            $invalidation_strategies = unserialize($row['invalidation_strategies'] ?? '', ['allowed_classes' => false]);
            $listItem = new StorageItem($cache_key, $ts, $expiration, $invalidation_strategies);
            $list[$cache_key] = $listItem;
        }
        return $list;
    }

    /**
     * max bytes allowed in a TEXT field of a MySQL database
     * (as used by redcap_data)
     */
    const SIZE_BLOB = 65536;
    const SIZE_MEDIUM_BLOB = 16777216;
    const SIZE_LONG_BLOB = 4294967296;

  /**
   *
   * @param string $string
   * @param int $byteCount
   * @return void
   */
  function isSizeExceeded($string, $maxTextSize) {
    // Get the size of the string in bytes
    $stringSize = strlen($string);
    
    // Check if the size of the string exceeds the maximum size of TEXT data type
    if ($stringSize > $maxTextSize) {
      return true; // Size exceeded
    } else {
      return false; // Size within limit
    }
  }

    /**
     * Determine whether the MySQL error code represents a transient lock issue.
     *
     * @param int|null $errno last database error code from db_errno()
     * @return bool true when the error is one of the retryable lock/timeout codes
     */
    private function shouldRetry($errno)
    {
        if (!is_int($errno)) return false;
        return in_array($errno, self::RETRYABLE_ERROR_CODES, true);
    }

    /**
     * Apply an exponential backoff delay between retries.
     */
    private function applyBackoffDelay(int $currentBackoff): int
    {
        $delay = max(self::INITIAL_BACKOFF_MICROSECONDS, min($currentBackoff, self::MAX_BACKOFF_MICROSECONDS));
        usleep($delay);
        $nextBackoff = min($delay * 2, self::MAX_BACKOFF_MICROSECONDS);
        return $nextBackoff;
    }

    /**
     * Log the exhausted retry failure to the REDCap error log.
     * Extracted for easier testing/substitution.
     */
    protected function logRetryExhausted(string $cache_key, int $attempt, int $errno, string $errorMessage): void
    {
        System::addErrorToRCErrorLogTable(sprintf(
            'Cache database write retry exhausted (project_id=%u, cache_key=%s, attempts=%d, errno=%s): %s',
            $this->project_id,
            $cache_key,
            $attempt,
            $errno,
            $errorMessage
        ));
    }

}
