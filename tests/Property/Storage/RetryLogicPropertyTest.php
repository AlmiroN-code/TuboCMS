<?php

declare(strict_types=1);

namespace App\Tests\Property\Storage;

use App\Storage\AbstractStorageAdapter;
use App\Storage\DTO\ConnectionTestResult;
use App\Storage\DTO\StorageQuota;
use App\Storage\DTO\UploadResult;
use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Property-based tests for retry logic with exponential backoff.
 * 
 * **Feature: remote-storage, Property 4: Retry logic with exponential backoff**
 * **Validates: Requirements 2.5**
 * 
 * Property: For any failed upload attempt N (where N < 3), the next retry 
 * SHALL occur after 2^N seconds delay.
 */
class RetryLogicPropertyTest extends TestCase
{
    use TestTrait;

    /**
     * Property 4: Exponential backoff delay calculation.
     * 
     * For any attempt number N (1, 2), the delay SHALL be 2^N seconds.
     * Attempt 1 -> 2 seconds
     * Attempt 2 -> 4 seconds
     */
    public function testExponentialBackoffDelayCalculation(): void
    {
        $this->forAll(
            Generator\choose(1, 2) // Attempts 1 and 2 (before max retries)
        )->then(function (int $attempt): void {
            $adapter = new TestableStorageAdapter();
            
            $expectedDelay = (int) pow(2, $attempt);
            $actualDelay = $adapter->publicCalculateDelay($attempt);
            
            $this->assertSame(
                $expectedDelay,
                $actualDelay,
                \sprintf(
                    'Attempt %d should have delay of 2^%d = %d seconds, got %d',
                    $attempt,
                    $attempt,
                    $expectedDelay,
                    $actualDelay
                )
            );
        });
    }

    /**
     * Property 4: Retry attempts are limited to MAX_RETRIES (3).
     * 
     * For any operation that always fails, exactly MAX_RETRIES attempts SHALL be made.
     */
    public function testMaxRetryAttemptsAreRespected(): void
    {
        $this->forAll(
            Generator\constant(true) // Always fail
        )->then(function (bool $_): void {
            $adapter = new TestableStorageAdapter();
            $adapter->setLogger(new NullLogger());
            
            $attemptCount = 0;
            $operation = function () use (&$attemptCount): never {
                $attemptCount++;
                throw new \RuntimeException('Simulated failure');
            };
            
            try {
                $adapter->publicExecuteWithRetry($operation, 'test_operation');
                $this->fail('Expected RuntimeException was not thrown');
            } catch (\RuntimeException $e) {
                $this->assertSame(
                    3,
                    $attemptCount,
                    'Exactly 3 retry attempts should be made'
                );
                $this->assertStringContainsString(
                    'failed after 3 attempts',
                    $e->getMessage()
                );
            }
        });
    }

    /**
     * Property 4: Successful operation on first attempt returns immediately.
     * 
     * For any operation that succeeds on first attempt, no retries SHALL occur.
     */
    public function testSuccessfulOperationReturnsImmediately(): void
    {
        $this->forAll(
            Generator\string()
        )->then(function (string $expectedResult): void {
            $adapter = new TestableStorageAdapter();
            $adapter->setLogger(new NullLogger());
            
            $attemptCount = 0;
            $operation = function () use (&$attemptCount, $expectedResult): string {
                $attemptCount++;
                return $expectedResult;
            };
            
            $result = $adapter->publicExecuteWithRetry($operation, 'test_operation');
            
            $this->assertSame(1, $attemptCount, 'Only one attempt should be made on success');
            $this->assertSame($expectedResult, $result);
        });
    }

    /**
     * Property 4: Operation succeeding on Nth attempt returns result.
     * 
     * For any operation that fails N-1 times then succeeds, the result SHALL be returned
     * and exactly N attempts SHALL be made.
     */
    public function testOperationSucceedsAfterRetries(): void
    {
        $this->forAll(
            Generator\choose(1, 3), // Succeed on attempt 1, 2, or 3
            Generator\string()
        )->then(function (int $succeedOnAttempt, string $expectedResult): void {
            $adapter = new TestableStorageAdapter();
            $adapter->setLogger(new NullLogger());
            
            $attemptCount = 0;
            $operation = function () use (&$attemptCount, $succeedOnAttempt, $expectedResult): string {
                $attemptCount++;
                if ($attemptCount < $succeedOnAttempt) {
                    throw new \RuntimeException('Simulated failure');
                }
                return $expectedResult;
            };
            
            $result = $adapter->publicExecuteWithRetry($operation, 'test_operation');
            
            $this->assertSame(
                $succeedOnAttempt,
                $attemptCount,
                \sprintf('Should make exactly %d attempts', $succeedOnAttempt)
            );
            $this->assertSame($expectedResult, $result);
        });
    }

    /**
     * Property 4: Delays are recorded correctly for failed attempts.
     * 
     * For any operation that fails all attempts, delays SHALL follow 2^N pattern.
     */
    public function testDelaysFollowExponentialPattern(): void
    {
        $this->forAll(
            Generator\constant(true)
        )->then(function (bool $_): void {
            $adapter = new TestableStorageAdapter();
            $adapter->setLogger(new NullLogger());
            
            $operation = function (): never {
                throw new \RuntimeException('Simulated failure');
            };
            
            try {
                $adapter->publicExecuteWithRetry($operation, 'test_operation');
            } catch (\RuntimeException) {
                // Expected
            }
            
            $recordedDelays = $adapter->getRecordedDelays();
            
            // Should have 2 delays (after attempt 1 and 2, not after attempt 3)
            $this->assertCount(2, $recordedDelays, 'Should record 2 delays');
            $this->assertSame(2, $recordedDelays[0], 'First delay should be 2^1 = 2 seconds');
            $this->assertSame(4, $recordedDelays[1], 'Second delay should be 2^2 = 4 seconds');
        });
    }

    /**
     * Property 4: Exception from last attempt is preserved.
     * 
     * For any operation that fails all attempts, the final exception message SHALL be included.
     */
    public function testLastExceptionIsPreserved(): void
    {
        $this->forAll(
            Generator\suchThat(
                fn($s) => \strlen($s) > 0 && \strlen($s) < 100,
                Generator\string()
            )
        )->then(function (string $errorMessage): void {
            $adapter = new TestableStorageAdapter();
            $adapter->setLogger(new NullLogger());
            
            $operation = function () use ($errorMessage): never {
                throw new \RuntimeException($errorMessage);
            };
            
            try {
                $adapter->publicExecuteWithRetry($operation, 'test_operation');
                $this->fail('Expected RuntimeException was not thrown');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString(
                    $errorMessage,
                    $e->getMessage(),
                    'Final exception message should contain original error'
                );
                $this->assertInstanceOf(
                    \RuntimeException::class,
                    $e->getPrevious(),
                    'Previous exception should be preserved'
                );
            }
        });
    }
}
