<?php

namespace App\Repository;

use App\Entity\AdAbTest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AdAbTestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdAbTest::class);
    }

    public function findRunning(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.status = :status')
            ->setParameter('status', AdAbTest::STATUS_RUNNING)
            ->orderBy('t.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findPaginated(int $limit, int $offset, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if ($status) {
            $qb->where('t.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    public function countAll(?string $status = null): int
    {
        $qb = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)');

        if ($status) {
            $qb->where('t.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->getQuery()->getSingleScalarResult();
    }
}
