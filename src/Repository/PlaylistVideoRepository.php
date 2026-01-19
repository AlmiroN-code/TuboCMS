<?php

namespace App\Repository;

use App\Entity\Playlist;
use App\Entity\PlaylistVideo;
use App\Entity\Video;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlaylistVideo>
 */
class PlaylistVideoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlaylistVideo::class);
    }

    public function findByPlaylistAndVideo(Playlist $playlist, Video $video): ?PlaylistVideo
    {
        return $this->createQueryBuilder('pv')
            ->where('pv.playlist = :playlist')
            ->andWhere('pv.video = :video')
            ->setParameter('playlist', $playlist)
            ->setParameter('video', $video)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return PlaylistVideo[]
     */
    public function findByPlaylist(Playlist $playlist): array
    {
        return $this->createQueryBuilder('pv')
            ->leftJoin('pv.video', 'v')
            ->addSelect('v')
            ->where('pv.playlist = :playlist')
            ->setParameter('playlist', $playlist)
            ->orderBy('pv.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getMaxPosition(Playlist $playlist): int
    {
        $result = $this->createQueryBuilder('pv')
            ->select('MAX(pv.position)')
            ->where('pv.playlist = :playlist')
            ->setParameter('playlist', $playlist)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?? -1;
    }
}
