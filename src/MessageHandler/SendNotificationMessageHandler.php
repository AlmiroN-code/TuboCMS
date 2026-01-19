<?php

namespace App\MessageHandler;

use App\Entity\Notification;
use App\Message\SendNotificationMessage;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for sending notifications asynchronously.
 * 
 * @see Requirements 4.3 - Send notifications to subscribers
 */
#[AsMessageHandler]
class SendNotificationMessageHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(SendNotificationMessage $message): void
    {
        $user = $this->userRepository->find($message->getUserId());
        
        if ($user === null) {
            $this->logger->warning('User not found for notification', [
                'userId' => $message->getUserId(),
            ]);
            return;
        }

        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType($message->getType());
        $notification->setData($message->getData());

        $this->em->persist($notification);
        $this->em->flush();

        $this->logger->info('Notification created', [
            'userId' => $user->getId(),
            'type' => $message->getType(),
        ]);
    }
}
