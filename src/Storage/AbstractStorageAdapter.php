<?php

declare(strict_types=1);

namespace App\Storage;

use App\Storage\DTO\ConnectionTestResult;
use App\Storage\DTO\StorageQuota;
use App\Storage\DTO\UploadResult;
use Psr\Log\LoggerInterface;

/**
 * Abstract base class for storage adapters with common retry logic.
 * 
 * Implements exponential backoff retry mechanism for failed operations.
 * Requirement 2.5: IF upload fails THEN the System SHALL retry up to 3 times with exponential backoff
 */
abstract class AbstractStorageAdapter implements StorageAdapterInterface
{
    protected const MAX_RETRIES = 3;
    protected const BASE_DELAY_SECONDS = 1;

    protected ?LoggerInterface $logger = null;

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Execute an operation with retry logic and exponential backoff.
     * 
     * For attempt N (where N < 3), the next retry occurs after 2^N seconds delay.
     * Attempt 1: 2^1 = 2 seconds
     * Attempt 2: 2^2 = 4 seconds
     * 
     * @param callable $operation The operation to execute
     * @param string $operationName Name of the operation for logging
     * @return mixed Result of the operation
     * @throws \RuntimeException If all retry attempts fail
     */
    protected function executeWithRetry(callable $operation, string $operationName): mixed
    {
        $lastException = null;
        
        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                return $operation();
            } catch (\Throwable $e) {
                $lastException = $e;
                
                $this->logger?->warning(
                    sprintf(
                        'Storage operation "%s" failed on attempt %d/%d: %s',
                        $operationName,
                        $attempt,
                        self::MAX_RETRIES,
                        $e->getMessage()
                    )
                );
                
                if ($attempt < self::MAX_RETRIES) {
                    $delay = $this->calculateDelay($attempt);
                    $this->logger?->info(
                        sprintf('Retrying "%s" in %d seconds...', $operationName, $delay)
                    );
                    $this->sleep($delay);
                }
            }
        }
        
        $this->logger?->error(
            sprintf(
                'Storage operation "%s" failed after %d attempts',
                $operationName,
                self::MAX_RETRIES
            )
        );
        
        throw new \RuntimeException(
            sprintf(
                'Operation "%s" failed after %d attempts: %s',
                $operationName,
                self::MAX_RETRIES,
                $lastException?->getMessage() ?? 'Unknown error'
            ),
            0,
            $lastException
        );
    }

    /**
     * Calculate delay for retry attempt using exponential backoff.
     * 
     * Formula: 2^attempt seconds
     * Attempt 1: 2 seconds
     * Attempt 2: 4 seconds
     * 
     * @param int $attempt Current attempt number (1-based)
     * @return int Delay in seconds
     */
    protected function calculateDelay(int $attempt): int
    {
        return (int) pow(2, $attempt);
    }

    /**
     * Sleep for specified seconds. Extracted for testability.
     * 
     * @param int $seconds Number of seconds to sleep
     */
    protected function sleep(int $seconds): void
    {
        sleep($seconds);
    }

    /**
     * Get the maximum number of retry attempts.
     */
    public function getMaxRetries(): int
    {
        return self::MAX_RETRIES;
    }
}
