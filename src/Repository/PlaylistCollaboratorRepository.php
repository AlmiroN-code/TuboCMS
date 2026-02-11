<?php

namespace App\Repository;

use App\Entity\ChannelPlaylist;
use App\Entity\PlaylistCollaborator;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlaylistCollaborator>
 */
class PlaylistCollaboratorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlaylistCollaborator::class);
    }

    /**
     * Найти соавтора плейлиста
     */
    public function findByPlaylistAndUser(ChannelPlaylist $playlist, User $user): ?PlaylistCollaborator
    {
        return $this->createQueryBuilder('c')
            ->where('c.playlist = :playlist')
            ->andWhere('c.user = :user')
            ->setParameter('playlist', $playlist)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Получить всех соавторов плейлиста
     */
    public function findByPlaylist(ChannelPlaylist $playlist): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.playlist = :playlist')
            ->setParameter('playlist', $playlist)
            ->orderBy('c.addedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Получить плейлисты где пользователь соавтор
     */
    public function findPlaylistsByUser(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->select('IDENTITY(c.playlist)')
            ->where('c.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleColumnResult();
    }

    /**
     * Проверить является ли пользователь соавтором
     */
    public function isCollaborator(ChannelPlaylist $playlist, User $user): bool
    {
        return $this->findByPlaylistAndUser($playlist, $user) !== null;
    }

    /**
     * Подсчитать количество соавторов
     */
    public function countByPlaylist(ChannelPlaylist $playlist): int
    {
        return $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.playlist = :playlist')
            ->setParameter('playlist', $playlist)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
