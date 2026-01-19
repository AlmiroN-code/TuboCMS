<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class UserStatsService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CacheInterface $cache
    ) {
    }

    public function getUserStats(User $user): array
    {
        $cacheKey = 'user_stats_' . $user->getId();
        
        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($user) {
            $item->expiresAfter(300); // 5 минут
            
            return [
                'videos_count' => $user->getVideosCount(),
                'subscribers_count' => $user->getSubscribersCount(),
                'total_views' => $user->getTotalViews(),
                'member_since' => $user->getCreatedAt(),
            ];
        });
    }

    public function getRecentVideos(User $user, int $limit = 6): array
    {
        if ($user->getVideosCount() === 0) {
            return [];
        }

        $cacheKey = 'user_recent_videos_' . $user->getId() . '_' . $limit;
        
        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($user, $limit) {
            $item->expiresAfter(600); // 10 минут
            
            return $this->entityManager
                ->getRepository(\App\Entity\Video::class)
                ->createQueryBuilder('v')
                ->select('v') // Явно выбираем все поля видео
                ->where('v.createdBy = :user')
                ->andWhere('v.status = :status')
                ->setParameter('user', $user)
                ->setParameter('status', \App\Entity\Video::STATUS_PUBLISHED)
                ->orderBy('v.createdAt', 'DESC')
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();
        });
    }

    public function invalidateUserCache(User $user): void
    {
        $this->cache->delete('user_stats_' . $user->getId());
        $this->cache->delete('user_recent_videos_' . $user->getId() . '_6');
    }
}