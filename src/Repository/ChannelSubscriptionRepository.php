<?php

namespace App\Repository;

use App\Entity\ChannelSubscription;
use App\Entity\Channel;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ChannelSubscription>
 */
class ChannelSubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChannelSubscription::class);
    }

    public function save(ChannelSubscription $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ChannelSubscription $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Найти подписку пользователя на канал
     */
    public function findSubscription(User $user, Channel $channel): ?ChannelSubscription
    {
        return $this->createQueryBuilder('cs')
            ->andWhere('cs.user = :user')
            ->andWhere('cs.channel = :channel')
            ->setParameter('user', $user)
            ->setParameter('channel', $channel)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Проверить подписан ли пользователь на канал
     */
    public function isSubscribed(User $user, Channel $channel): bool
    {
        return $this->findSubscription($user, $channel) !== null;
    }

    /**
     * Найти подписки пользователя
     */
    public function findUserSubscriptions(User $user, int $limit = 20, int $offset = 0): array
    {
        return $this->createQueryBuilder('cs')
            ->join('cs.channel', 'c')
            ->andWhere('cs.user = :user')
            ->andWhere('c.isActive = :active')
            ->setParameter('user', $user)
            ->setParameter('active', true)
            ->orderBy('cs.subscribedAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Найти подписчиков канала
     */
    public function findChannelSubscribers(Channel $channel, int $limit = 20, int $offset = 0): array
    {
        return $this->createQueryBuilder('cs')
            ->join('cs.user', 'u')
            ->andWhere('cs.channel = :channel')
            ->setParameter('channel', $channel)
            ->orderBy('cs.subscribedAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Подсчет подписчиков канала
     */
    public function countChannelSubscribers(Channel $channel): int
    {
        return $this->createQueryBuilder('cs')
            ->select('COUNT(cs.id)')
            ->andWhere('cs.channel = :channel')
            ->setParameter('channel', $channel)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Подсчет подписок пользователя
     */
    public function countUserSubscriptions(User $user): int
    {
        return $this->createQueryBuilder('cs')
            ->select('COUNT(cs.id)')
            ->join('cs.channel', 'c')
            ->andWhere('cs.user = :user')
            ->andWhere('c.isActive = :active')
            ->setParameter('user', $user)
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Найти активные платные подписки
     */
    public function findActivePaidSubscriptions(User $user): array
    {
        return $this->createQueryBuilder('cs')
            ->join('cs.channel', 'c')
            ->andWhere('cs.user = :user')
            ->andWhere('cs.isPaid = :paid')
            ->andWhere('cs.paidUntil > :now')
            ->andWhere('c.isActive = :active')
            ->setParameter('user', $user)
            ->setParameter('paid', true)
            ->setParameter('now', new \DateTime())
            ->setParameter('active', true)
            ->orderBy('cs.paidUntil', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Найти истекающие подписки
     */
    public function findExpiringSubscriptions(\DateTime $expiryDate): array
    {
        return $this->createQueryBuilder('cs')
            ->join('cs.channel', 'c')
            ->andWhere('cs.isPaid = :paid')
            ->andWhere('cs.paidUntil <= :expiry')
            ->andWhere('cs.paidUntil > :now')
            ->andWhere('c.isActive = :active')
            ->setParameter('paid', true)
            ->setParameter('expiry', $expiryDate)
            ->setParameter('now', new \DateTime())
            ->setParameter('active', true)
            ->orderBy('cs.paidUntil', 'ASC')
            ->getQuery()
            ->getResult();
    }
}