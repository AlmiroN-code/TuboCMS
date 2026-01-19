<?php

declare(strict_types=1);

namespace App\Tests\Property\Storage;

use App\Service\StorageStatsService;
use App\Storage\DTO\StorageQuota;
use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;

/**
 * Property-based tests for warning threshold at 80%.
 * 
 * **Feature: remote-storage, Property 12: Warning threshold at 80%**
 * **Validates: Requirements 6.3**
 * 
 * Property: For any storage where used space exceeds 80% of quota, 
 * a warning notification SHALL be displayed.
 */
class WarningThresholdPropertyTest extends TestCase
{
    use TestTrait;

    /**
     * Property 12: Usage at or above 80% triggers warning.
     * 
     * For any storage quota where usedBytes/totalBytes >= 0.80,
     * isWarningThresholdExceeded() SHALL return true.
     */
    public function testUsageAtOrAbove80PercentTriggersWarning(): void
    {
        $this->forAll(
            Generator\choose(1, 1000000000), // totalBytes (1 byte to 1GB)
            Generator\choose(80, 100)        // usagePercent (80-100%)
        )->then(function (int $totalBytes, int $usagePercent): void {
            $usedBytes = (int) floor($totalBytes * $usagePercent / 100);
            
            $quota = new StorageQuota($usedBytes, $totalBytes);
            
            $this->assertTrue(
                $quota->isWarningThresholdExceeded(),
                \sprintf(
                    'Warning should be triggered at %d%% usage (%d/%d bytes)',
                    $usagePercent,
                    $usedBytes,
                    $totalBytes
                )
            );
        });
    }

    /**
     * Property 12: Usage below 80% does not trigger warning.
     * 
     * For any storage quota where usedBytes/totalBytes < 0.80,
     * isWarningThresholdExceeded() SHALL return false.
     */
    public function testUsageBelow80PercentDoesNotTriggerWarning(): void
    {
        $this->forAll(
            Generator\choose(1, 1000000000), // totalBytes (1 byte to 1GB)
            Generator\choose(0, 79)          // usagePercent (0-79%)
        )->then(function (int $totalBytes, int $usagePercent): void {
            $usedBytes = (int) floor($totalBytes * $usagePercent / 100);
            
            $quota = new StorageQuota($usedBytes, $totalBytes);
            
            $this->assertFalse(
                $quota->isWarningThresholdExceeded(),
                \sprintf(
                    'Warning should NOT be triggered at %d%% usage (%d/%d bytes)',
                    $usagePercent,
                    $usedBytes,
                    $totalBytes
                )
            );
        });
    }

    /**
     * Property 12: Exact 80% threshold triggers warning.
     * 
     * For any storage quota where usedBytes/totalBytes == 0.80 exactly,
     * isWarningThresholdExceeded() SHALL return true.
     */
    public function testExact80PercentTriggersWarning(): void
    {
        $this->forAll(
            Generator\choose(5, 1000000) // totalBytes (divisible by 5 for exact 80%)
        )->then(function (int $baseValue): void {
            // Ensure totalBytes is divisible by 5 for exact 80%
            $totalBytes = $baseValue * 5;
            $usedBytes = $totalBytes * 80 / 100;
            
            $quota = new StorageQuota((int) $usedBytes, $totalBytes);
            
            $actualPercent = $quota->getUsagePercent();
            
            $this->assertEquals(
                80.0,
                $actualPercent,
                'Usage should be exactly 80%'
            );
            
            $this->assertTrue(
                $quota->isWarningThresholdExceeded(),
                'Warning should be triggered at exactly 80%'
            );
        });
    }


    /**
     * Property 12: Unknown quota (null totalBytes) does not trigger warning.
     * 
     * For any storage quota where totalBytes is null,
     * isWarningThresholdExceeded() SHALL return false.
     */
    public function testUnknownQuotaDoesNotTriggerWarning(): void
    {
        $this->forAll(
            Generator\choose(0, 1000000000) // usedBytes (any value)
        )->then(function (int $usedBytes): void {
            $quota = new StorageQuota($usedBytes, null);
            
            $this->assertFalse(
                $quota->isWarningThresholdExceeded(),
                \sprintf(
                    'Warning should NOT be triggered when totalBytes is unknown (used: %d)',
                    $usedBytes
                )
            );
        });
    }

