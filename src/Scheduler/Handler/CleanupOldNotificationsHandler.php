<?php

namespace App\Scheduler\Handler;

use App\Repository\NotificationRepository;
use App\Scheduler\Message\CleanupOldNotificationsMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class CleanupOldNotificationsHandler
{
    public function __construct(
        private NotificationRepository $notificationRepository,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(CleanupOldNotificationsMessage $message): void
    {
        $this->logger->info('Starting old notifications cleanup', ['maxAgeDays' => $message->maxAgeDays]);
        
        $threshold = new \DateTimeImmutable("-{$message->maxAgeDays} days");
        
        // Удаляем прочитанные уведомления старше порога
        $deleted = $this->notificationRepository->createQueryBuilder('n')
            ->delete()
            ->where('n.isRead = :isRead')
            ->andWhere('n.createdAt < :threshold')
            ->setParameter('isRead', true)
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->execute();
        
        $this->logger->info('Old notifications cleanup completed', [
            'deleted' => $deleted,
        ]);
    }
}
