<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\MaterializedViewService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:refresh-materialized-views',
    description: 'Обновить все материализованные представления для аналитики'
)]
class RefreshMaterializedViewsCommand extends Command
{
    public function __construct(
        private readonly MaterializedViewService $materializedViewService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('create', 'c', InputOption::VALUE_NONE, 'Создать представления если они не существуют')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Принудительное обновление даже если заблокировано')
            ->setHelp('Эта команда обновляет материализованные представления для быстрой аналитики');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Обновление материализованных представлений');

        try {
            if ($input->getOption('create')) {
                $io->section('Создание представлений');
                $this->materializedViewService->createAllViews();
                $io->success('Все представления созданы');
            }

            $io->section('Обновление представлений');
            $startTime = microtime(true);
            
            $this->materializedViewService->refreshAllViews();
            
            $duration = round(microtime(true) - $startTime, 2);
            
            $io->success("Все представления обновлены за {$duration} секунд");
            
            // Показываем статистику
            $this->showStats($io);
            
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Ошибка при обновлении представлений: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function showStats(SymfonyStyle $io): void
    {
        $io->section('Статистика');
        
        try {
            $dailyStats = $this->materializedViewService->getDailyStats(7);
            $topChannels = $this->materializedViewService->getTopChannels(5);
            
            if (!empty($dailyStats)) {
                $io->text('Статистика за последние 7 дней:');
                foreach ($dailyStats as $stat) {
                    $io->text(sprintf(
                        '%s: %d видео, %d просмотров',
                        $stat['date'],
                        $stat['total_videos'],
                        $stat['total_views']
                    ));
                }
            }
            
            if (!empty($topChannels)) {
                $io->text('Топ 5 каналов:');
                foreach ($topChannels as $channel) {
                    $io->text(sprintf(
                        '%s: %d видео, %d просмотров',
                        $channel['channel_name'],
                        $channel['videos_count'],
                        $channel['total_views']
                    ));
                }
            }
        } catch (\Throwable $e) {
            $io->warning('Не удалось получить статистику: ' . $e->getMessage());
        }
    }
}