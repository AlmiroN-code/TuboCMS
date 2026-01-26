<?php

namespace App\Repository;

use App\Entity\Channel;
use App\Entity\ChannelPlaylist;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ChannelPlaylist>
 */
class ChannelPlaylistRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChannelPlaylist::class);
    }

    /**
     * Найти плейлисты канала
     */
    public function findByChannel(Channel $channel, ?User $viewer = null, int $limit = 20, int $offset = 0): array
    {
        $qb = $this->createQueryBuilder('p')
            ->select('p', 'c')
            ->join('p.channel', 'c')
            ->andWhere('p.channel = :channel')
            ->andWhere('p.isActive = :active')
            ->setParameter('channel', $channel)
            ->setParameter('active', true);

        // Фильтрация по видимости в зависимости от пользователя
        if (!$viewer || $viewer !== $channel->getOwner()) {
            if ($viewer && $viewer->isPremium()) {
                // Premium пользователи видят публичные, unlisted и premium плейлисты
                $qb->andWhere('p.visibility IN (:visibilities)')
                   ->setParameter('visibilities', [
                       ChannelPlaylist::VISIBILITY_PUBLIC,
                       ChannelPlaylist::VISIBILITY_UNLISTED,
                       ChannelPlaylist::VISIBILITY_PREMIUM
                   ]);
            } else {
                // Обычные пользователи видят только публичные и unlisted
                $qb->andWhere('p.visibility IN (:visibilities)')
                   ->setParameter('visibilities', [
                       ChannelPlaylist::VISIBILITY_PUBLIC,
                       ChannelPlaylist::VISIBILITY_UNLISTED
                   ]);
            }
        }

        return $qb->orderBy('p.sortOrder', 'ASC')
                  ->addOrderBy('p.createdAt', 'DESC')
                  ->setMaxResults($limit)
                  ->setFirstResult($offset)
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Найти плейлист по slug
     */
    public function findBySlug(string $slug, ?User $viewer = null): ?ChannelPlaylist
    {
        $qb = $this->createQueryBuilder('p')
            ->select('p', 'c')
            ->join('p.channel', 'c')
            ->andWhere('p.slug = :slug')
            ->andWhere('p.isActive = :active')
            ->setParameter('slug', $slug)
            ->setParameter('active', true);

        $playlist = $qb->getQuery()->getOneOrNullResult();

        if (!$playlist) {
            return null;
        }

        // Проверка прав доступа
        if (!$this->canUserViewPlaylist($playlist, $viewer)) {
            return null;
        }

        return $playlist;
    }

    /**
     * Найти публичные плейлисты
     */
    public function findPublic(int $limit = 20, int $offset = 0): array
    {
        return $this->createQueryBuilder('p')
            ->select('p', 'c')
            ->join('p.channel', 'c')
            ->andWhere('p.visibility = :visibility')
            ->andWhere('p.isActive = :active')
            ->andWhere('c.isActive = :channelActive')
            ->setParameter('visibility', ChannelPlaylist::VISIBILITY_PUBLIC)
            ->setParameter('active', true)
            ->setParameter('channelActive', true)
            ->orderBy('p.viewsCount', 'DESC')
            ->addOrderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Поиск плейлистов
     */
    public function search(string $query, ?User $viewer = null, int $limit = 20, int $offset = 0): array
    {
        $qb = $this->createQueryBuilder('p')
            ->select('p', 'c')
            ->join('p.channel', 'c')
            ->andWhere('p.title LIKE :query OR p.description LIKE :query')
            ->andWhere('p.isActive = :active')
            ->andWhere('c.isActive = :channelActive')
            ->setParameter('query', '%' . $query . '%')
            ->setParameter('active', true)
            ->setParameter('channelActive', true);

        // Фильтрация по видимости
        if (!$viewer) {
            $qb->andWhere('p.visibility = :visibility')
               ->setParameter('visibility', ChannelPlaylist::VISIBILITY_PUBLIC);
        } elseif ($viewer->isPremium()) {
            $qb->andWhere('p.visibility IN (:visibilities)')
               ->setParameter('visibilities', [
                   ChannelPlaylist::VISIBILITY_PUBLIC,
                   ChannelPlaylist::VISIBILITY_PREMIUM
               ]);
        } else {
            $qb->andWhere('p.visibility = :visibility')
               ->setParameter('visibility', ChannelPlaylist::VISIBILITY_PUBLIC);
        }

        return $qb->orderBy('p.viewsCount', 'DESC')
                  ->setMaxResults($limit)
                  ->setFirstResult($offset)
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Получить популярные плейлисты
     */
    public function findPopular(?User $viewer = null, int $limit = 20, int $offset = 0): array
    {
        $qb = $this->createQueryBuilder('p')
            ->select('p', 'c')
            ->join('p.channel', 'c')
            ->andWhere('p.isActive = :active')
            ->andWhere('c.isActive = :channelActive')
            ->setParameter('active', true)
            ->setParameter('channelActive', true);

        // Фильтрация по видимости
        if (!$viewer) {
            $qb->andWhere('p.visibility = :visibility')
               ->setParameter('visibility', ChannelPlaylist::VISIBILITY_PUBLIC);
        } elseif ($viewer->isPremium()) {
            $qb->andWhere('p.visibility IN (:visibilities)')
               ->setParameter('visibilities', [
                   ChannelPlaylist::VISIBILITY_PUBLIC,
                   ChannelPlaylist::VISIBILITY_PREMIUM
               ]);
        } else {
            $qb->andWhere('p.visibility = :visibility')
               ->setParameter('visibility', ChannelPlaylist::VISIBILITY_PUBLIC);
        }

        return $qb->orderBy('p.viewsCount', 'DESC')
                  ->addOrderBy('p.videosCount', 'DESC')
                  ->setMaxResults($limit)
                  ->setFirstResult($offset)
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Подсчет плейлистов канала
     */
    public function countByChannel(Channel $channel, ?User $viewer = null): int
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.channel = :channel')
            ->andWhere('p.isActive = :active')
            ->setParameter('channel', $channel)
            ->setParameter('active', true);

        // Фильтрация по видимости
        if (!$viewer || $viewer !== $channel->getOwner()) {
            if ($viewer && $viewer->isPremium()) {
                $qb->andWhere('p.visibility IN (:visibilities)')
                   ->setParameter('visibilities', [
                       ChannelPlaylist::VISIBILITY_PUBLIC,
                       ChannelPlaylist::VISIBILITY_UNLISTED,
                       ChannelPlaylist::VISIBILITY_PREMIUM
                   ]);
            } else {
                $qb->andWhere('p.visibility IN (:visibilities)')
                   ->setParameter('visibilities', [
                       ChannelPlaylist::VISIBILITY_PUBLIC,
                       ChannelPlaylist::VISIBILITY_UNLISTED
                   ]);
            }
        }

        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Проверить уникальность slug
     */
    public function isSlugUnique(string $slug, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.slug = :slug')
            ->setParameter('slug', $slug);

        if ($excludeId) {
            $qb->andWhere('p.id != :id')
               ->setParameter('id', $excludeId);
        }

        return $qb->getQuery()->getSingleScalarResult() === 0;
    }

    /**
     * Получить следующий порядок сортировки для канала
     */
    public function getNextSortOrder(Channel $channel): int
    {
        $result = $this->createQueryBuilder('p')
            ->select('MAX(p.sortOrder)')
            ->andWhere('p.channel = :channel')
            ->setParameter('channel', $channel)
            ->getQuery()
            ->getSingleScalarResult();

        return ($result ?? 0) + 1;
    }

    /**
     * Проверить может ли пользователь просматривать плейлист
     */
    private function canUserViewPlaylist(ChannelPlaylist $playlist, ?User $viewer): bool
    {
        // Владелец канала может видеть все плейлисты
        if ($viewer && $viewer === $playlist->getChannel()->getOwner()) {
            return true;
        }

        // Проверка видимости
        switch ($playlist->getVisibility()) {
            case ChannelPlaylist::VISIBILITY_PUBLIC:
            case ChannelPlaylist::VISIBILITY_UNLISTED:
                return true;
            
            case ChannelPlaylist::VISIBILITY_PREMIUM:
                return $viewer && $viewer->isPremium();
            
            case ChannelPlaylist::VISIBILITY_PRIVATE:
                return false;
            
            default:
                return false;
        }
    }
}