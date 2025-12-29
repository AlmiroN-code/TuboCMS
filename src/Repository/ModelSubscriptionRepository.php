<?php

namespace App\Repository;

use App\Entity\ModelProfile;
use App\Entity\ModelSubscription;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ModelSubscription>
 */
class ModelSubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ModelSubscription::class);
    }

    public function findByUserAndModel(User $user, ModelProfile $model): ?ModelSubscription
    {
        return $this->createQueryBuilder('s')
            ->where('s.user = :user')
            ->andWhere('s.model = :model')
            ->setParameter('user', $user)
            ->setParameter('model', $model)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function isSubscribed(User $user, ModelProfile $model): bool
    {
        return $this->findByUserAndModel($user, $model) !== null;
    }

    public function findByUser(User $user, int $limit = 20, int $offset = 0): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.user = :user')
            ->setParameter('user', $user)
            ->orderBy('s.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
