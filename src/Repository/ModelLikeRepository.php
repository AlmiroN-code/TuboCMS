<?php

namespace App\Repository;

use App\Entity\ModelLike;
use App\Entity\ModelProfile;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ModelLike>
 */
class ModelLikeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ModelLike::class);
    }

    public function findByUserAndModel(User $user, ModelProfile $model): ?ModelLike
    {
        return $this->createQueryBuilder('l')
            ->where('l.user = :user')
            ->andWhere('l.model = :model')
            ->setParameter('user', $user)
            ->setParameter('model', $model)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getUserLikeType(User $user, ModelProfile $model): ?string
    {
        $like = $this->findByUserAndModel($user, $model);
        return $like?->getType();
    }
}
