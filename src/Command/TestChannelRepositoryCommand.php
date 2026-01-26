<?php

namespace App\Command;

use App\Repository\ChannelRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-channel-repository',
    description: 'Тестирует методы репозитория каналов'
)]
class TestChannelRepositoryCommand extends Command
{
    public function __construct(
        private ChannelRepository $channelRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Тестирование репозитория каналов');

        // Тест 1: Все каналы
        $allChannels = $this->channelRepository->findAll();
        $io->info("Всего каналов в базе: " . count($allChannels));

        // Тест 2: Активные каналы
        $activeChannels = $this->channelRepository->findActive(10, 0);
        $io->info("Активных каналов: " . count($activeChannels));

        // Тест 3: Каналы с фильтрами (пустые фильтры)
        $filters = [
            'search' => null,
            'type' => null,
            'verified' => false,
            'premium' => false,
            'sort' => 'popular'
        ];
        
        $filteredChannels = $this->channelRepository->findWithFilters($filters, 10, 0);
        $io->info("Каналов с пустыми фильтрами: " . count($filteredChannels));
        
        $totalWithFilters = $this->channelRepository->countWithFilters($filters);
        $io->info("Общее количество с фильтрами: " . $totalWithFilters);

        // Показать детали каналов
        foreach ($filteredChannels as $channel) {
            $io->text("- {$channel->getName()} (slug: {$channel->getSlug()}, active: " . ($channel->isActive() ? 'да' : 'нет') . ")");
        }

        return Command::SUCCESS;
    }
}