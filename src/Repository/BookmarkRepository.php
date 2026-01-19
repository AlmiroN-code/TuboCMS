<?php

namespace App\Repository;

use App\Entity\Bookmark;
use App\Entity\User;
use App\Entity\Video;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Bookmark>
 */
class BookmarkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Bookmark::class);
    }

    public function findByUserAndVideo(User $user, Video $video): ?Bookmark
    {
        return $this->findOneBy(['user' => $user, 'video' => $video]);
    }

    /**
     * @return Bookmark[]
     */
    public function findByUser(User $user, int $limit = 20, int $offset = 0): array
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.video', 'v')
            ->addSelect('v')
            ->where('b.user = :user')
            ->setParameter('user', $user)
            ->orderBy('b.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function countByUser(User $user): int
    {
        return $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->where('b.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function isBookmarked(User $user, Video $video): bool
    {
        return $this->findByUserAndVideo($user, $video) !== null;
    }

    public function deleteByUserAndVideo(User $user, Video $video): int
    {
        return $this->createQueryBuilder('b')
            ->delete()
            ->where('b.user = :user')
            ->andWhere('b.video = :video')
            ->setParameter('user', $user)
            ->setParameter('video', $video)
            ->getQuery()
            ->execute();
    }
}
