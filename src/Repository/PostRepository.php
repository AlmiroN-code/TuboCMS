<?php

namespace App\Repository;

use App\Entity\Post;
use App\Entity\PostCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Post>
 */
class PostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Post::class);
    }

    public function save(Post $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Post $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Найти опубликованные посты
     */
    public function findPublished(int $limit = 20, int $offset = 0): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.status = :status')
            ->andWhere('p.isActive = :active')
            ->setParameter('status', 'published')
            ->setParameter('active', true)
            ->orderBy('p.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Найти посты по категории
     */
    public function findByCategory(PostCategory $category, int $limit = 20, int $offset = 0): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.categories', 'c')
            ->andWhere('c = :category')
            ->andWhere('p.status = :status')
            ->andWhere('p.isActive = :active')
            ->setParameter('category', $category)
            ->setParameter('status', 'published')
            ->setParameter('active', true)
            ->orderBy('p.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Найти пост по полному slug (включая родительские)
     */
    public function findByFullSlug(string $fullSlug): ?Post
    {
        $slugParts = explode('/', $fullSlug);
        $lastSlug = array_pop($slugParts);
        
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.slug = :slug')
            ->andWhere('p.status = :status')
            ->andWhere('p.isActive = :active')
            ->setParameter('slug', $lastSlug)
            ->setParameter('status', 'published')
            ->setParameter('active', true);

        // Если есть родительские slug'и, проверяем иерархию
        if (!empty($slugParts)) {
            $qb->join('p.parent', 'parent');
            // Здесь можно добавить более сложную логику для проверки полного пути
        } else {
            // Если нет родительских slug'ов, то parent должен быть null
            $qb->andWhere('p.parent IS NULL');
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Найти корневые посты (без родителя)
     */
    public function findRootPosts(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.parent IS NULL')
            ->andWhere('p.status = :status')
            ->andWhere('p.isActive = :active')
            ->setParameter('status', 'published')
            ->setParameter('active', true)
            ->orderBy('p.sortOrder', 'ASC')
            ->addOrderBy('p.publishedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Найти дочерние посты
     */
    public function findChildren(Post $parent): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.parent = :parent')
            ->andWhere('p.status = :status')
            ->andWhere('p.isActive = :active')
            ->setParameter('parent', $parent)
            ->setParameter('status', 'published')
            ->setParameter('active', true)
            ->orderBy('p.sortOrder', 'ASC')
            ->addOrderBy('p.publishedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Поиск постов
     */
    public function search(string $query, int $limit = 20, int $offset = 0): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.title LIKE :query OR p.content LIKE :query OR p.excerpt LIKE :query')
            ->andWhere('p.status = :status')
            ->andWhere('p.isActive = :active')
            ->setParameter('query', '%' . $query . '%')
            ->setParameter('status', 'published')
            ->setParameter('active', true)
            ->orderBy('p.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Подсчет опубликованных постов
     */
    public function countPublished(): int
    {
        return $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.status = :status')
            ->andWhere('p.isActive = :active')
            ->setParameter('status', 'published')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Подсчет постов в категории
     */
    public function countByCategory(PostCategory $category): int
    {
        return $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->join('p.categories', 'c')
            ->andWhere('c = :category')
            ->andWhere('p.status = :status')
            ->andWhere('p.isActive = :active')
            ->setParameter('category', $category)
            ->setParameter('status', 'published')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Найти все посты для админки
     */
    public function findForAdmin(int $limit = 20, int $offset = 0, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.author', 'a')
            ->addSelect('a')
            ->orderBy('p.updatedAt', 'DESC');

        if ($status) {
            $qb->andWhere('p.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->setMaxResults($limit)
                  ->setFirstResult($offset)
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Подсчет всех постов для админки
     */
    public function countForAdmin(?string $status = null): int
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)');

        if ($status) {
            $qb->andWhere('p.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->getQuery()->getSingleScalarResult();
    }
}