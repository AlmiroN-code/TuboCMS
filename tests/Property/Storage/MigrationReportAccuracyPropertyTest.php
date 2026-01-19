<?php

declare(strict_types=1);

namespace App\Tests\Property\Storage;

use App\Service\MigrationReportService;
use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Property-based tests for migration report accuracy.
 * 
 * **Feature: remote-storage, Property 9: Migration report accuracy**
 * **Validates: Requirements 4.5**
 * 
 * Property: For any completed migration with S successes and F failures,
 * the summary report SHALL show exactly S successful and F failed counts.
 */
class MigrationReportAccuracyPropertyTest extends TestCase
{
    use TestTrait;

    private MigrationReportService $reportService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reportService = new MigrationReportService(new NullLogger());
    }

    /**
     * Property 9: Report shows exact success count.
     * 
     * For any migration with S recorded successes,
     * the summary report SHALL show successCount = S.
     */
    public function testReportShowsExactSuccessCount(): void
    {
        $this->forAll(
            Generator\choose(0, 20), // Количество успехов
            Generator\choose(0, 20)  // Общее количество файлов (>= успехов)
        )->then(function (int $successCount, int $extraFiles): void {
            $totalFiles = $successCount + $extraFiles;
            $migrationId = $this->reportService->generateMigrationId();
            
            // Создаём отчёт
            $this->reportService->createReport(
                $migrationId,
                $totalFiles,
                'Source Storage',
                'Destination Storage'
            );
            
            // Записываем успехи
            for ($i = 1; $i <= $successCount; $i++) {
                $this->reportService->recordSuccess($migrationId, $i);
            }
            
            // Получаем сводку
            $summary = $this->reportService->getSummary($migrationId);
            
            $this->assertTrue($summary['found'], 'Report should be found');
            $this->assertEquals(
                $successCount,
                $summary['successCount'],
                \sprintf(
                    'Expected %d successes, got %d',
                    $successCount,
                    $summary['successCount']
                )
            );
            
            // Очищаем
            $this->reportService->deleteReport($migrationId);
        });
    }

    /**
     * Property 9: Report shows exact failure count.
     * 
     * For any migration with F recorded failures,
     * the summary report SHALL show failureCount = F.
     */
    public function testReportShowsExactFailureCount(): void
    {
        $this->forAll(
            Generator\choose(0, 20), // Количество неудач
            Generator\choose(0, 20)  // Дополнительные файлы
        )->then(function (int $failureCount, int $extraFiles): void {
            $totalFiles = $failureCount + $extraFiles;
            $migrationId = $this->reportService->generateMigrationId();
            
            // Создаём отчёт
            $this->reportService->createReport(
                $migrationId,
                $totalFiles,
                'Source Storage',
                'Destination Storage'
            );
            
            // Записываем неудачи
            for ($i = 1; $i <= $failureCount; $i++) {
                $this->reportService->recordFailure(
                    $migrationId,
                    $i,
                    "Error for file {$i}"
                );
            }
            
            // Получаем сводку
            $summary = $this->reportService->getSummary($migrationId);
            
            $this->assertTrue($summary['found'], 'Report should be found');
            $this->assertEquals(
                $failureCount,
                $summary['failureCount'],
                \sprintf(
                    'Expected %d failures, got %d',
                    $failureCount,
                    $summary['failureCount']
                )
            );
            
            // Очищаем
            $this->reportService->deleteReport($migrationId);
        });
    }


    /**
     * Property 9: Report shows exact combined counts.
     * 
     * For any migration with S successes and F failures,
     * the summary report SHALL show exactly S successful and F failed counts.
     */
    public function testReportShowsExactCombinedCounts(): void
    {
        $this->forAll(
            Generator\choose(0, 15), // Количество успехов
            Generator\choose(0, 15)  // Количество неудач
        )->then(function (int $successCount, int $failureCount): void {
            $totalFiles = $successCount + $failureCount;
            $migrationId = $this->reportService->generateMigrationId();
            
            // Создаём отчёт
            $this->reportService->createReport(
                $migrationId,
                $totalFiles,
                'Source Storage',
                'Destination Storage'
            );
            
            // Записываем успехи
            for ($i = 1; $i <= $successCount; $i++) {
                $this->reportService->recordSuccess($migrationId, $i);
            }
            
            // Записываем неудачи
            for ($i = 1; $i <= $failureCount; $i++) {
                $this->reportService->recordFailure(
                    $migrationId,
                    $successCount + $i,
                    "Error for file {$i}"
                );
            }
            
            // Получаем сводку
            $summary = $this->reportService->getSummary($migrationId);
            
            $this->assertTrue($summary['found'], 'Report should be found');
            $this->assertEquals(
                $successCount,
                $summary['successCount'],
                \sprintf(
                    'Expected %d successes, got %d',
                    $successCount,
                    $summary['successCount']
                )
            );
            $this->assertEquals(
                $failureCount,
                $summary['failureCount'],
                \sprintf(
                    'Expected %d failures, got %d',
                    $failureCount,
                    $summary['failureCount']
                )
            );
            $this->assertEquals(
                $totalFiles,
                $summary['processedCount'],
                \sprintf(
                    'Expected %d processed, got %d',
                    $totalFiles,
                    $summary['processedCount']
                )
            );
            
            // Очищаем
            $this->reportService->deleteReport($migrationId);
        });
    }

    /**
     * Property 9: Completed migration has correct status.
     * 
     * For any migration where all files are processed (S + F = total),
     * the status SHALL be 'completed'.
     */
    public function testCompletedMigrationHasCorrectStatus(): void
    {
        $this->forAll(
            Generator\choose(0, 10), // Количество успехов
            Generator\choose(0, 10)  // Количество неудач
        )->then(function (int $successCount, int $failureCount): void {
            $totalFiles = $successCount + $failureCount;
            
            // Пропускаем случай с 0 файлами
            if ($totalFiles === 0) {
                $this->assertTrue(true);
                return;
            }
            
            $migrationId = $this->reportService->generateMigrationId();
            
            // Создаём отчёт
            $this->reportService->createReport(
                $migrationId,
                $totalFiles,
                'Source Storage',
                'Destination Storage'
            );
            
            // Записываем успехи
            for ($i = 1; $i <= $successCount; $i++) {
                $this->reportService->recordSuccess($migrationId, $i);
            }
            
            // Записываем неудачи
            for ($i = 1; $i <= $failureCount; $i++) {
                $this->reportService->recordFailure(
                    $migrationId,
                    $successCount + $i,
                    "Error for file {$i}"
                );
            }
            
            // Получаем сводку
            $summary = $this->reportService->getSummary($migrationId);
            
            $this->assertTrue($summary['found'], 'Report should be found');
            $this->assertEquals(
                'completed',
                $summary['status'],
                'Migration should be completed when all files are processed'
            );
            $this->assertTrue(
                $summary['isComplete'],
                'isComplete should be true when all files are processed'
            );
            
            // Очищаем
            $this->reportService->deleteReport($migrationId);
        });
    }

    /**
     * Property 9: In-progress migration has correct status.
     * 
     * For any migration where not all files are processed (S + F < total),
     * the status SHALL be 'in_progress'.
     */
    public function testInProgressMigrationHasCorrectStatus(): void
    {
        $this->forAll(
            Generator\choose(0, 5),  // Количество успехов
            Generator\choose(0, 5),  // Количество неудач
            Generator\choose(1, 10)  // Дополнительные необработанные файлы
        )->then(function (int $successCount, int $failureCount, int $remainingFiles): void {
            $totalFiles = $successCount + $failureCount + $remainingFiles;
            $migrationId = $this->reportService->generateMigrationId();
            
            // Создаём отчёт
            $this->reportService->createReport(
                $migrationId,
                $totalFiles,
                'Source Storage',
                'Destination Storage'
            );
            
            // Записываем успехи
            for ($i = 1; $i <= $successCount; $i++) {
                $this->reportService->recordSuccess($migrationId, $i);
            }
            
            // Записываем неудачи
            for ($i = 1; $i <= $failureCount; $i++) {
                $this->reportService->recordFailure(
                    $migrationId,
                    $successCount + $i,
                    "Error for file {$i}"
                );
            }
            
            // Получаем сводку
            $summary = $this->reportService->getSummary($migrationId);
            
            $this->assertTrue($summary['found'], 'Report should be found');
            $this->assertEquals(
                'in_progress',
                $summary['status'],
                'Migration should be in_progress when not all files are processed'
            );
            $this->assertFalse(
                $summary['isComplete'],
                'isComplete should be false when not all files are processed'
            );
            $this->assertEquals(
                $remainingFiles,
                $summary['remainingCount'],
                \sprintf(
                    'Expected %d remaining, got %d',
                    $remainingFiles,
                    $summary['remainingCount']
                )
            );
            
            // Очищаем
            $this->reportService->deleteReport($migrationId);
        });
    }


    /**
     * Property 9: Progress percentage is accurate.
     * 
     * For any migration with total T files and P processed,
     * the progress percentage SHALL be (P / T) * 100.
     */
    public function testProgressPercentageIsAccurate(): void
    {
        $this->forAll(
            Generator\choose(1, 20), // Общее количество файлов (минимум 1)
            Generator\choose(0, 100) // Процент обработанных (0-100)
        )->then(function (int $totalFiles, int $processedPercent): void {
            $processedCount = (int) \floor($totalFiles * $processedPercent / 100);
            $successCount = (int) \floor($processedCount / 2);
            $failureCount = $processedCount - $successCount;
            
            $migrationId = $this->reportService->generateMigrationId();
            
            // Создаём отчёт
            $this->reportService->createReport(
                $migrationId,
                $totalFiles,
                'Source Storage',
                'Destination Storage'
            );
            
            // Записываем успехи
            for ($i = 1; $i <= $successCount; $i++) {
                $this->reportService->recordSuccess($migrationId, $i);
            }
            
            // Записываем неудачи
            for ($i = 1; $i <= $failureCount; $i++) {
                $this->reportService->recordFailure(
                    $migrationId,
                    $successCount + $i,
                    "Error for file {$i}"
                );
            }
            
            // Получаем сводку
            $summary = $this->reportService->getSummary($migrationId);
            
            $this->assertTrue($summary['found'], 'Report should be found');
            
            $expectedPercent = \round(($processedCount / $totalFiles) * 100, 1);
            $this->assertEquals(
                $expectedPercent,
                $summary['progressPercent'],
                \sprintf(
                    'Expected %.1f%% progress, got %.1f%%',
                    $expectedPercent,
                    $summary['progressPercent']
                )
            );
            
            // Очищаем
            $this->reportService->deleteReport($migrationId);
        });
    }

    /**
     * Property 9: hasFailures flag is accurate.
     * 
     * For any migration with F failures,
     * hasFailures SHALL be true if F > 0, false otherwise.
     */
    public function testHasFailuresFlagIsAccurate(): void
    {
        $this->forAll(
            Generator\choose(0, 10), // Количество успехов
            Generator\choose(0, 10)  // Количество неудач
        )->then(function (int $successCount, int $failureCount): void {
            $totalFiles = $successCount + $failureCount;
            
            // Пропускаем случай с 0 файлами
            if ($totalFiles === 0) {
                $this->assertTrue(true);
                return;
            }
            
            $migrationId = $this->reportService->generateMigrationId();
            
            // Создаём отчёт
            $this->reportService->createReport(
                $migrationId,
                $totalFiles,
                'Source Storage',
                'Destination Storage'
            );
            
            // Записываем успехи
            for ($i = 1; $i <= $successCount; $i++) {
                $this->reportService->recordSuccess($migrationId, $i);
            }
            
            // Записываем неудачи
            for ($i = 1; $i <= $failureCount; $i++) {
                $this->reportService->recordFailure(
                    $migrationId,
                    $successCount + $i,
                    "Error for file {$i}"
                );
            }
            
            // Получаем сводку
            $summary = $this->reportService->getSummary($migrationId);
            
            $this->assertTrue($summary['found'], 'Report should be found');
            $this->assertEquals(
                $failureCount > 0,
                $summary['hasFailures'],
                \sprintf(
                    'hasFailures should be %s when failure count is %d',
                    $failureCount > 0 ? 'true' : 'false',
                    $failureCount
                )
            );
            
            // Очищаем
            $this->reportService->deleteReport($migrationId);
        });
    }

    /**
     * Property 9: Failure details are recorded accurately.
     * 
     * For any migration with F failures,
     * getFailures() SHALL return exactly F failure records.
     */
    public function testFailureDetailsAreRecordedAccurately(): void
    {
        $this->forAll(
            Generator\choose(0, 10) // Количество неудач
        )->then(function (int $failureCount): void {
            $migrationId = $this->reportService->generateMigrationId();
            
            // Создаём отчёт
            $this->reportService->createReport(
                $migrationId,
                $failureCount,
                'Source Storage',
                'Destination Storage'
            );
            
            // Записываем неудачи с уникальными сообщениями
            $expectedErrors = [];
            for ($i = 1; $i <= $failureCount; $i++) {
                $errorMessage = "Error message for file {$i}";
                $expectedErrors[$i] = $errorMessage;
                $this->reportService->recordFailure($migrationId, $i, $errorMessage);
            }
            
            // Получаем список неудач
            $failures = $this->reportService->getFailures($migrationId);
            
            $this->assertCount(
                $failureCount,
                $failures,
                \sprintf(
                    'Expected %d failure records, got %d',
                    $failureCount,
                    \count($failures)
                )
            );
            
            // Проверяем, что все ошибки записаны
            foreach ($failures as $failure) {
                $this->assertArrayHasKey('videoFileId', $failure);
                $this->assertArrayHasKey('error', $failure);
                $this->assertArrayHasKey('timestamp', $failure);
                
                $fileId = $failure['videoFileId'];
                $this->assertArrayHasKey($fileId, $expectedErrors);
                $this->assertEquals($expectedErrors[$fileId], $failure['error']);
            }
            
            // Очищаем
            $this->reportService->deleteReport($migrationId);
        });
    }
}
