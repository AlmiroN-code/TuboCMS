<?php

namespace App\Command;

use App\Message\ProcessVideoEncodingMessage;
use App\Repository\VideoRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:video:process-pending',
    description: 'Process pending videos for encoding',
)]
class ProcessPendingVideosCommand extends Command
{
    public function __construct(
        private VideoRepository $videoRepository,
        private MessageBusInterface $messageBus
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Находим видео, которые ожидают обработки
        $pendingVideos = $this->videoRepository->createQueryBuilder('v')
            ->where('v.processingStatus = :status')
            ->andWhere('v.tempVideoFile IS NOT NULL')
            ->setParameter('status', 'pending')
            ->setMaxResults(10) // Обрабатываем максимум 10 видео за раз
            ->getQuery()
            ->getResult();

        if (empty($pendingVideos)) {
            $io->success('No pending videos found');
            return Command::SUCCESS;
        }

        $io->info(sprintf('Found %d pending videos', count($pendingVideos)));

        foreach ($pendingVideos as $video) {
            try {
                $io->text(sprintf('Dispatching encoding for video: %s (ID: %d)', $video->getTitle(), $video->getId()));
                
                // Отправляем сообщение в очередь
                $this->messageBus->dispatch(new ProcessVideoEncodingMessage($video->getId()));
                
                $io->success(sprintf('✓ Dispatched: %s', $video->getTitle()));
            } catch (\Exception $e) {
                $io->error(sprintf('✗ Failed to dispatch video %d: %s', $video->getId(), $e->getMessage()));
            }
        }

        $io->success(sprintf('Dispatched %d videos for encoding', count($pendingVideos)));

        return Command::SUCCESS;
    }
}