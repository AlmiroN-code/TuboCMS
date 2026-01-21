<?php

namespace App\Repository;

use App\Entity\ModelProfile;
use App\Entity\Video;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ModelProfile>
 */
class ModelProfileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ModelProfile::class);
    }

    public function findPopular(int $limit = 20): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('m.subscribersCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Поиск моделей с пагинацией, сортировкой, поиском и фильтрацией по полу
     * 
     * @param int $page Номер страницы (начиная с 1)
     * @param int $limit Количество элементов на странице
     * @param string|null $search Поисковый запрос по имени
     * @param string $sort Критерий сортировки: popular, newest, alphabetical, videos
     * @param string|null $gender Фильтр по полу: male, female, trans
     * @return array{items: ModelProfile[], total: int, pages: int}
     */
    public function findPaginated(
        int $page = 1,
        int $limit = 24,
        ?string $search = null,
        string $sort = 'popular',
        ?string $gender = null
    ): array {
        $qb = $this->createQueryBuilder('m')
            ->where('m.isActive = :active')
            ->setParameter('active', true);

        // Поиск по имени
        if ($search !== null && trim($search) !== '') {
            $qb->andWhere('LOWER(m.displayName) LIKE LOWER(:search)')
               ->setParameter('search', '%' . trim($search) . '%');
        }

        // Фильтрация по полу
        if ($gender !== null && in_array($gender, ['male', 'female', 'trans'], true)) {
            $qb->andWhere('m.gender = :gender')
               ->setParameter('gender', $gender);
        }

        // Сортировка
        switch ($sort) {
            case 'newest':
                $qb->orderBy('m.createdAt', 'DESC');
                break;
            case 'alphabetical':
                $qb->orderBy('m.displayName', 'ASC');
                break;
            case 'videos':
                $qb->orderBy('m.videosCount', 'DESC');
                break;
            case 'popular':
            default:
                $qb->orderBy('m.subscribersCount', 'DESC');
                break;
        }

        // Пагинация
        $offset = ($page - 1) * $limit;
        $qb->setFirstResult($offset)
           ->setMaxResults($limit);

        $paginator = new Paginator($qb->getQuery(), true);
        $total = count($paginator);
        $pages = (int) ceil($total / $limit);

        return [
            'items' => iterator_to_array($paginator),
            'total' => $total,
            'pages' => $pages,
        ];
    }

    /**
     * Поиск модели по slug
     */
    public function findBySlug(string $slug): ?ModelProfile
    {
        return $this->createQueryBuilder('m')
            ->where('m.slug = :slug')
            ->andWhere('m.isActive = :active')
            ->setParameter('slug', $slug)
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Получить видео модели с пагинацией (только опубликованные)
     * 
     * @return array{items: Video[], total: int}
     */
    public function findActiveWithVideos(int $modelId, int $limit = 24, int $offset = 0): array
    {
        $em = $this->getEntityManager();
        
        // Получаем видео модели с eager loading
        $qb = $em->createQueryBuilder()
            ->select('v', 'u', 'c', 'p')
            ->from(Video::class, 'v')
            ->innerJoin('v.performers', 'm')
            ->leftJoin('v.createdBy', 'u')
            ->leftJoin('v.categories', 'c')
            ->leftJoin('v.performers', 'p')
            ->where('m.id = :modelId')
            ->andWhere('v.status = :status')
            ->setParameter('modelId', $modelId)
            ->setParameter('status', 'published')
            ->orderBy('v.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $paginator = new Paginator($qb->getQuery(), true);
        $total = count($paginator);

        return [
            'items' => iterator_to_array($paginator),
            'total' => $total,
        ];
    }

    /**
     * Пересчитывает и сохраняет счётчики видео для всех моделей.
     * 
     * @return int Количество обновлённых моделей
     */
    public function recalculateVideoCounts(): int
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = "
            UPDATE model_profile m
            SET m.videos_count = (
                SELECT COUNT(DISTINCT vm.video_id)
                FROM video_model vm
                INNER JOIN video v ON vm.video_id = v.id
                WHERE vm.model_profile_id = m.id
                  AND v.status = ?
            )
        ";
        
        return $conn->executeStatement($sql, [Video::STATUS_PUBLISHED]);
    }
}
