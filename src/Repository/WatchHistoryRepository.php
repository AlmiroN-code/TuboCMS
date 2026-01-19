<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\Video;
use App\Entity\WatchHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WatchHistory>
 */
class WatchHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WatchHistory::class);
    }

    public function findByUserAndVideo(User $user, Video $video): ?WatchHistory
    {
        return $this->createQueryBuilder('wh')
            ->where('wh.user = :user')
            ->andWhere('wh.video = :video')
            ->setParameter('user', $user)
            ->setParameter('video', $video)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return WatchHistory[]
     */
    public function findByUser(User $user, int $limit = 20, int $offset = 0): array
    {
        return $this->createQueryBuilder('wh')
            ->leftJoin('wh.video', 'v')
            ->addSelect('v')
            ->where('wh.user = :user')
            ->setParameter('user', $user)
            ->orderBy('wh.watchedAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function countByUser(User $user): int
    {
        return $this->createQueryBuilder('wh')
            ->select('COUNT(wh.id)')
            ->where('wh.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function deleteByUser(User $user): int
    {
        return $this->createQueryBuilder('wh')
            ->delete()
            ->where('wh.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }

    public function deleteByUserAndVideo(User $user, Video $video): int
    {
        return $this->createQueryBuilder('wh')
            ->delete()
            ->where('wh.user = :user')
            ->andWhere('wh.video = :video')
            ->setParameter('user', $user)
            ->setParameter('video', $video)
            ->getQuery()
            ->execute();
    }
}
