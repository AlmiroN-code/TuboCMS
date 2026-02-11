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
            
            // Очередь обработки
            'queue_stats' => $this->getQueueStats(),
            
            // Последние ошибки
            'recent_errors' => $this->getRecentErrors(),
            
            // Активность пользователей
            'online_users' => $this->getOnlineUsersCount(),
        ];
    }

    /**
     * Получить статистику очереди обработки
     */
    public function getQueueStats(): array
    {
        // Подсчитываем сообщения в очереди через прямой SQL запрос
        try {
            $conn = $this->em->getConnection();
            $sql = 'SELECT COUNT(*) as total FROM messenger_messages WHERE delivered_at IS NULL';
            $result = $conn->executeQuery($sql);
            $total = (int) $result->fetchOne();
        } catch (\Exception $e) {
            $total = 0;
        }
        
        return [
            'total' => $total,
            'processing' => $this->videoRepository->count(['status' => Video::STATUS_PROCESSING]),
            'draft' => $this->videoRepository->count(['status' => Video::STATUS_DRAFT]),
        ];
    }

    /**
     * Получить последние ошибки обработки видео
     */
    public function getRecentErrors(): array
    {
        // Ищем видео со статусом rejected или с ошибками обработки
        return $this->videoRepository->createQueryBuilder('v')
            ->where('v.status = :status OR v.processingStatus = :error_status')
            ->setParameter('status', Video::STATUS_REJECTED)
            ->setParameter('error_status', 'error')
            ->orderBy('v.updatedAt', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();
    }

    /**
     * Получить количество онлайн пользователей (активны за последние 15 минут)
     * Примечание: требует поле lastActivityAt в User, пока возвращаем 0
     */
    public function getOnlineUsersCount(): int
    {
        // TODO: Добавить поле lastActivityAt в User для отслеживания активности
        return 0;
    }

    /**
     * Получить последние действия пользователей
     * Показываем последних зарегистрированных пользователей
     */
    public function getRecentUserActivity(): array
    {
        return $this->userRepository->createQueryBuilder('u')
            ->orderBy('u.createdAt', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
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
