<?php

namespace App\Scheduler\Handler;

use App\Entity\Video;
use App\Message\ProcessVideoEncodingMessage;
use App\Repository\VideoRepository;
use App\Scheduler\Message\CheckStuckVideosMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class CheckStuckVideosHandler
{
    public function __construct(
        private VideoRepository $videoRepository,
        private EntityManagerInterface $em,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(CheckStuckVideosMessage $message): void
    {
        $this->logger->info('Checking for stuck videos', ['thresholdMinutes' => $message->stuckThresholdMinutes]);
        
        $threshold = new \DateTimeImmutable("-{$message->stuckThresholdMinutes} minutes");
        
        // Находим видео, которые застряли в статусе processing
        $stuckVideos = $this->videoRepository->createQueryBuilder('v')
            ->where('v.status = :status')
            ->andWhere('v.processingStatus = :processingStatus')
            ->andWhere('v.updatedAt < :threshold')
            ->setParameter('status', Video::STATUS_PROCESSING)
            ->setParameter('processingStatus', 'processing')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getResult();
        
        $requeued = 0;
        
        foreach ($stuckVideos as $video) {
            $this->logger->warning('Found stuck video, requeuing', [
                'videoId' => $video->getId(),
                'title' => $video->getTitle(),
                'lastUpdate' => $video->getUpdatedAt()->format('Y-m-d H:i:s'),
            ]);
            
            // Сбрасываем статус и отправляем на переобработку
            $video->setProcessingStatus('pending');
            $video->setProcessingProgress(0);
            $video->setProcessingError('Автоматическая переобработка: видео застряло в обработке');
            $video->setUpdatedAt(new \DateTimeImmutable());
            
            // Отправляем на переобработку
            $this->messageBus->dispatch(new ProcessVideoEncodingMessage($video->getId()));
            $requeued++;
        }
        
        $this->em->flush();
        
        $this->logger->info('Stuck videos check completed', [
            'found' => count($stuckVideos),
            'requeued' => $requeued,
        ]);
    }
}
