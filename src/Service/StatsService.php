<?php

namespace App\Service;

use App\Entity\Video;
use App\Repository\CommentRepository;
use App\Repository\ModelProfileRepository;
use App\Repository\UserRepository;
use App\Repository\VideoRepository;
use App\Repository\CategoryRepository;
use App\Repository\TagRepository;
use Doctrine\ORM\EntityManagerInterface;

class StatsService
{
    public function __construct(
        private EntityManagerInterface $em,
        private VideoRepository $videoRepository,
        private UserRepository $userRepository,
        private CommentRepository $commentRepository,
        private ModelProfileRepository $modelRepository,
        private CategoryRepository $categoryRepository,
        private TagRepository $tagRepository,
    ) {
    }

    public function getDashboardStats(): array
    {
        $today = new \DateTime('today');
        
        return [
            // Основные счётчики
            'total_videos' => $this->videoRepository->count([]),
            'total_users' => $this->userRepository->count([]),
            'total_comments' => $this->commentRepository->count([]),
            'total_views' => $this->getTotalViews(),
            'total_models' => $this->modelRepository->count([]),
            'total_categories' => $this->categoryRepository->count([]),
            'total_tags' => $this->tagRepository->count([]),
            
            // Видео по статусам
            'videos_published' => $this->videoRepository->count(['status' => Video::STATUS_PUBLISHED]),
            'videos_processing' => $this->videoRepository->count(['status' => Video::STATUS_PROCESSING]),
            'videos_draft' => $this->videoRepository->count(['status' => Video::STATUS_DRAFT]),
            'videos_private' => $this->videoRepository->count(['status' => Video::STATUS_PRIVATE]),
            
            // Добавлено сегодня
            'videos_today' => $this->getCountCreatedToday('App\Entity\Video'),
            'users_today' => $this->getCountCreatedToday('App\Entity\User'),
            'comments_today' => $this->getCountCreatedToday('App\Entity\Comment'),
            'views_today' => $this->getViewsToday(),
            
            // Лайки/дизлайки
            'total_likes' => $this->getTotalLikes(),
            'total_dislikes' => $this->getTotalDislikes(),
        ];
    }

    private function getTotalViews(): int
    {
        $result = $this->em->createQuery(
            'SELECT SUM(v.viewsCount) FROM App\Entity\Video v'
        )->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    private function getTotalLikes(): int
    {
        $result = $this->em->createQuery(
            'SELECT SUM(v.likesCount) FROM App\Entity\Video v'
        )->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    private function getTotalDislikes(): int
    {
        $result = $this->em->createQuery(
            'SELECT SUM(v.dislikesCount) FROM App\Entity\Video v'
        )->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    private function getCountCreatedToday(string $entityClass): int
    {
        $today = new \DateTime('today');
        
        return (int) $this->em->createQuery(
            "SELECT COUNT(e) FROM {$entityClass} e WHERE e.createdAt >= :today"
        )->setParameter('today', $today)->getSingleScalarResult();
    }

    private function getViewsToday(): int
    {
        // Если есть отдельная таблица просмотров с датами — используем её
        // Иначе возвращаем 0 (нет возможности отследить просмотры за сегодня)
        return 0;
    }
}
