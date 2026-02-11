<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\LiveStream;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class LiveStreamRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LiveStream::class);
    }

    public function save(LiveStream $liveStream): void
    {
        $this->getEntityManager()->persist($liveStream);
        $this->getEntityManager()->flush();
    }

    public function remove(LiveStream $liveStream): void
    {
        $this->getEntityManager()->remove($liveStream);
        $this->getEntityManager()->flush();
    }

    /**
     * Получить активные стримы
     */
    public function findLiveStreams(int $limit = 20, int $offset = 0): array
    {
        return $this->createQueryBuilder('ls')
            ->leftJoin('ls.streamer', 'u')
            ->leftJoin('ls.channel', 'c')
            ->addSelect('u', 'c')
            ->where('ls.status = :status')
            ->setParameter('status', LiveStream::STATUS_LIVE)
            ->orderBy('ls.viewersCount', 'DESC')
            ->addOrderBy('ls.startedAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Получить запланированные стримы
     */
    public function findScheduledStreams(int $limit = 20, int $offset = 0): array
    {
        return $this->createQueryBuilder('ls')
            ->leftJoin('ls.streamer', 'u')
            ->leftJoin('ls.channel', 'c')
            ->addSelect('u', 'c')
            ->where('ls.status = :status')
            ->setParameter('status', LiveStream::STATUS_SCHEDULED)
            ->orderBy('ls.scheduledAt', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Получить стримы пользователя
     */
    public function findByStreamer(User $user, int $limit = 20, int $offset = 0): array
    {
        return $this->createQueryBuilder('ls')
            ->leftJoin('ls.channel', 'c')
            ->addSelect('c')
            ->where('ls.streamer = :user')
            ->setParameter('user', $user)
            ->orderBy('ls.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Найти стрим по stream key
     */
    public function findByStreamKey(string $streamKey): ?LiveStream
    {
        return $this->createQueryBuilder('ls')
            ->where('ls.streamKey = :streamKey')
            ->setParameter('streamKey', $streamKey)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Подсчитать активные стримы
     */
    public function countLiveStreams(): int
    {
        return (int) $this->createQueryBuilder('ls')
            ->select('COUNT(ls.id)')
            ->where('ls.status = :status')
            ->setParameter('status', LiveStream::STATUS_LIVE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Получить популярные стримы за период
     */
    public function findPopularStreams(\DateTimeImmutable $since, int $limit = 10): array
    {
        return $this->createQueryBuilder('ls')
            ->leftJoin('ls.streamer', 'u')
            ->leftJoin('ls.channel', 'c')
            ->addSelect('u', 'c')
            ->where('ls.startedAt >= :since')
            ->andWhere('ls.status IN (:statuses)')
            ->setParameter('since', $since)
            ->setParameter('statuses', [LiveStream::STATUS_LIVE, LiveStream::STATUS_ENDED])
            ->orderBy('ls.peakViewersCount', 'DESC')
            ->addOrderBy('ls.totalViews', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
