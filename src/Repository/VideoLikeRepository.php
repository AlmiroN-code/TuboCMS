<?php

namespace App\Repository;

use App\Entity\VideoLike;
use App\Entity\User;
use App\Entity\Video;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VideoLike>
 */
class VideoLikeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VideoLike::class);
    }

    public function findByUserAndVideo(User $user, Video $video): ?VideoLike
    {
        return $this->findOneBy(['user' => $user, 'video' => $video]);
    }

    public function countLikes(Video $video): int
    {
        return $this->count(['video' => $video, 'isLike' => true]);
    }

    public function countDislikes(Video $video): int
    {
        return $this->count(['video' => $video, 'isLike' => false]);
    }

    /**
     * @return VideoLike[]
     */
    public function findLikedByUser(User $user, int $limit = 20, int $offset = 0): array
    {
        return $this->createQueryBuilder('vl')
            ->leftJoin('vl.video', 'v')
            ->addSelect('v')
            ->where('vl.user = :user')
            ->andWhere('vl.isLike = :isLike')
            ->setParameter('user', $user)
            ->setParameter('isLike', true)
            ->orderBy('vl.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function deleteByUserAndVideo(User $user, Video $video): int
    {
        return $this->createQueryBuilder('vl')
            ->delete()
            ->where('vl.user = :user')
            ->andWhere('vl.video = :video')
            ->setParameter('user', $user)
            ->setParameter('video', $video)
            ->getQuery()
            ->execute();
    }
}
