<?php

namespace App\Repository;

use App\Entity\Tag;
use App\Entity\Video;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tag>
 */
class TagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tag::class);
    }

    public function findPopular(int $limit = 20): array
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.usageCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findAllSorted(string $sort = 'name'): array
    {
        $qb = $this->createQueryBuilder('t');
        
        match ($sort) {
            'popular' => $qb->orderBy('t.usageCount', 'DESC'),
            'newest' => $qb->orderBy('t.id', 'DESC'),
            default => $qb->orderBy('t.name', 'ASC'),
        };
        
        return $qb->getQuery()->getResult();
    }

    /**
     * Пересчитывает и сохраняет счётчики использования для всех тегов.
     * 
     * @return int Количество обновлённых тегов
     */
    public function recalculateVideoCounts(): int
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = "
            UPDATE tag t
            SET t.usage_count = (
                SELECT COUNT(DISTINCT vt.video_id)
                FROM video_tag vt
                INNER JOIN video v ON vt.video_id = v.id
                WHERE vt.tag_id = t.id
                  AND v.status = ?
            )
        ";
        
        return $conn->executeStatement($sql, [Video::STATUS_PUBLISHED]);
    }

    /**
     * Возвращает самые популярные теги
     */
    public function findMostPopular(int $limit = 10): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.usageCount > 0')
            ->orderBy('t.usageCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
