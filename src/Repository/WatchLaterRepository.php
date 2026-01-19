<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\Video;
use App\Entity\WatchLater;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WatchLater>
 */
class WatchLaterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WatchLater::class);
    }

    /**
     * Проверяет, добавлено ли видео в "Смотреть позже" для пользователя
     */
    public function isInWatchLater(User $user, Video $video): bool
    {
        return $this->count(['user' => $user, 'video' => $video]) > 0;
    }

    /**
     * Добавляет видео в "Смотреть позже"
     */
    public function addToWatchLater(User $user, Video $video): WatchLater
    {
        $watchLater = new WatchLater();
        $watchLater->setUser($user);
        $watchLater->setVideo($video);

        $this->getEntityManager()->persist($watchLater);
        $this->getEntityManager()->flush();

        return $watchLater;
    }

    /**
     * Удаляет видео из "Смотреть позже"
     */
    public function removeFromWatchLater(User $user, Video $video): void
    {
        $watchLater = $this->findOneBy(['user' => $user, 'video' => $video]);
        
        if ($watchLater) {
            $this->getEntityManager()->remove($watchLater);
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Получает все видео из "Смотреть позже" для пользователя
     */
    public function findUserWatchLater(User $user, int $page = 1, int $limit = 24): array
    {
        $qb = $this->createQueryBuilder('wl')
            ->select('wl', 'v')
            ->innerJoin('wl.video', 'v')
            ->where('wl.user = :user')
            ->andWhere('v.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'published')
            ->orderBy('wl.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * Подсчитывает количество видео в "Смотреть позже" для пользователя
     */
    public function countUserWatchLater(User $user): int
    {
        return $this->createQueryBuilder('wl')
            ->select('COUNT(wl.id)')
            ->innerJoin('wl.video', 'v')
            ->where('wl.user = :user')
            ->andWhere('v.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'published')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Получает ID всех видео в "Смотреть позже" для пользователя
     */
    public function getUserWatchLaterVideoIds(User $user): array
    {
        $result = $this->createQueryBuilder('wl')
            ->select('IDENTITY(wl.video)')
            ->where('wl.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getScalarResult();

        return array_column($result, 1);
    }
}
