<?php

namespace App\Repository;

use App\Entity\Series;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Series>
 */
class SeriesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Series::class);
    }

    /**
     * @return Series[]
     */
    public function findByAuthor(User $author, int $limit = 50, int $offset = 0): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.author = :author')
            ->setParameter('author', $author)
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function findBySlug(string $slug): ?Series
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    public function countByAuthor(User $author): int
    {
        return $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.author = :author')
            ->setParameter('author', $author)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return Series[]
     */
    public function findPopular(int $limit = 10): array
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.videosCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
