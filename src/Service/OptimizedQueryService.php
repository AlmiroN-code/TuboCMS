<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\VideoRepository;
use App\Repository\ChannelRepository;
use App\Repository\CommentRepository;
use App\Service\CircuitBreaker\CircuitBreakerFactory;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Сервис для оптимизированных запросов с Eager Loading и Circuit Breaker
 */
class OptimizedQueryService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly VideoRepository $videoRepository,
        private readonly ChannelRepository $channelRepository,
        private readonly CommentRepository $commentRepository,
        private readonly CircuitBreakerFactory $circuitBreakerFactory,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Оптимизированное получение видео с полной загрузкой связей
     */
    public function getVideoWithAllRelations(string $slug): ?array
    {
        $circuitBreaker = $this->circuitBreakerFactory->create('video_relations', 3, 30);

        return $circuitBreaker->call(
            operation: function () use ($slug) {
                // Основное видео с базовыми связями
                $video = $this->em->createQueryBuilder()
                    ->select('v', 'u', 'c', 't', 'p', 'ch', 'ef', 's', 'ep')
                    ->from('App\Entity\Video', 'v')
                    ->leftJoin('v.createdBy', 'u')
                    ->leftJoin('v.categories', 'c')
                    ->leftJoin('v.tags', 't')
                    ->leftJoin('v.performers', 'p')
                    ->leftJoin('v.channel', 'ch')
                    ->leftJoin('v.encodedFiles', 'ef')
                    ->leftJoin('ef.storage', 's')
                    ->leftJoin('ef.profile', 'ep')
                    ->where('v.slug = :slug')
                    ->andWhere('v.status = :status')
                    ->setParameter('slug', $slug)
                    ->setParameter('status', 'published')
                    ->getQuery()
                    ->useQueryCache(true)
                    ->setResultCacheLifetime(300)
                    ->getOneOrNullResult();

                if (!$video) {
                    return null;
                }

                // Комментарии с пользователями одним запросом
                $comments = $this->em->createQueryBuilder()
                    ->select('c', 'cu')
                    ->from('App\Entity\Comment', 'c')
                    ->leftJoin('c.user', 'cu')
                    ->where('c.video = :video')
                    ->andWhere('c.status = :status')
                    ->setParameter('video', $video)
                    ->setParameter('status', 'approved')
                    ->orderBy('c.createdAt', 'DESC')
                    ->setMaxResults(20)
                    ->getQuery()
                    ->useQueryCache(true)
                    ->setResultCacheLifetime(120)
                    ->getResult();

                // Похожие видео одним запросом
                $relatedVideos = $this->getRelatedVideosOptimized($video, 6);

                return [
                    'video' => $video,
                    'comments' => $comments,
                    'related_videos' => $relatedVideos
                ];
            },
            fallback: function (\Throwable $e) use ($slug) {
                $this->logger->error('Failed to load video with relations', [
                    'slug' => $slug,
                    'error' => $e->getMessage()
                ]);
                return null;
            }
        );
    }

    /**
     * Оптимизированное получение похожих видео
     */
    private function getRelatedVideosOptimized($video, int $limit): array
    {
        // Получаем ID тегов и категорий
        $tagIds = $video->getTags()->map(fn($tag) => $tag->getId())->toArray();
        $categoryIds = $video->getCategories()->map(fn($cat) => $cat->getId())->toArray();

        $qb = $this->em->createQueryBuilder()
            ->select('v', 'u', 'c', 'ch')
            ->from('App\Entity\Video', 'v')
            ->leftJoin('v.createdBy', 'u')
            ->leftJoin('v.categories', 'c')
            ->leftJoin('v.channel', 'ch')
            ->where('v.status = :status')
            ->andWhere('v.id != :excludeId')
            ->setParameter('status', 'published')
            ->setParameter('excludeId', $video->getId())
            ->setMaxResults($limit);

        // Приоритет: сначала по тегам, потом по категориям
        if (!empty($tagIds)) {
            $qb->leftJoin('v.tags', 't')
               ->andWhere('t.id IN (:tagIds)')
               ->setParameter('tagIds', $tagIds)
               ->orderBy('v.viewsCount', 'DESC');
        } elseif (!empty($categoryIds)) {
            $qb->andWhere('c.id IN (:categoryIds)')
               ->setParameter('categoryIds', $categoryIds)
               ->orderBy('v.viewsCount', 'DESC');
        } else {
            $qb->orderBy('v.viewsCount', 'DESC');
        }

        return $qb->getQuery()
            ->useQueryCache(true)
            ->setResultCacheLifetime(300)
            ->getResult();
    }

    /**
     * Оптимизированное получение данных для главной страницы
     */
    public function getHomePageData(): array
    {
        $circuitBreaker = $this->circuitBreakerFactory->create('homepage_data', 5, 60);

        return $circuitBreaker->call(
            operation: function () {
                // Все запросы выполняем параллельно через один EntityManager
                $results = [];

                // Рекомендуемые видео
                $results['featured'] = $this->em->createQueryBuilder()
                    ->select('v', 'u', 'c', 'ch')
                    ->from('App\Entity\Video', 'v')
                    ->leftJoin('v.createdBy', 'u')
                    ->leftJoin('v.categories', 'c')
                    ->leftJoin('v.channel', 'ch')
                    ->where('v.status = :status')
                    ->andWhere('v.isFeatured = :featured')
                    ->setParameter('status', 'published')
                    ->setParameter('featured', true)
                    ->orderBy('v.viewsCount', 'DESC')
                    ->setMaxResults(10)
                    ->getQuery()
                    ->useQueryCache(true)
                    ->setResultCacheLifetime(300)
                    ->getResult();

                // Новые видео
                $results['recent'] = $this->em->createQueryBuilder()
                    ->select('v', 'u', 'c', 'ch')
                    ->from('App\Entity\Video', 'v')
                    ->leftJoin('v.createdBy', 'u')
                    ->leftJoin('v.categories', 'c')
                    ->leftJoin('v.channel', 'ch')
                    ->where('v.status = :status')
                    ->setParameter('status', 'published')
                    ->orderBy('v.createdAt', 'DESC')
                    ->setMaxResults(12)
                    ->getQuery()
                    ->useQueryCache(true)
                    ->setResultCacheLifetime(120)
                    ->getResult();

                // Популярные видео
                $results['popular'] = $this->em->createQueryBuilder()
                    ->select('v', 'u', 'c', 'ch')
                    ->from('App\Entity\Video', 'v')
                    ->leftJoin('v.createdBy', 'u')
                    ->leftJoin('v.categories', 'c')
                    ->leftJoin('v.channel', 'ch')
                    ->where('v.status = :status')
                    ->setParameter('status', 'published')
                    ->orderBy('v.viewsCount', 'DESC')
                    ->setMaxResults(12)
                    ->getQuery()
                    ->useQueryCache(true)
                    ->setResultCacheLifetime(300)
                    ->getResult();

                // Популярные каналы
                $results['channels'] = $this->em->createQueryBuilder()
                    ->select('ch', 'o')
                    ->from('App\Entity\Channel', 'ch')
                    ->leftJoin('ch.owner', 'o')
                    ->where('ch.isActive = :active')
                    ->setParameter('active', true)
                    ->orderBy('ch.subscribersCount', 'DESC')
                    ->setMaxResults(8)
                    ->getQuery()
                    ->useQueryCache(true)
                    ->setResultCacheLifetime(600)
                    ->getResult();

                return $results;
            },
            fallback: function (\Throwable $e) {
                $this->logger->error('Failed to load homepage data', [
                    'error' => $e->getMessage()
                ]);
                return [
                    'featured' => [],
                    'recent' => [],
                    'popular' => [],
                    'channels' => []
                ];
            }
        );
    }

    /**
     * Оптимизированный поиск с предзагрузкой
     */
    public function searchVideosOptimized(string $query, array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $circuitBreaker = $this->circuitBreakerFactory->create('video_search', 5, 30);

        return $circuitBreaker->call(
            operation: function () use ($query, $filters, $limit, $offset) {
                $cleanQuery = $this->sanitizeSearchQuery($query);
                
                if (empty($cleanQuery)) {
                    return ['videos' => [], 'total' => 0];
                }

                // Основной поиск с JOIN всех связей
                $qb = $this->em->createQueryBuilder()
                    ->select('v', 'u', 'c', 'ch', 't')
                    ->from('App\Entity\Video', 'v')
                    ->leftJoin('v.createdBy', 'u')
                    ->leftJoin('v.categories', 'c')
                    ->leftJoin('v.channel', 'ch')
                    ->leftJoin('v.tags', 't')
                    ->where('v.status = :status')
                    ->andWhere('(v.title LIKE :query OR v.description LIKE :query OR t.name LIKE :query OR c.name LIKE :query)')
                    ->setParameter('status', 'published')
                    ->setParameter('query', '%' . $cleanQuery . '%');

                // Применяем фильтры
                if (!empty($filters['category'])) {
                    $qb->andWhere('c.id = :categoryId')
                       ->setParameter('categoryId', $filters['category']);
                }

                if (!empty($filters['duration'])) {
                    switch ($filters['duration']) {
                        case 'short':
                            $qb->andWhere('v.duration < 300');
                            break;
                        case 'medium':
                            $qb->andWhere('v.duration >= 300 AND v.duration <= 1200');
                            break;
                        case 'long':
                            $qb->andWhere('v.duration > 1200');
                            break;
                    }
                }

                // Подсчет общего количества
                $countQb = clone $qb;
                $total = $countQb->select('COUNT(DISTINCT v.id)')
                    ->getQuery()
                    ->getSingleScalarResult();

                // Получение результатов с пагинацией
                $videos = $qb->orderBy('v.viewsCount', 'DESC')
                    ->addOrderBy('v.createdAt', 'DESC')
                    ->setMaxResults($limit)
                    ->setFirstResult($offset)
                    ->getQuery()
                    ->useQueryCache(true)
                    ->setResultCacheLifetime(120)
                    ->getResult();

                return ['videos' => $videos, 'total' => $total];
            },
            fallback: function (\Throwable $e) use ($query) {
                $this->logger->error('Search failed', [
                    'query' => $query,
                    'error' => $e->getMessage()
                ]);
                return ['videos' => [], 'total' => 0];
            }
        );
    }

    /**
     * Batch операции для обновления статистики
     */
    public function updateVideoStatsBatch(array $videoIds, array $stats): void
    {
        $circuitBreaker = $this->circuitBreakerFactory->create('batch_stats_update', 3, 60);

        $circuitBreaker->call(
            operation: function () use ($videoIds, $stats) {
                $this->em->beginTransaction();
                
                try {
                    // Batch update views
                    if (!empty($stats['views'])) {
                        $this->em->createQuery(
                            'UPDATE App\Entity\Video v 
                             SET v.viewsCount = v.viewsCount + :increment 
                             WHERE v.id IN (:ids)'
                        )
                        ->setParameter('increment', $stats['views'])
                        ->setParameter('ids', $videoIds)
                        ->execute();
                    }

                    // Batch update impressions
                    if (!empty($stats['impressions'])) {
                        $this->em->createQuery(
                            'UPDATE App\Entity\Video v 
                             SET v.impressionsCount = v.impressionsCount + :increment 
                             WHERE v.id IN (:ids)'
                        )
                        ->setParameter('increment', $stats['impressions'])
                        ->setParameter('ids', $videoIds)
                        ->execute();
                    }

                    $this->em->commit();
                    
                    $this->logger->info('Batch stats update completed', [
                        'video_count' => count($videoIds),
                        'stats' => $stats
                    ]);
                } catch (\Throwable $e) {
                    $this->em->rollback();
                    throw $e;
                }
            },
            fallback: function (\Throwable $e) use ($videoIds, $stats) {
                $this->logger->error('Batch stats update failed', [
                    'video_ids' => $videoIds,
                    'stats' => $stats,
                    'error' => $e->getMessage()
                ]);
            }
        );
    }

    /**
     * Очистка поискового запроса
     */
    private function sanitizeSearchQuery(string $query): string
    {
        $query = strip_tags($query);
        $query = preg_replace('/[^\p{L}\p{N}\s\-_]/u', '', $query);
        return trim(substr($query, 0, 100));
    }

    /**
     * Простой тестовый метод для проверки работы сервиса
     */
    public function getPopularVideos(int $limit = 10): array
    {
        try {
            return $this->videoRepository->createQueryBuilder('v')
                ->select('v.id', 'v.title', 'v.slug', 'v.viewsCount')
                ->where('v.status = :status')
                ->setParameter('status', 'published')
                ->orderBy('v.viewsCount', 'DESC')
                ->setMaxResults($limit)
                ->getQuery()
                ->getArrayResult();
        } catch (\Exception $e) {
            $this->logger->error('Failed to get popular videos', ['error' => $e->getMessage()]);
            return [];
        }
    }
}