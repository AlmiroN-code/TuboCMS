<?php

namespace App\Repository;

use App\Entity\Subscription;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Subscription>
 */
class SubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Subscription::class);
    }

    public function findBySubscriberAndChannel(User $subscriber, User $channel): ?Subscription
    {
        return $this->findOneBy(['subscriber' => $subscriber, 'channel' => $channel]);
    }

    /**
     * @return Subscription[]
     */
    public function findBySubscriber(User $subscriber, int $limit = 50, int $offset = 0): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.channel', 'c')
            ->addSelect('c')
            ->where('s.subscriber = :subscriber')
            ->setParameter('subscriber', $subscriber)
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Subscription[]
     */
    public function findByChannel(User $channel, int $limit = 50, int $offset = 0): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.subscriber', 'u')
            ->addSelect('u')
            ->where('s.channel = :channel')
            ->setParameter('channel', $channel)
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function countBySubscriber(User $subscriber): int
    {
        return $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.subscriber = :subscriber')
            ->setParameter('subscriber', $subscriber)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByChannel(User $channel): int
    {
        return $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.channel = :channel')
            ->setParameter('channel', $channel)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function isSubscribed(User $subscriber, User $channel): bool
    {
        return $this->findBySubscriberAndChannel($subscriber, $channel) !== null;
    }

    public function deleteBySubscriberAndChannel(User $subscriber, User $channel): int
    {
        return $this->createQueryBuilder('s')
            ->delete()
            ->where('s.subscriber = :subscriber')
            ->andWhere('s.channel = :channel')
            ->setParameter('subscriber', $subscriber)
            ->setParameter('channel', $channel)
            ->getQuery()
            ->execute();
    }
}
