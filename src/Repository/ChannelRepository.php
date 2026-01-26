<?php

namespace App\Repository;

use App\Entity\Channel;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Channel>
 */
class ChannelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Channel::class);
    }

    public function save(Channel $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Channel $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Найти активные каналы
     */
    public function findActive(int $limit = 20, int $offset = 0): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('c.subscribersCount', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Найти канал по slug
     */
    public function findBySlug(string $slug): ?Channel
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.slug = :slug')
            ->andWhere('c.isActive = :active')
            ->setParameter('slug', $slug)
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Найти каналы пользователя
     */
    public function findByOwner(User $owner): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Найти популярные каналы
     */
    public function findPopular(int $limit = 20, int $offset = 0): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('c.subscribersCount', 'DESC')
            ->addOrderBy('c.totalViews', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Найти верифицированные каналы
     */
    public function findVerified(int $limit = 20, int $offset = 0): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.isActive = :active')
            ->andWhere('c.isVerified = :verified')
            ->setParameter('active', true)
            ->setParameter('verified', true)
            ->orderBy('c.subscribersCount', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Найти студии
     */
    public function findStudios(int $limit = 20, int $offset = 0): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.isActive = :active')
            ->andWhere('c.type = :type')
            ->setParameter('active', true)
            ->setParameter('type', Channel::TYPE_STUDIO)
            ->orderBy('c.subscribersCount', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Поиск каналов
     */
    public function search(string $query, int $limit = 20, int $offset = 0): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.name LIKE :query OR c.description LIKE :query')
            ->andWhere('c.isActive = :active')
            ->setParameter('query', '%' . $query . '%')
            ->setParameter('active', true)
            ->orderBy('c.subscribersCount', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Подсчет активных каналов
     */
    public function countActive(): int
    {
        return $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Подсчет студий
     */
    public function countStudios(): int
    {
        return $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.isActive = :active')
            ->andWhere('c.type = :type')
            ->setParameter('active', true)
            ->setParameter('type', Channel::TYPE_STUDIO)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Найти каналы с фильтрами
     */
    public function findWithFilters(array $filters, int $limit = 20, int $offset = 0): array
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.isActive = :active')
            ->setParameter('active', true);

        if (isset($filters['type']) && $filters['type']) {
            $qb->andWhere('c.type = :type')
               ->setParameter('type', $filters['type']);
        }

        if (isset($filters['verified']) && $filters['verified']) {
            $qb->andWhere('c.isVerified = :verified')
               ->setParameter('verified', true);
        }

        if (isset($filters['premium']) && $filters['premium']) {
            $qb->andWhere('c.isPremium = :premium')
               ->setParameter('premium', true);
        }

        if (isset($filters['search']) && $filters['search']) {
            $qb->andWhere('c.name LIKE :search OR c.description LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }

        // Сортировка
        $sort = $filters['sort'] ?? 'popular';
        switch ($sort) {
            case 'newest':
                $qb->orderBy('c.createdAt', 'DESC');
                break;
            case 'oldest':
                $qb->orderBy('c.createdAt', 'ASC');
                break;
            case 'name':
                $qb->orderBy('c.name', 'ASC');
                break;
            case 'videos':
                $qb->orderBy('c.videosCount', 'DESC');
                break;
            default: // popular
                $qb->orderBy('c.subscribersCount', 'DESC')
                   ->addOrderBy('c.totalViews', 'DESC');
        }

        return $qb->setMaxResults($limit)
                  ->setFirstResult($offset)
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Подсчет каналов с фильтрами
     */
    public function countWithFilters(array $filters): int
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.isActive = :active')
            ->setParameter('active', true);

        if (isset($filters['type']) && $filters['type']) {
            $qb->andWhere('c.type = :type')
               ->setParameter('type', $filters['type']);
        }

        if (isset($filters['verified']) && $filters['verified']) {
            $qb->andWhere('c.isVerified = :verified')
               ->setParameter('verified', true);
        }

        if (isset($filters['premium']) && $filters['premium']) {
            $qb->andWhere('c.isPremium = :premium')
               ->setParameter('premium', true);
        }

        if (isset($filters['search']) && $filters['search']) {
            $qb->andWhere('c.name LIKE :search OR c.description LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }

        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Проверить уникальность slug
     */
    public function isSlugUnique(string $slug, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.slug = :slug')
            ->setParameter('slug', $slug);

        if ($excludeId) {
            $qb->andWhere('c.id != :id')
               ->setParameter('id', $excludeId);
        }

        return $qb->getQuery()->getSingleScalarResult() === 0;
    }
}