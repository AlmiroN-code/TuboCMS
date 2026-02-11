<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Video;
use App\Entity\VideoChapter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VideoChapter>
 */
class VideoChapterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VideoChapter::class);
    }

    /**
     * Получить все главы видео, отсортированные по времени
     * 
     * @return VideoChapter[]
     */
    public function findByVideoOrdered(Video $video): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.video = :video')
            ->setParameter('video', $video)
            ->orderBy('c.timestamp', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
