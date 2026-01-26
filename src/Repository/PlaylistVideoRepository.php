<?php

namespace App\Repository;

use App\Entity\ChannelPlaylist;
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

    /**
     * Найти видео в плейлисте
     */
    public function findByPlaylist(ChannelPlaylist $playlist, int $limit = 50, int $offset = 0): array
    {
        return $this->createQueryBuilder('pv')
            ->join('pv.video', 'v')
            ->andWhere('pv.playlist = :playlist')
            ->andWhere('v.status = :status')
            ->setParameter('playlist', $playlist)
            ->setParameter('status', 'published')
            ->orderBy('pv.sortOrder', 'ASC')
            ->addOrderBy('pv.addedAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Найти связь видео с плейлистом
     */
    public function findByPlaylistAndVideo(ChannelPlaylist $playlist, Video $video): ?PlaylistVideo
    {
        return $this->createQueryBuilder('pv')
            ->andWhere('pv.playlist = :playlist')
            ->andWhere('pv.video = :video')
            ->setParameter('playlist', $playlist)
            ->setParameter('video', $video)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Проверить есть ли видео в плейлисте
     */
    public function isVideoInPlaylist(ChannelPlaylist $playlist, Video $video): bool
    {
        return $this->findByPlaylistAndVideo($playlist, $video) !== null;
    }

    /**
     * Подсчет видео в плейлисте
     */
    public function countByPlaylist(ChannelPlaylist $playlist): int
    {
        return $this->createQueryBuilder('pv')
            ->select('COUNT(pv.id)')
            ->join('pv.video', 'v')
            ->andWhere('pv.playlist = :playlist')
            ->andWhere('v.status = :status')
            ->setParameter('playlist', $playlist)
            ->setParameter('status', 'published')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Получить следующий порядок сортировки в плейлисте
     */
    public function getNextSortOrder(ChannelPlaylist $playlist): int
    {
        $result = $this->createQueryBuilder('pv')
            ->select('MAX(pv.sortOrder)')
            ->andWhere('pv.playlist = :playlist')
            ->setParameter('playlist', $playlist)
            ->getQuery()
            ->getSingleScalarResult();

        return ($result ?? 0) + 1;
    }

    /**
     * Переместить видео в плейлисте
     */
    public function moveVideo(PlaylistVideo $playlistVideo, int $newPosition): void
    {
        $playlist = $playlistVideo->getPlaylist();
        $currentPosition = $playlistVideo->getSortOrder();

        if ($newPosition === $currentPosition) {
            return;
        }

        $em = $this->getEntityManager();

        if ($newPosition > $currentPosition) {
            // Перемещение вниз - сдвигаем элементы вверх
            $em->createQueryBuilder()
                ->update(PlaylistVideo::class, 'pv')
                ->set('pv.sortOrder', 'pv.sortOrder - 1')
                ->where('pv.playlist = :playlist')
                ->andWhere('pv.sortOrder > :currentPos')
                ->andWhere('pv.sortOrder <= :newPos')
                ->setParameter('playlist', $playlist)
                ->setParameter('currentPos', $currentPosition)
                ->setParameter('newPos', $newPosition)
                ->getQuery()
                ->execute();
        } else {
            // Перемещение вверх - сдвигаем элементы вниз
            $em->createQueryBuilder()
                ->update(PlaylistVideo::class, 'pv')
                ->set('pv.sortOrder', 'pv.sortOrder + 1')
                ->where('pv.playlist = :playlist')
                ->andWhere('pv.sortOrder >= :newPos')
                ->andWhere('pv.sortOrder < :currentPos')
                ->setParameter('playlist', $playlist)
                ->setParameter('newPos', $newPosition)
                ->setParameter('currentPos', $currentPosition)
                ->getQuery()
                ->execute();
        }

        // Устанавливаем новую позицию для перемещаемого элемента
        $playlistVideo->setSortOrder($newPosition);
        $em->flush();
    }

    /**
     * Получить предыдущее видео в плейлисте
     */
    public function getPreviousVideo(PlaylistVideo $playlistVideo): ?PlaylistVideo
    {
        return $this->createQueryBuilder('pv')
            ->join('pv.video', 'v')
            ->andWhere('pv.playlist = :playlist')
            ->andWhere('pv.sortOrder < :sortOrder')
            ->andWhere('v.status = :status')
            ->setParameter('playlist', $playlistVideo->getPlaylist())
            ->setParameter('sortOrder', $playlistVideo->getSortOrder())
            ->setParameter('status', 'published')
            ->orderBy('pv.sortOrder', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Получить следующее видео в плейлисте
     */
    public function getNextVideo(PlaylistVideo $playlistVideo): ?PlaylistVideo
    {
        return $this->createQueryBuilder('pv')
            ->join('pv.video', 'v')
            ->andWhere('pv.playlist = :playlist')
            ->andWhere('pv.sortOrder > :sortOrder')
            ->andWhere('v.status = :status')
            ->setParameter('playlist', $playlistVideo->getPlaylist())
            ->setParameter('sortOrder', $playlistVideo->getSortOrder())
            ->setParameter('status', 'published')
            ->orderBy('pv.sortOrder', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Получить позицию видео в плейлисте
     */
    public function getVideoPosition(ChannelPlaylist $playlist, Video $video): ?int
    {
        $playlistVideo = $this->findByPlaylistAndVideo($playlist, $video);
        
        if (!$playlistVideo) {
            return null;
        }

        $position = $this->createQueryBuilder('pv')
            ->select('COUNT(pv.id)')
            ->join('pv.video', 'v')
            ->andWhere('pv.playlist = :playlist')
            ->andWhere('pv.sortOrder <= :sortOrder')
            ->andWhere('v.status = :status')
            ->setParameter('playlist', $playlist)
            ->setParameter('sortOrder', $playlistVideo->getSortOrder())
            ->setParameter('status', 'published')
            ->getQuery()
            ->getSingleScalarResult();

        return $position;
    }

    /**
     * Удалить видео из плейлиста и пересортировать
     */
    public function removeVideoAndReorder(PlaylistVideo $playlistVideo): void
    {
        $playlist = $playlistVideo->getPlaylist();
        $sortOrder = $playlistVideo->getSortOrder();
        
        $em = $this->getEntityManager();
        
        // Удаляем видео из плейлиста
        $em->remove($playlistVideo);
        
        // Сдвигаем все последующие элементы вверх
        $em->createQueryBuilder()
            ->update(PlaylistVideo::class, 'pv')
            ->set('pv.sortOrder', 'pv.sortOrder - 1')
            ->where('pv.playlist = :playlist')
            ->andWhere('pv.sortOrder > :sortOrder')
            ->setParameter('playlist', $playlist)
            ->setParameter('sortOrder', $sortOrder)
            ->getQuery()
            ->execute();
        
        $em->flush();
    }
}