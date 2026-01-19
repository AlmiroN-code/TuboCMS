<?php

namespace App\Scheduler\Handler;

use App\Repository\UserRepository;
use App\Repository\VideoRepository;
use App\Scheduler\Message\UpdateStatsMessage;
use App\Service\UserStatsService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class UpdateStatsHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private VideoRepository $videoRepository,
        private UserStatsService $userStatsService,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(UpdateStatsMessage $message): void
    {
        $this->logger->info('Starting stats update');
        
        // Обновляем статистику пользователей
        $users = $this->userRepository->findAll();
        $updatedUsers = 0;
        
        foreach ($users as $user) {
            try {
                $this->userStatsService->updateUserStats($user);
                $updatedUsers++;
            } catch (\Exception $e) {
                $this->logger->warning('Failed to update user stats', [
                    'userId' => $user->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        $this->em->flush();
        
        $this->logger->info('Stats update completed', [
            'updatedUsers' => $updatedUsers,
        ]);
    }
}
