<?php

namespace App\Repository;

use App\Entity\PostCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PostCategory>
 */
class PostCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PostCategory::class);
    }

    public function save(PostCategory $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PostCategory $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Найти активные категории
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('pc')
            ->andWhere('pc.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('pc.sortOrder', 'ASC')
            ->addOrderBy('pc.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Найти категорию по slug
     */
    public function findBySlug(string $slug): ?PostCategory
    {
        return $this->createQueryBuilder('pc')
            ->andWhere('pc.slug = :slug')
            ->andWhere('pc.isActive = :active')
            ->setParameter('slug', $slug)
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Найти все категории для админки
     */
    public function findForAdmin(): array
    {
        return $this->createQueryBuilder('pc')
            ->orderBy('pc.sortOrder', 'ASC')
            ->addOrderBy('pc.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Проверить уникальность slug
     */
    public function isSlugUnique(string $slug, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('pc')
            ->select('COUNT(pc.id)')
            ->andWhere('pc.slug = :slug')
            ->setParameter('slug', $slug);

        if ($excludeId) {
            $qb->andWhere('pc.id != :id')
               ->setParameter('id', $excludeId);
        }

        return $qb->getQuery()->getSingleScalarResult() === 0;
    }
}