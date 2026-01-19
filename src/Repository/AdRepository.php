<?php

namespace App\Repository;

use App\Entity\Ad;
use App\Entity\AdPlacement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AdRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ad::class);
    }

    public function findActiveForPlacement(AdPlacement $placement, array $context = []): array
    {
        $now = new \DateTime();
        
        $qb = $this->createQueryBuilder('a')
            ->where('a.placement = :placement')
            ->andWhere('a.isActive = :active')
            ->andWhere('a.status = :status')
            ->andWhere('(a.startDate IS NULL OR a.startDate <= :now)')
            ->andWhere('(a.endDate IS NULL OR a.endDate >= :now)')
            ->setParameter('placement', $placement)
            ->setParameter('active', true)
            ->setParameter('status', Ad::STATUS_ACTIVE)
            ->setParameter('now', $now)
            ->orderBy('a.priority', 'DESC')
            ->addOrderBy('a.weight', 'DESC');

        return $qb->getQuery()->getResult();
    }

    public function findForAdminList(int $limit, int $offset, ?string $status = null, ?int $placementId = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.placement', 'p')
            ->leftJoin('a.campaign', 'c')
            ->addSelect('p', 'c')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if ($status) {
            $qb->andWhere('a.status = :status')
               ->setParameter('status', $status);
        }

        if ($placementId) {
            $qb->andWhere('p.id = :placementId')
               ->setParameter('placementId', $placementId);
        }

        return $qb->getQuery()->getResult();
    }

    public function countForAdminList(?string $status = null, ?int $placementId = null): int
    {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)');

        if ($status) {
            $qb->andWhere('a.status = :status')
               ->setParameter('status', $status);
        }

        if ($placementId) {
            $qb->leftJoin('a.placement', 'p')
               ->andWhere('p.id = :placementId')
               ->setParameter('placementId', $placementId);
        }

        return $qb->getQuery()->getSingleScalarResult();
    }

    public function findByCampaign(int $campaignId): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.placement', 'p')
            ->addSelect('p')
            ->where('a.campaign = :campaignId')
            ->setParameter('campaignId', $campaignId)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getStatsSummary(): array
    {
        return $this->createQueryBuilder('a')
            ->select('
                COUNT(a.id) as totalAds,
                SUM(CASE WHEN a.status = :active THEN 1 ELSE 0 END) as activeAds,
                SUM(a.impressionsCount) as totalImpressions,
                SUM(a.clicksCount) as totalClicks,
                SUM(a.spentAmount) as totalSpent
            ')
            ->setParameter('active', Ad::STATUS_ACTIVE)
            ->getQuery()
            ->getSingleResult();
    }

    public function findTopPerforming(int $limit = 10): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.placement', 'p')
            ->addSelect('p')
            ->where('a.impressionsCount > 0')
            ->orderBy('(a.clicksCount / a.impressionsCount)', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
