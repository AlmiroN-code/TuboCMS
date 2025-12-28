<?php

declare(strict_types=1);

namespace App\Tests\Property\Storage;

use App\Storage\AbstractStorageAdapter;
use App\Storage\DTO\ConnectionTestResult;
use App\Storage\DTO\StorageQuota;
use App\Storage\DTO\UploadResult;

/**
 * Testable storage adapter for property-based testing of retry logic.
 * 
 * Overrides sleep() to record delays without actually sleeping,
 * and exposes protected methods for testing.
 */
class TestableStorageAdapter extends AbstractStorageAdapter
{
    /** @var int[] */
    private array $recordedDelays = [];

    /**
     * Override sleep to record delays without actually sleeping.
     */
    protected function sleep(int $seconds): void
    {
        $this->recordedDelays[] = $seconds;
    }

    /**
     * Get recorded delays for verification.
     * 
     * @return int[]
     */
    public function getRecordedDelays(): array
    {
        return $this->recordedDelays;
    }

    /**
     * Reset recorded delays.
     */
    public function resetRecordedDelays(): void
    {
        $this->recordedDelays = [];
    }

    /**
     * Public wrapper for protected calculateDelay method.
     */
    public function publicCalculateDelay(int $attempt): int
    {
        return $this->calculateDelay($attempt);
    }

    /**
     * Public wrapper for protected executeWithRetry method.
     */
    public function publicExecuteWithRetry(callable $operation, string $operationName): mixed
    {
        return $this->executeWithRetry($operation, $operationName);
    }

    // Required interface methods (not used in retry tests)

    public function upload(string $localPath, string $remotePath): UploadResult
    {
        throw new \RuntimeException('Not implemented for testing');
    }

    public function download(string $remotePath, string $localPath): bool
    {
        throw new \RuntimeException('Not implemented for testing');
    }

    public function delete(string $remotePath): bool
    {
        throw new \RuntimeException('Not implemented for testing');
    }

    public function exists(string $remotePath): bool
    {
        throw new \RuntimeException('Not implemented for testing');
    }

    public function getUrl(string $remotePath): string
    {
        throw new \RuntimeException('Not implemented for testing');
    }

    public function getSignedUrl(string $remotePath, int $expiresIn = 3600): string
    {
        throw new \RuntimeException('Not implemented for testing');
    }

    public function testConnection(): ConnectionTestResult
    {
        throw new \RuntimeException('Not implemented for testing');
    }

    public function getQuota(): ?StorageQuota
    {
        return null;
    }

    public function createDirectory(string $path): bool
    {
        throw new \RuntimeException('Not implemented for testing');
    }
}
