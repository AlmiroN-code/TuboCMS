<?php

namespace App\Repository;

use App\Entity\AdCampaign;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AdCampaignRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdCampaign::class);
    }

    public function findActive(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.status = :status')
            ->setParameter('status', AdCampaign::STATUS_ACTIVE)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findPaginated(int $limit, int $offset, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if ($status) {
            $qb->where('c.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    public function countAll(?string $status = null): int
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)');

        if ($status) {
            $qb->where('c.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->getQuery()->getSingleScalarResult();
    }

    public function getStatsSummary(): array
    {
        return $this->createQueryBuilder('c')
            ->select('
                COUNT(c.id) as totalCampaigns,
                SUM(CASE WHEN c.status = :active THEN 1 ELSE 0 END) as activeCampaigns,
                SUM(c.totalImpressions) as totalImpressions,
                SUM(c.totalClicks) as totalClicks,
                SUM(c.spentAmount) as totalSpent
            ')
            ->setParameter('active', AdCampaign::STATUS_ACTIVE)
            ->getQuery()
            ->getSingleResult();
    }
}
