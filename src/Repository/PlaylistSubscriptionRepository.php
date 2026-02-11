<?php

namespace App\Repository;

use App\Entity\PlaylistSubscription;
use App\Entity\User;
use App\Entity\ChannelPlaylist;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlaylistSubscription>
 */
class PlaylistSubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlaylistSubscription::class);
    }

    public function findByUser(User $user, int $limit = 20, int $offset = 0): array
    {
        return $this->createQueryBuilder('ps')
            ->where('ps.user = :user')
            ->setParameter('user', $user)
            ->orderBy('ps.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function countByUser(User $user): int
    {
        return $this->createQueryBuilder('ps')
            ->select('COUNT(ps.id)')
            ->where('ps.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function isSubscribed(User $user, ChannelPlaylist $playlist): bool
    {
        return $this->count(['user' => $user, 'playlist' => $playlist]) > 0;
    }

    public function countByPlaylist(ChannelPlaylist $playlist): int
    {
        return $this->count(['playlist' => $playlist]);
    }
}
