<?php

namespace App\Command;

use App\Repository\VideoRepository;
use App\Service\PerformanceMonitorService;
use App\Service\RecommendationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-performance',
    description: 'Тестирование производительности оптимизированных методов'
)]
class TestPerformanceCommand extends Command
{
    public function __construct(
        private VideoRepository $videoRepository,
        private RecommendationService $recommendationService,
        private PerformanceMonitorService $performanceMonitor
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('iterations', 'i', InputOption::VALUE_OPTIONAL, 'Количество итераций', 10)
            ->addOption('test-search', null, InputOption::VALUE_NONE, 'Тестировать поиск')
            ->addOption('test-recommendations', null, InputOption::VALUE_NONE, 'Тестировать рекомендации')
            ->addOption('test-queries', null, InputOption::VALUE_NONE, 'Тестировать запросы');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $iterations = (int) $input->getOption('iterations');

        $io->title('Тестирование производительности RexTube');

        if ($input->getOption('test-search')) {
            $this->testSearchPerformance($io, $iterations);
        }

        if ($input->getOption('test-recommendations')) {
            $this->testRecommendationsPerformance($io, $iterations);
        }

        if ($input->getOption('test-queries')) {
            $this->testQueriesPerformance($io, $iterations);
        }

        // Если не выбраны тесты, запускаем все
        if (!$input->getOption('test-search') && 
            !$input->getOption('test-recommendations') && 
            !$input->getOption('test-queries')) {
            $this->runAllTests($io, $iterations);
        }

        // Генерируем отчет
        $report = $this->performanceMonitor->generatePerformanceReport();
        $io->section('Отчет о производительности');
        $io->table(
            ['Метрика', 'Значение'],
            [
                ['Время выполнения', round($report['performance']['total_execution_time'], 4) . 's'],
                ['Использование памяти', $this->formatBytes($report['performance']['memory_usage'])],
                ['Пиковая память', $this->formatBytes($report['performance']['peak_memory'])],
                ['Кеш доступен', $report['cache']['cache_available'] ? 'Да' : 'Нет'],
                ['БД доступна', $report['database']['database_available'] ? 'Да' : 'Нет'],
            ]
        );

        return Command::SUCCESS;
    }

    private function testSearchPerformance(SymfonyStyle $io, int $iterations): void
    {
        $io->section('Тестирование производительности поиска');

        $searchQueries = ['video', 'test', 'sample', 'demo', 'content'];
        $totalTime = 0;

        $io->progressStart($iterations * count($searchQueries));

        for ($i = 0; $i < $iterations; $i++) {
            foreach ($searchQueries as $query) {
                $startTime = microtime(true);
                
                $results = $this->performanceMonitor->trackQuery(
                    'search_videos',
                    fn() => $this->videoRepository->searchVideos($query, 10, 0)
                );
                
                $executionTime = microtime(true) - $startTime;
                $totalTime += $executionTime;
                
                $io->progressAdvance();
            }
        }

        $io->progressFinish();

        $avgTime = $totalTime / ($iterations * count($searchQueries));
        $io->success(sprintf(
            'Поиск: %d запросов, среднее время: %.4fs',
            $iterations * count($searchQueries),
            $avgTime
        ));
    }

    private function testRecommendationsPerformance(SymfonyStyle $io, int $iterations): void
    {
        $io->section('Тестирование производительности рекомендаций');

        // Получаем несколько видео для тестирования
        $testVideos = $this->videoRepository->findBy(['status' => 'published'], null, 5);
        
        if (empty($testVideos)) {
            $io->warning('Нет опубликованных видео для тестирования рекомендаций');
            return;
        }

        $totalTime = 0;
        $io->progressStart($iterations * count($testVideos));

        for ($i = 0; $i < $iterations; $i++) {
            foreach ($testVideos as $video) {
                $startTime = microtime(true);
                
                $recommendations = $this->performanceMonitor->trackQuery(
                    'get_related_videos',
                    fn() => $this->recommendationService->getRelatedVideos($video, 12)
                );
                
                $executionTime = microtime(true) - $startTime;
                $totalTime += $executionTime;
                
                $io->progressAdvance();
            }
        }

        $io->progressFinish();

        $avgTime = $totalTime / ($iterations * count($testVideos));
        $io->success(sprintf(
            'Рекомендации: %d запросов, среднее время: %.4fs',
            $iterations * count($testVideos),
            $avgTime
        ));
    }

    private function testQueriesPerformance(SymfonyStyle $io, int $iterations): void
    {
        $io->section('Тестирование производительности запросов');

        $queries = [
            'findPublished' => fn() => $this->videoRepository->findPublished(24, 0),
            'findPopular' => fn() => $this->videoRepository->findPopularPaginated(24, 0),
            'findFeatured' => fn() => $this->videoRepository->findFeaturedForHome(10),
            'countPublished' => fn() => $this->videoRepository->countPublished(),
        ];

        $results = [];
        $io->progressStart($iterations * count($queries));

        foreach ($queries as $queryName => $queryCallback) {
            $totalTime = 0;
            
            for ($i = 0; $i < $iterations; $i++) {
                $startTime = microtime(true);
                
                $this->performanceMonitor->trackQuery(
                    $queryName,
                    $queryCallback
                );
                
                $executionTime = microtime(true) - $startTime;
                $totalTime += $executionTime;
                
                $io->progressAdvance();
            }
            
            $results[$queryName] = $totalTime / $iterations;
        }

        $io->progressFinish();

        $io->table(
            ['Запрос', 'Среднее время (s)', 'Итераций'],
            array_map(fn($query, $time) => [
                $query,
                number_format($time, 4),
                $iterations
            ], array_keys($results), $results)
        );
    }

    private function runAllTests(SymfonyStyle $io, int $iterations): void
    {
        $this->testQueriesPerformance($io, $iterations);
        $this->testSearchPerformance($io, $iterations);
        $this->testRecommendationsPerformance($io, $iterations);
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}