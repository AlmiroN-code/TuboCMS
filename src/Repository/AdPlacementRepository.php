<?php

namespace App\Repository;

use App\Entity\AdPlacement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AdPlacementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdPlacement::class);
    }

    public function findActive(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('p.orderPosition', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findBySlug(string $slug): ?AdPlacement
    {
        return $this->findOneBy(['slug' => $slug, 'isActive' => true]);
    }

    public function findByPosition(string $position): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.position = :position')
            ->andWhere('p.isActive = :active')
            ->setParameter('position', $position)
            ->setParameter('active', true)
            ->orderBy('p.orderPosition', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.type = :type')
            ->andWhere('p.isActive = :active')
            ->setParameter('type', $type)
            ->setParameter('active', true)
            ->orderBy('p.orderPosition', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.position', 'ASC')
            ->addOrderBy('p.orderPosition', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Загружает все активные placements с их ads одним запросом
     */
    public function findAllActiveWithAds(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.ads', 'a')
            ->addSelect('a')
            ->where('p.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('p.orderPosition', 'ASC')
            ->getQuery()
            ->useQueryCache(true)
            ->setResultCacheLifetime(300) // 5 минут кэш
            ->getResult();
    }
}