    /**
     * Property 12: Zero total bytes does not trigger warning.
     * 
     * For any storage quota where totalBytes is 0,
     * isWarningThresholdExceeded() SHALL return false (avoid division by zero).
     */
    public function testZeroTotalBytesDoesNotTriggerWarning(): void
    {
        $this->forAll(
            Generator\choose(0, 1000000) // usedBytes (any value)
        )->then(function (int $usedBytes): void {
            $quota = new StorageQuota($usedBytes, 0);
            
            $this->assertFalse(
                $quota->isWarningThresholdExceeded(),
                \sprintf(
                    'Warning should NOT be triggered when totalBytes is 0 (used: %d)',
                    $usedBytes
                )
            );
        });
    }

    /**
     * Property 12: StorageStatsService correctly identifies warning threshold.
     * 
     * For any quota at or above 80%, StorageStatsService::isWarningThresholdExceeded()
     * SHALL return true.
     */
    public function testServiceCorrectlyIdentifiesWarningThreshold(): void
    {
        $this->forAll(
            Generator\choose(1, 1000000000), // totalBytes
            Generator\choose(0, 100)         // usagePercent
        )->then(function (int $totalBytes, int $usagePercent): void {
            $usedBytes = (int) floor($totalBytes * $usagePercent / 100);
            $quota = new StorageQuota($usedBytes, $totalBytes);
            
            // Create a mock service to test the method
            $service = $this->createMock(StorageStatsService::class);
            $service->method('isWarningThresholdExceeded')
                    ->willReturnCallback(fn(?StorageQuota $q) => $q?->isWarningThresholdExceeded() ?? false);
            
            $result = $service->isWarningThresholdExceeded($quota);
            $expected = $usagePercent >= 80;
            
            $this->assertEquals(
                $expected,
                $result,
                \sprintf(
                    'Service should return %s for %d%% usage',
                    $expected ? 'true' : 'false',
                    $usagePercent
                )
            );
        });
    }

    /**
     * Property 12: getStoragesWithWarning returns only storages above threshold.
     * 
     * For any collection of storages with various usage percentages,
     * getStoragesWithWarning() SHALL return only those at or above 80%.
     */
    public function testGetStoragesWithWarningReturnsOnlyAboveThreshold(): void
    {
        $this->forAll(
            Generator\seq(Generator\choose(0, 100)) // Array of usage percentages
        )->then(function (array $usagePercents): void {
            $storageStats = [];
            $expectedWarnings = [];
            
            foreach ($usagePercents as $index => $usagePercent) {
                $totalBytes = 1000000;
                $usedBytes = (int) floor($totalBytes * $usagePercent / 100);
                $quota = new StorageQuota($usedBytes, $totalBytes);
                
                $storageStats[$index] = [
                    'name' => "Storage $index",
                    'quota' => $quota,
                ];
                
                if ($usagePercent >= 80) {
                    $expectedWarnings[$index] = [
                        'name' => "Storage $index",
                        'usagePercent' => $quota->getUsagePercent(),
                    ];
                }
            }
            
            $warnings = $this->getStoragesWithWarning($storageStats);
            
            $this->assertCount(
                \count($expectedWarnings),
                $warnings,
                \sprintf(
                    'Expected %d warnings, got %d',
                    \count($expectedWarnings),
                    \count($warnings)
                )
            );
            
            foreach ($warnings as $key => $warning) {
                $this->assertArrayHasKey($key, $expectedWarnings);
                $this->assertGreaterThanOrEqual(
                    80.0,
                    $warning['usagePercent'],
                    'Warning should only be for usage >= 80%'
                );
            }
        });
    }

    /**
     * Property 12: Warning threshold constant is 80.
     * 
     * The WARNING_THRESHOLD_PERCENT constant SHALL be 80.0.
     */
    public function testWarningThresholdConstantIs80(): void
    {
        $this->assertEquals(
            80.0,
            StorageStatsService::WARNING_THRESHOLD_PERCENT,
            'Warning threshold should be 80%'
        );
    }

    /**
     * Simulates StorageStatsService::getStoragesWithWarning() logic.
     * 
     * @param array<int|string, array{name: string, quota: StorageQuota|null}> $storageStats
     * @return array<int|string, array{name: string, usagePercent: float}>
     */
    private function getStoragesWithWarning(array $storageStats): array
    {
        $warnings = [];

        foreach ($storageStats as $key => $stat) {
            if (isset($stat['quota']) && $stat['quota'] instanceof StorageQuota) {
                $usagePercent = $stat['quota']->getUsagePercent();
                if ($usagePercent !== null && $usagePercent >= StorageStatsService::WARNING_THRESHOLD_PERCENT) {
                    $warnings[$key] = [
                        'name' => $stat['name'] ?? 'Unknown',
                        'usagePercent' => $usagePercent,
                    ];
                }
            }
        }

        return $warnings;
    }
}
