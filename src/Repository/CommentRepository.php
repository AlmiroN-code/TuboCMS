<?php

namespace App\Repository;

use App\Entity\Comment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Comment>
 */
class CommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Comment::class);
    }

    public function findByVideoWithReplies(int $videoId, int $limit = 20, int $offset = 0): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.user', 'u')
            ->leftJoin('c.replies', 'r')
            ->leftJoin('r.user', 'ru')
            ->addSelect('u', 'r', 'ru')
            ->where('c.video = :videoId')
            ->andWhere('c.parent IS NULL')
            ->setParameter('videoId', $videoId)
            ->orderBy('c.isPinned', 'DESC')
            ->addOrderBy('c.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function countByVideo(int $videoId): int
    {
        return $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.video = :videoId')
            ->setParameter('videoId', $videoId)
            ->getQuery()
            ->useQueryCache(true)
            ->setResultCacheLifetime(300)
            ->getSingleScalarResult();
    }

    public function findRecentByUser(int $userId, int $limit = 10): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.video', 'v')
            ->addSelect('v')
            ->where('c.user = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findForModeration(int $limit = 50, int $offset = 0): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.user', 'u')
            ->leftJoin('c.video', 'v')
            ->addSelect('u', 'v')
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }
}
