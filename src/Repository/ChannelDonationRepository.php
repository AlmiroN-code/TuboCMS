<?php

namespace App\Repository;

use App\Entity\Channel;
use App\Entity\ChannelDonation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ChannelDonation>
 */
class ChannelDonationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChannelDonation::class);
    }

    /**
     * Найти донаты канала
     */
    public function findByChannel(Channel $channel, int $limit = 20, int $offset = 0): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.channel = :channel')
            ->andWhere('d.status = :status')
            ->setParameter('channel', $channel)
            ->setParameter('status', ChannelDonation::STATUS_COMPLETED)
            ->orderBy('d.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Найти донаты пользователя
     */
    public function findByDonor(User $donor, int $limit = 20, int $offset = 0): array
    {
        return $this->createQueryBuilder('d')
            ->join('d.channel', 'c')
            ->andWhere('d.donor = :donor')
            ->andWhere('c.isActive = :active')
            ->setParameter('donor', $donor)
            ->setParameter('active', true)
            ->orderBy('d.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Получить последние донаты канала (публичные)
     */
    public function getRecentPublicDonations(Channel $channel, int $limit = 10): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.channel = :channel')
            ->andWhere('d.status = :status')
            ->andWhere('d.isAnonymous = :anonymous')
            ->setParameter('channel', $channel)
            ->setParameter('status', ChannelDonation::STATUS_COMPLETED)
            ->setParameter('anonymous', false)
            ->orderBy('d.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Получить статистику донатов канала
     */
    public function getChannelDonationStats(Channel $channel, ?\DateTimeInterface $startDate = null, ?\DateTimeInterface $endDate = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->select([
                'COUNT(d.id) as totalDonations',
                'SUM(d.amount) as totalAmount',
                'AVG(d.amount) as avgAmount',
                'MAX(d.amount) as maxAmount',
                'MIN(d.amount) as minAmount'
            ])
            ->andWhere('d.channel = :channel')
            ->andWhere('d.status = :status')
            ->setParameter('channel', $channel)
            ->setParameter('status', ChannelDonation::STATUS_COMPLETED);

        if ($startDate) {
            $qb->andWhere('d.createdAt >= :startDate')
               ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $qb->andWhere('d.createdAt <= :endDate')
               ->setParameter('endDate', $endDate);
        }

        $result = $qb->getQuery()->getOneOrNullResult();

        return [
            'totalDonations' => (int) ($result['totalDonations'] ?? 0),
            'totalAmount' => (float) ($result['totalAmount'] ?? 0),
            'avgAmount' => (float) ($result['avgAmount'] ?? 0),
            'maxAmount' => (float) ($result['maxAmount'] ?? 0),
            'minAmount' => (float) ($result['minAmount'] ?? 0),
        ];
    }

    /**
     * Получить топ донатеров канала
     */
    public function getTopDonors(Channel $channel, int $limit = 10, ?\DateTimeInterface $startDate = null, ?\DateTimeInterface $endDate = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->select([
                'u.id',
                'u.username',
                'u.avatar',
                'SUM(d.amount) as totalAmount',
                'COUNT(d.id) as donationsCount'
            ])
            ->join('d.donor', 'u')
            ->andWhere('d.channel = :channel')
            ->andWhere('d.status = :status')
            ->andWhere('d.isAnonymous = :anonymous')
            ->setParameter('channel', $channel)
            ->setParameter('status', ChannelDonation::STATUS_COMPLETED)
            ->setParameter('anonymous', false);

        if ($startDate) {
            $qb->andWhere('d.createdAt >= :startDate')
               ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $qb->andWhere('d.createdAt <= :endDate')
               ->setParameter('endDate', $endDate);
        }

        return $qb->groupBy('u.id')
                  ->orderBy('totalAmount', 'DESC')
                  ->setMaxResults($limit)
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Получить донаты по месяцам для графика
     */
    public function getDonationsByMonth(Channel $channel, int $months = 12): array
    {
        $startDate = new \DateTime();
        $startDate->modify("-{$months} months");
        $startDate->modify('first day of this month');
        $startDate->setTime(0, 0, 0);

        $results = $this->createQueryBuilder('d')
            ->select([
                'YEAR(d.createdAt) as year',
                'MONTH(d.createdAt) as month',
                'SUM(d.amount) as totalAmount',
                'COUNT(d.id) as donationsCount'
            ])
            ->andWhere('d.channel = :channel')
            ->andWhere('d.status = :status')
            ->andWhere('d.createdAt >= :startDate')
            ->setParameter('channel', $channel)
            ->setParameter('status', ChannelDonation::STATUS_COMPLETED)
            ->setParameter('startDate', $startDate)
            ->groupBy('year', 'month')
            ->orderBy('year', 'ASC')
            ->addOrderBy('month', 'ASC')
            ->getQuery()
            ->getResult();

        $chartData = [
            'labels' => [],
            'amounts' => [],
            'counts' => []
        ];

        foreach ($results as $result) {
            $date = new \DateTime();
            $date->setDate($result['year'], $result['month'], 1);
            
            $chartData['labels'][] = $date->format('M Y');
            $chartData['amounts'][] = (float) $result['totalAmount'];
            $chartData['counts'][] = (int) $result['donationsCount'];
        }

        return $chartData;
    }

    /**
     * Найти донат по ID платежа
     */
    public function findByPaymentId(string $paymentId): ?ChannelDonation
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.paymentId = :paymentId')
            ->setParameter('paymentId', $paymentId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Получить ожидающие обработки донаты
     */
    public function findPendingDonations(int $limit = 100): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.status = :status')
            ->andWhere('d.createdAt < :timeout')
            ->setParameter('status', ChannelDonation::STATUS_PENDING)
            ->setParameter('timeout', new \DateTime('-1 hour'))
            ->orderBy('d.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Подсчет донатов канала
     */
    public function countByChannel(Channel $channel): int
    {
        return $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.channel = :channel')
            ->andWhere('d.status = :status')
            ->setParameter('channel', $channel)
            ->setParameter('status', ChannelDonation::STATUS_COMPLETED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Получить общую сумму донатов канала
     */
    public function getTotalDonationAmount(Channel $channel): float
    {
        $result = $this->createQueryBuilder('d')
            ->select('SUM(d.amount)')
            ->andWhere('d.channel = :channel')
            ->andWhere('d.status = :status')
            ->setParameter('channel', $channel)
            ->setParameter('status', ChannelDonation::STATUS_COMPLETED)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }
}