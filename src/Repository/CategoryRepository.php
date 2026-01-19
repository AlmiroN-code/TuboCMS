<?php

namespace App\Repository;

use App\Entity\Category;
use App\Entity\Video;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Category>
 */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    public function findActive(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('c.orderPosition', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Возвращает активные категории с актуальным количеством видео.
     * 
     * @return array<array{category: Category, videosCount: int}>
     */
    public function findActiveWithVideoCounts(): array
    {
        $categories = $this->createQueryBuilder('c')
            ->select('c', 'COUNT(DISTINCT v.id) as videosCount')
            ->leftJoin('c.videos', 'v', 'WITH', 'v.status = :status')
            ->where('c.isActive = :active')
            ->setParameter('active', true)
            ->setParameter('status', Video::STATUS_PUBLISHED)
            ->groupBy('c.id')
            ->orderBy('c.orderPosition', 'ASC')
            ->getQuery()
            ->getResult();

        // Обновляем videosCount в сущностях для корректного отображения
        $result = [];
        foreach ($categories as $row) {
            $category = $row[0];
            $count = (int) $row['videosCount'];
            $category->setVideosCount($count);
            $result[] = $category;
        }

        return $result;
    }

    /**
     * Пересчитывает и сохраняет счётчики видео для всех категорий.
     * 
     * @return int Количество обновлённых категорий
     */
    public function recalculateVideoCounts(): int
    {
        $conn = $this->getEntityManager()->getConnection();
        
        // Обновляем счётчики одним SQL запросом
        $sql = "
            UPDATE category c
            SET c.videos_count = (
                SELECT COUNT(DISTINCT vc.video_id)
                FROM video_category vc
                INNER JOIN video v ON vc.video_id = v.id
                WHERE vc.category_id = c.id
                  AND v.status = ?
            )
        ";
        
        return $conn->executeStatement($sql, [Video::STATUS_PUBLISHED]);
    }
}
