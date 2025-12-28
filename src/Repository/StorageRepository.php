<?php

namespace App\Repository;

use App\Entity\Storage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Storage>
 */
class StorageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Storage::class);
    }

    /**
     * Находит хранилище по умолчанию
     */
    public function findDefault(): ?Storage
    {
        return $this->createQueryBuilder('s')
            ->where('s.isDefault = :isDefault')
            ->andWhere('s.isEnabled = :isEnabled')
            ->setParameter('isDefault', true)
            ->setParameter('isEnabled', true)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Находит все включённые хранилища
     * 
     * @return Storage[]
     */
    public function findEnabled(): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.isEnabled = :isEnabled')
            ->setParameter('isEnabled', true)
            ->orderBy('s.isDefault', 'DESC')
            ->addOrderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Находит хранилища по типу
     * 
     * @return Storage[]
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.type = :type')
            ->setParameter('type', $type)
            ->orderBy('s.isDefault', 'DESC')
            ->addOrderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
