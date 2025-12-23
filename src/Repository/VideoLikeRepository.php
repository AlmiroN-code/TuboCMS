<?php

namespace App\Repository;

use App\Entity\VideoLike;
use App\Entity\User;
use App\Entity\Video;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

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
        return $this->count(['video' => $video, 'type' => VideoLike::TYPE_LIKE]);
    }

    public function countDislikes(Video $video): int
    {
        return $this->count(['video' => $video, 'type' => VideoLike::TYPE_DISLIKE]);
    }
}
