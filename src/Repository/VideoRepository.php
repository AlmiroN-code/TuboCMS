<?php

namespace App\Repository;

use App\Entity\Video;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Video>
 */
class VideoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Video::class);
    }

    public function findPublished(int $limit = 20, int $offset = 0): array
    {
        return $this->createQueryBuilder('v')
            ->leftJoin('v.createdBy', 'u')
            ->leftJoin('v.category', 'c')
            ->leftJoin('v.performers', 'p')
            ->addSelect('u', 'c', 'p')
            ->where('v.status = :status')
            ->setParameter('status', Video::STATUS_PUBLISHED)
            ->orderBy('v.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function countPublished(): int
    {
        return $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->where('v.status = :status')
            ->setParameter('status', Video::STATUS_PUBLISHED)
            ->getQuery()
            ->useQueryCache(true)
            ->setResultCacheLifetime(300) // 5 минут
            ->getSingleScalarResult();
    }

    public function findFeatured(int $limit = 10): array
    {
        return $this->createQueryBuilder('v')
            ->leftJoin('v.createdBy', 'u')
            ->addSelect('u')
            ->leftJoin('v.category', 'c')
            ->addSelect('c')
            ->leftJoin('v.performers', 'p')
            ->addSelect('p')
            ->where('v.status = :status')
            ->andWhere('v.isFeatured = :featured')
            ->setParameter('status', Video::STATUS_PUBLISHED)
            ->setParameter('featured', true)
            ->orderBy('v.viewsCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->useQueryCache(true)
            ->setResultCacheLifetime(300) // 5 минут
            ->getResult();
    }

    public function findByCategory(int $categoryId, int $limit = 20, int $offset = 0): array
    {
        return $this->createQueryBuilder('v')
            ->leftJoin('v.createdBy', 'u')
            ->leftJoin('v.category', 'c')
            ->leftJoin('v.performers', 'p')
            ->addSelect('u', 'c', 'p')
            ->where('v.status = :status')
            ->andWhere('v.category = :category')
            ->setParameter('status', Video::STATUS_PUBLISHED)
            ->setParameter('category', $categoryId)
            ->orderBy('v.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function countByCategory(int $categoryId): int
    {
        return $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->where('v.status = :status')
            ->andWhere('v.category = :category')
            ->setParameter('status', Video::STATUS_PUBLISHED)
            ->setParameter('category', $categoryId)
            ->getQuery()
            ->useQueryCache(true)
            ->setResultCacheLifetime(300)
            ->getSingleScalarResult();
    }

    public function findMostViewed(int $limit = 20): array
    {
        return $this->createQueryBuilder('v')
            ->leftJoin('v.createdBy', 'u')
            ->leftJoin('v.category', 'c')
            ->leftJoin('v.performers', 'p')
            ->addSelect('u', 'c', 'p')
            ->where('v.status = :status')
            ->setParameter('status', Video::STATUS_PUBLISHED)
            ->orderBy('v.viewsCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->useQueryCache(true)
            ->setResultCacheLifetime(300)
            ->getResult();
    }

    public function findPopular(int $limit = 12): array
    {
        return $this->createQueryBuilder('v')
            ->leftJoin('v.createdBy', 'u')
            ->leftJoin('v.category', 'c')
            ->leftJoin('v.performers', 'p')
            ->addSelect('u', 'c', 'p')
            ->where('v.status = :status')
            ->setParameter('status', Video::STATUS_PUBLISHED)
            ->orderBy('v.viewsCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->useQueryCache(true)
            ->setResultCacheLifetime(300)
            ->getResult();
    }

    public function findPopularPaginated(int $limit = 24, int $offset = 0): array
    {
        return $this->createQueryBuilder('v')
            ->leftJoin('v.createdBy', 'u')
            ->leftJoin('v.category', 'c')
            ->leftJoin('v.performers', 'p')
            ->addSelect('u', 'c', 'p')
            ->where('v.status = :status')
            ->setParameter('status', Video::STATUS_PUBLISHED)
            ->orderBy('v.viewsCount', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function findTrending(int $limit = 24, int $offset = 0): array
    {
        // Тренды - видео с наибольшим количеством просмотров за последние 7 дней
        $weekAgo = new \DateTime('-7 days');
        
        return $this->createQueryBuilder('v')
            ->leftJoin('v.createdBy', 'u')
            ->leftJoin('v.category', 'c')
            ->leftJoin('v.performers', 'p')
            ->addSelect('u', 'c', 'p')
            ->where('v.status = :status')
            ->andWhere('v.createdAt >= :weekAgo')
            ->setParameter('status', Video::STATUS_PUBLISHED)
            ->setParameter('weekAgo', $weekAgo)
            ->orderBy('v.viewsCount', 'DESC')
            ->addOrderBy('v.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function findRecent(int $limit = 12): array
    {
        return $this->createQueryBuilder('v')
            ->leftJoin('v.createdBy', 'u')
            ->leftJoin('v.category', 'c')
            ->leftJoin('v.performers', 'p')
            ->addSelect('u', 'c', 'p')
            ->where('v.status = :status')
            ->setParameter('status', Video::STATUS_PUBLISHED)
            ->orderBy('v.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->useQueryCache(true)
            ->setResultCacheLifetime(300)
            ->getResult();
    }

    public function searchVideos(string $query, int $limit = 20, int $offset = 0): array
    {
        // Очищаем поисковый запрос от потенциально опасных символов
        $cleanQuery = $this->sanitizeSearchQuery($query);
        
        return $this->createQueryBuilder('v')
            ->leftJoin('v.createdBy', 'u')
            ->addSelect('u')
            ->leftJoin('v.category', 'c')
            ->addSelect('c')
            ->leftJoin('v.performers', 'p')
            ->addSelect('p')
            ->where('v.status = :status')
            ->andWhere('(v.title LIKE :query OR v.description LIKE :query)')
            ->setParameter('status', Video::STATUS_PUBLISHED)
            ->setParameter('query', '%' . $cleanQuery . '%')
            ->orderBy('v.viewsCount', 'DESC')
            ->addOrderBy('v.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    private function sanitizeSearchQuery(string $query): string
    {
        // Удаляем потенциально опасные символы
        $query = strip_tags($query);
        $query = preg_replace('/[^\p{L}\p{N}\s\-_]/u', '', $query);
        return trim($query);
    }

    public function getSearchCount(string $query): int
    {
        $cleanQuery = $this->sanitizeSearchQuery($query);
        
        return $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->where('v.status = :status')
            ->andWhere('(v.title LIKE :query OR v.description LIKE :query)')
            ->setParameter('status', Video::STATUS_PUBLISHED)
            ->setParameter('query', '%' . $cleanQuery . '%')
            ->getQuery()
            ->useQueryCache(true)
            ->setResultCacheLifetime(300)
            ->getSingleScalarResult();
    }

    public function findForAdminList(int $limit = 20, int $offset = 0): array
    {
        return $this->createQueryBuilder('v')
            ->leftJoin('v.createdBy', 'u')
            ->leftJoin('v.category', 'c')
            ->leftJoin('v.performers', 'p')
            ->addSelect('u', 'c', 'p')
            ->orderBy('v.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }
}
