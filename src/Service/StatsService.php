<?php

namespace App\Service;

use App\Repository\CommentRepository;
use App\Repository\UserRepository;
use App\Repository\VideoRepository;
use Doctrine\ORM\EntityManagerInterface;

class StatsService
{
    public function __construct(
        private EntityManagerInterface $em,
        private VideoRepository $videoRepository,
        private UserRepository $userRepository,
        private CommentRepository $commentRepository
    ) {
    }

    public function getDashboardStats(): array
    {
        return [
            'videos' => $this->videoRepository->count([]),
            'users' => $this->userRepository->count([]),
            'comments' => $this->commentRepository->count([]),
            'views' => $this->getTotalViews(),
        ];
    }

    private function getTotalViews(): int
    {
        $result = $this->em->createQuery(
            'SELECT SUM(v.viewsCount) FROM App\Entity\Video v'
        )->getSingleScalarResult();

        return (int) ($result ?? 0);
    }
}
