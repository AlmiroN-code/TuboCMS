<?php

namespace App\Repository;

use App\Entity\Category;
use App\Entity\ModelProfile;
use App\Entity\Video;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Video>
 */
class VideoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Video::class);
    }

    public function findPublished(int $limit = 20, int $offset = 0): array
    {
        return $this->createQueryBuilder('v')
            ->leftJoin('v.createdBy', 'u')
            ->leftJoin('v.categories', 'c')
            ->leftJoin('v.performers', 'p')
            ->addSelect('u', 'c', 'p')
            ->where('v.status = :status')
            ->setParameter('status', Video::STATUS_PUBLISHED)
            ->orderBy('v.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->useQueryCache(true)
            ->setResultCacheLifetime(60) // 1 минута для свежих данных
            ->getResult();
    }

    public function countPublished(): int
    {
        return $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->where('v.status = :status')
            ->setParameter('status', Video::STATUS_PUBLISHED)
            ->getQuery()
            ->useQueryCache(true)
            ->setResultCacheLifetime(600) // 10 минут для счетчиков
            ->getSingleScalarResult();
    }

    /**
     * Оптимизированный запрос для страницы просмотра видео
     * Загружает все связанные данные одним запросом
     */
    public function findOneWithRelations(string $slug): ?Video
    {
        return $this->createQueryBuilder('v')
            ->leftJoin('v.createdBy', 'u')
            ->leftJoin('v.categories', 'c')
            ->leftJoin('v.tags', 't')
            ->leftJoin('v.performers', 'p')
            ->leftJoin('v.encodedFiles', 'ef')
            ->leftJoin('ef.storage', 's')
            ->addSelect('u', 'c', 't', 'p', 'ef', 's')
            ->where('v.slug = :slug')
            ->andWhere('v.status = :status')
            ->setParameter('slug', $slug)
            ->setParameter('status', Video::STATUS_PUBLISHED)
            ->getQuery()
            ->useQueryCache(true)
            ->setResultCacheLifetime(300) // 5 минут
            ->getOneOrNullResult();
    }

    public function findFeatured(int $limit = 10): array
    {
        return $this->createQueryBuilder('v')
            ->leftJoin('v.createdBy', 'u')
            ->addSelect('u')
            ->leftJoin('v.categories', 'c')
            ->addSelect('c')
            ->leftJoin('v.performers', 'p')
            ->addSelect('p')
            ->where('v.status = :status')
            ->andWhere('v.isFeatured = :featured')
            ->setParameter('status', Video::STATUS_PUBLISHED)
            ->setParameter('featured', true)
            ->orderBy('v.viewsCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->useQueryCache(true)
            ->setResultCacheLifetime(300) // 5 минут
            ->getResult();
    }

    public function findByCategory(int $categoryId, int $limit = 20, int $offset = 0): array
    {
        return $this->createQueryBuilder('v')
            ->leftJoin('v.createdBy', 'u')
            ->leftJoin('v.categories', 'c')
            ->leftJoin('v.performers', 'p')
            ->addSelect('u', 'c', 'p')
            ->where('v.status = :status')
            ->andWhere('c.id = :category')
            ->setParameter('status', Video::STATUS_PUBLISHED)
            ->setParameter('category', $categoryId)
            ->orderBy('v.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function countByCategory(int $categoryId): int
    {
        return $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->innerJoin('v.categories', 'c')
            ->where('v.status = :status')
            ->andWhere('c.id = :category')
            ->setParameter('status', Video::STATUS_PUBLISHED)
            ->setParameter('category', $categoryId)
            ->getQuery()
            ->useQueryCache(true)
            ->setResultCacheLifetime(300)
            ->getSingleScalarResult();
    }

    public function findByTag(int $tagId, int $limit = 20, int $offset = 0): array
    {
        return $this->createQueryBuilder('v')
            ->innerJoin('v.tags', 't')
            ->leftJoin('v.createdBy', 'u')
            ->leftJoin('v.categories', 'c')
            ->leftJoin('v.performers', 'p')
            ->addSelect('u', 'c', 'p')
            ->where('v.status = :status')
            ->andWhere('t.id = :tag')
            ->setParameter('status', Video::STATUS_PUBLISHED)
            ->setParameter('tag', $tagId)
            ->orderBy('v.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function countByTag(int $tagId): int
    {
        return $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->innerJoin('v.tags', 't')
            ->where('v.status = :status')
            ->andWhere('t.id = :tag')
            ->setParameter('status', Video::STATUS_PUBLISHED)
            ->setParameter('tag', $tagId)
            ->getQuery()
            ->useQueryCache(true)
            ->setResultCacheLifetime(300)
            ->getSingleScalarResult();
    }

    public function findMostViewed(int $limit = 20): array
    {
        return $this->createQueryBuilder('v')
            ->leftJoin('v.createdBy', 'u')
            ->leftJoin('v.categories', 'c')
            ->leftJoin('v.performers', 'p')
            ->addSelect('u', 'c', 'p')
            ->where('v.status = :status')
            ->setParameter('status', Video::STATUS_PUBLISHED)
            ->orderBy('v.viewsCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->useQueryCache(true)
            ->setResultCacheLifetime(300)
            ->getResult();
    }

    public function findPopular(int $limit = 12): array
    {
        return $this->createQueryBuilder('v')
            ->leftJoin('v.createdBy', 'u')
            ->leftJoin('v.categories', 'c')
            ->leftJoin('v.performers', 'p')
            ->addSelect('u', 'c', 'p')
            ->where('v.status = :status')
            ->setParameter('status', Video::STATUS_PUBLISHED)
            ->orderBy('v.viewsCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->useQueryCache(true)
            ->setResultCacheLifetime(300)
            ->getResult();
    }

    public function findPopularPaginated(int $limit = 24, int $offset = 0): array
    {
        return $this->createQueryBuilder('v')
            ->leftJoin('v.createdBy', 'u')
            ->leftJoin('v.categories', 'c')
            ->leftJoin('v.performers', 'p')
            ->addSelect('u', 'c', 'p')
            ->where('v.status = :status')
            ->setParameter('status', Video::STATUS_PUBLISHED)
            ->orderBy('v.viewsCount', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function findTrending(int $limit = 24, int $offset = 0): array
    {
        /**
         * Улучшенный алгоритм трендов с учётом:
         * - Просмотров (базовый вес)
         * - Лайков и дизлайков (engagement)
         * - Комментариев (активность)
         * - Свежести видео (буст для новых видео)
         * 
         * Формула trending score:
         * score = (views * 1.0) + (likes * 5.0) - (dislikes * 2.0) + (comments * 3.0)
         * 
         * Свежесть учитывается через сортировку по дате после основного score
         */
        
        $weekAgo = new \DateTime('-7 days');
        
        return $this->createQueryBuilder('v')
            ->leftJoin('v.createdBy', 'u')
            ->leftJoin('v.categories', 'c')
            ->leftJoin('v.performers', 'p')
            ->addSelect('u', 'c', 'p')
            ->addSelect('
                (
                    (v.viewsCount * 1.0) + 
                    (v.likesCount * 5.0) - 
                    (v.dislikesCount * 2.0) + 
                    (v.commentsCount * 3.0)
                ) as HIDDEN trending_score
            ')
            ->where('v.status = :status')
            ->andWhere('v.createdAt >= :weekAgo')
            ->setParameter('status', Video::STATUS_PUBLISHED)
            ->setParameter('weekAgo', $weekAgo)
            ->orderBy('trending_score', 'DESC')
            ->addOrderBy('v.createdAt', 'DESC') // Свежие видео выше при равном score
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function findRecent(int $limit = 12): array
    {
        return $this->createQueryBuilder('v')
            ->leftJoin('v.createdBy', 'u')
            ->leftJoin('v.categories', 'c')
            ->leftJoin('v.performers', 'p')
            ->addSelect('u', 'c', 'p')
            ->where('v.status = :status')
            ->setParameter('status', Video::STATUS_PUBLISHED)
            ->orderBy('v.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->useQueryCache(true)
            ->setResultCacheLifetime(60) // Уменьшил с 300 до 60 секунд
            ->getResult();
    }

    public function findForHomePage(int $limit = 50): array
    {
        return $this->createQueryBuilder('v')
            ->leftJoin('v.createdBy', 'u')
            ->leftJoin('v.categories', 'c')
            ->leftJoin('v.performers', 'p')
            ->addSelect('u', 'c', 'p')
            ->where('v.status = :status')
            ->setParameter('status', Video::STATUS_PUBLISHED)
            ->orderBy('v.createdAt', 'DESC') // Основная сортировка по дате
            ->addOrderBy('v.viewsCount', 'DESC') // Дополнительная сортировка по просмотрам
            ->setMaxResults($limit)
            ->getQuery()
            ->useQueryCache(true)
            ->setResultCacheLifetime(60)
            ->getResult();
    }

    public function findRecentExcluding(int $limit = 12, array $excludeIds = []): array
    {
        $qb = $this->createQueryBuilder('v')
            ->leftJoin('v.createdBy', 'u')
            ->leftJoin('v.categories', 'c')
            ->leftJoin('v.performers', 'p')
            ->addSelect('u', 'c', 'p')
            ->where('v.status = :status')
            ->setParameter('status', Video::STATUS_PUBLISHED)
            ->orderBy('v.createdAt', 'DESC')
            ->setMaxResults($limit);

        if (!empty($excludeIds)) {
            $qb->andWhere('v.id NOT IN (:excludeIds)')
               ->setParameter('excludeIds', $excludeIds);
        }

        return $qb->getQuery()
            ->useQueryCache(true)
            ->setResultCacheLifetime(60)
            ->getResult();
    }

    public function findPopularExcluding(int $limit = 12, array $excludeIds = []): array
    {
        $qb = $this->createQueryBuilder('v')
            ->leftJoin('v.createdBy', 'u')
            ->leftJoin('v.categories', 'c')
            ->leftJoin('v.performers', 'p')
            ->addSelect('u', 'c', 'p')
            ->where('v.status = :status')
            ->setParameter('status', Video::STATUS_PUBLISHED)
            ->orderBy('v.viewsCount', 'DESC')
            ->setMaxResults($limit);

        if (!empty($excludeIds)) {
            $qb->andWhere('v.id NOT IN (:excludeIds)')
               ->setParameter('excludeIds', $excludeIds);
        }

        return $qb->getQuery()
            ->useQueryCache(true)
            ->setResultCacheLifetime(60)
            ->getResult();
    }

    /**
     * Полнотекстовый поиск видео с поддержкой тегов и категорий.
     * Использует MySQL FULLTEXT индекс для релевантного ранжирования.
     * 
     * @param string $query Поисковый запрос
     * @param array $filters Дополнительные фильтры ['category' => int, 'tag' => int, 'duration' => string]
     * @param int $limit Лимит результатов
     * @param int $offset Смещение для пагинации
     * @return array Массив видео с релевантностью
     */
    public function searchVideos(string $query, int $limit = 20, int $offset = 0, array $filters = []): array
    {
        $cleanQuery = $this->sanitizeSearchQuery($query);
        
        if (empty($cleanQuery)) {
            return [];
        }

        $conn = $this->getEntityManager()->getConnection();
        
        // Подготавливаем запрос для FULLTEXT поиска
        $fulltextQuery = $this->prepareFulltextQuery($cleanQuery);
        $likeQuery = '%' . $cleanQuery . '%';
        
        // Базовый SQL с FULLTEXT и релевантностью (позиционные параметры)
        $sql = "
            SELECT DISTINCT v.id,
                   MATCH(v.title, v.description) AGAINST(? IN BOOLEAN MODE) as relevance,
                   CASE WHEN v.title LIKE ? THEN 10 ELSE 0 END as title_boost,
                   v.views_count,
                   v.created_at
            FROM video v
            LEFT JOIN video_tag vt ON v.id = vt.video_id
            LEFT JOIN tag t ON vt.tag_id = t.id
            LEFT JOIN video_category vc ON v.id = vc.video_id
            LEFT JOIN category cat ON vc.category_id = cat.id
            WHERE v.status = ?
              AND (
                  MATCH(v.title, v.description) AGAINST(? IN BOOLEAN MODE)
                  OR v.title LIKE ?
                  OR t.name LIKE ?
                  OR cat.name LIKE ?
              )
        ";
        
        $params = [$fulltextQuery, $likeQuery, Video::STATUS_PUBLISHED, $fulltextQuery, $likeQuery, $likeQuery, $likeQuery];
        
        // Добавляем фильтры
        if (!empty($filters['category'])) {
            $sql .= " AND cat.id = ?";
            $params[] = $filters['category'];
        }
        
        if (!empty($filters['tag'])) {
            $sql .= " AND t.id = ?";
            $params[] = $filters['tag'];
        }
        
        if (!empty($filters['duration'])) {
            switch ($filters['duration']) {
                case 'short':
                    $sql .= " AND v.duration < 300";
                    break;
                case 'medium':
                    $sql .= " AND v.duration >= 300 AND v.duration <= 1200";
                    break;
                case 'long':
                    $sql .= " AND v.duration > 1200";
                    break;
            }
        }
        
        // Сортировка по релевантности + популярности
        $sql .= " ORDER BY (relevance + title_boost) DESC, v.views_count DESC, v.created_at DESC";
        $sql .= " LIMIT " . (int) $limit . " OFFSET " . (int) $offset;
        
        $result = $conn->executeQuery($sql, $params);
        $ids = array_column($result->fetchAllAssociative(), 'id');
        
        if (empty($ids)) {
            return [];
        }
        
        // Загружаем полные сущности с JOIN, сохраняя порядок
        $videos = $this->createQueryBuilder('v')
            ->leftJoin('v.createdBy', 'u')
            ->leftJoin('v.categories', 'c')
            ->leftJoin('v.performers', 'p')
            ->leftJoin('v.tags', 't')
            ->addSelect('u', 'c', 'p', 't')
            ->where('v.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
        
        // Сортируем по порядку ID из поиска
        $idOrder = array_flip($ids);
        usort($videos, fn($a, $b) => ($idOrder[$a->getId()] ?? 0) <=> ($idOrder[$b->getId()] ?? 0));
        
        return $videos;
    }

    /**
     * Подготавливает запрос для FULLTEXT поиска в BOOLEAN MODE
     */
    private function prepareFulltextQuery(string $query): string
    {
        $words = preg_split('/\s+/', trim($query));
        $prepared = [];
        
        foreach ($words as $word) {
            if (mb_strlen($word) >= 2) {
                // Добавляем + для обязательного вхождения и * для префиксного поиска
                $prepared[] = '+' . $word . '*';
            }
        }
        
        return implode(' ', $prepared);
    }

    private function sanitizeSearchQuery(string $query): string
    {
        // Удаляем потенциально опасные символы для LIKE
        $query = str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $query);
        // Удаляем HTML теги
        $query = strip_tags($query);
        // Оставляем только буквы, цифры, пробелы, дефисы и подчеркивания
        $query = preg_replace('/[^\p{L}\p{N}\s\-_]/u', '', $query);
        // Ограничиваем длину и убираем лишние пробелы
        return trim(substr($query, 0, 100));
    }

    /**
     * Подсчёт результатов полнотекстового поиска с учётом тегов и категорий
     */
    public function getSearchCount(string $query, array $filters = []): int
    {
        $cleanQuery = $this->sanitizeSearchQuery($query);
        
        if (empty($cleanQuery)) {
            return 0;
        }

        $conn = $this->getEntityManager()->getConnection();
        $fulltextQuery = $this->prepareFulltextQuery($cleanQuery);
        $likeQuery = '%' . $cleanQuery . '%';
        
        $sql = "
            SELECT COUNT(DISTINCT v.id)
            FROM video v
            LEFT JOIN video_tag vt ON v.id = vt.video_id
            LEFT JOIN tag t ON vt.tag_id = t.id
            LEFT JOIN video_category vc ON v.id = vc.video_id
            LEFT JOIN category cat ON vc.category_id = cat.id
            WHERE v.status = ?
              AND (
                  MATCH(v.title, v.description) AGAINST(? IN BOOLEAN MODE)
                  OR v.title LIKE ?
                  OR t.name LIKE ?
                  OR cat.name LIKE ?
              )
        ";
        
        $params = [Video::STATUS_PUBLISHED, $fulltextQuery, $likeQuery, $likeQuery, $likeQuery];
        
        if (!empty($filters['category'])) {
            $sql .= " AND cat.id = ?";
            $params[] = $filters['category'];
        }
        
        if (!empty($filters['tag'])) {
            $sql .= " AND t.id = ?";
            $params[] = $filters['tag'];
        }
        
        if (!empty($filters['duration'])) {
            switch ($filters['duration']) {
                case 'short':
                    $sql .= " AND v.duration < 300";
                    break;
                case 'medium':
                    $sql .= " AND v.duration >= 300 AND v.duration <= 1200";
                    break;
                case 'long':
                    $sql .= " AND v.duration > 1200";
                    break;
            }
        }
        
        $result = $conn->executeQuery($sql, $params);
        
        return (int) $result->fetchOne();
    }

    public function findForAdminList(?int $limit = 20, int $offset = 0, ?string $sort = null, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('v')
            ->leftJoin('v.createdBy', 'u')
            ->leftJoin('v.categories', 'c')
            ->leftJoin('v.performers', 'p')
            ->addSelect('u', 'c', 'p');
        
        // Фильтр по статусу
        if (!empty($filters['status'])) {
            $qb->andWhere('v.status = :status')
               ->setParameter('status', $filters['status']);
        }
        
        // Фильтр по категории
        if (!empty($filters['categoryId'])) {
            $qb->andWhere(':category MEMBER OF v.categories')
               ->setParameter('category', $filters['categoryId']);
        }
        
        // Фильтр по автору
        if (!empty($filters['authorId'])) {
            $qb->andWhere('v.createdBy = :author')
               ->setParameter('author', $filters['authorId']);
        }
        
        // Фильтр по дате (от)
        if (!empty($filters['dateFrom'])) {
            try {
                $dateFrom = new \DateTime($filters['dateFrom']);
                $qb->andWhere('v.createdAt >= :dateFrom')
                   ->setParameter('dateFrom', $dateFrom);
            } catch (\Exception $e) {
                // Игнорируем некорректную дату
            }
        }
        
        // Фильтр по дате (до)
        if (!empty($filters['dateTo'])) {
            try {
                $dateTo = new \DateTime($filters['dateTo']);
                $dateTo->setTime(23, 59, 59); // Конец дня
                $qb->andWhere('v.createdAt <= :dateTo')
                   ->setParameter('dateTo', $dateTo);
            } catch (\Exception $e) {
                // Игнорируем некорректную дату
            }
        }
        
        // Поиск по названию
        if (!empty($filters['search'])) {
            $qb->andWhere('v.title LIKE :search OR v.description LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }
        
        // Сортировка
        switch ($sort) {
            case 'ctr_desc':
                // CTR = views / impressions, сортируем по убыванию
                $qb->addSelect('CASE WHEN v.impressionsCount > 0 THEN (v.viewsCount * 1.0 / v.impressionsCount) ELSE 0 END AS HIDDEN ctr_value')
                   ->orderBy('ctr_value', 'DESC')
                   ->addOrderBy('v.viewsCount', 'DESC');
                break;
            case 'ctr_asc':
                $qb->addSelect('CASE WHEN v.impressionsCount > 0 THEN (v.viewsCount * 1.0 / v.impressionsCount) ELSE 0 END AS HIDDEN ctr_value')
                   ->orderBy('ctr_value', 'ASC');
                break;
            case 'views_desc':
                $qb->orderBy('v.viewsCount', 'DESC');
                break;
            case 'views_asc':
                $qb->orderBy('v.viewsCount', 'ASC');
                break;
            case 'impressions_desc':
                $qb->orderBy('v.impressionsCount', 'DESC');
                break;
            case 'impressions_asc':
                $qb->orderBy('v.impressionsCount', 'ASC');
                break;
            case 'date_asc':
                $qb->orderBy('v.createdAt', 'ASC');
                break;
            case 'date_desc':
            default:
                $qb->orderBy('v.createdAt', 'DESC');
                break;
        }
        
        // Применяем лимит только если он указан (для экспорта может быть null)
        if ($limit !== null) {
            $qb->setMaxResults($limit)
               ->setFirstResult($offset);
        }
        
        return $qb->getQuery()->getResult();
    }

    public function countForAdminList(array $filters = []): int
    {
        $qb = $this->createQueryBuilder('v')
            ->select('COUNT(v.id)');
        
        // Фильтр по статусу
        if (!empty($filters['status'])) {
            $qb->andWhere('v.status = :status')
               ->setParameter('status', $filters['status']);
        }
        
        // Фильтр по категории
        if (!empty($filters['categoryId'])) {
            $qb->leftJoin('v.categories', 'c')
               ->andWhere(':category MEMBER OF v.categories')
               ->setParameter('category', $filters['categoryId']);
        }
        
        // Фильтр по автору
        if (!empty($filters['authorId'])) {
            $qb->andWhere('v.createdBy = :author')
               ->setParameter('author', $filters['authorId']);
        }
        
        // Фильтр по дате (от)
        if (!empty($filters['dateFrom'])) {
            try {
                $dateFrom = new \DateTime($filters['dateFrom']);
                $qb->andWhere('v.createdAt >= :dateFrom')
                   ->setParameter('dateFrom', $dateFrom);
            } catch (\Exception $e) {
                // Игнорируем некорректную дату
            }
        }
        
        // Фильтр по дате (до)
        if (!empty($filters['dateTo'])) {
            try {
                $dateTo = new \DateTime($filters['dateTo']);
                $dateTo->setTime(23, 59, 59);
                $qb->andWhere('v.createdAt <= :dateTo')
                   ->setParameter('dateTo', $dateTo);
            } catch (\Exception $e) {
                // Игнорируем некорректную дату
            }
        }
        
        // Поиск по названию
        if (!empty($filters['search'])) {
            $qb->andWhere('v.title LIKE :search OR v.description LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }
        
        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Advanced filtering with multiple criteria
     * 
     * @param array $filters [
     *   'duration' => 'short'|'medium'|'long',
     *   'sort' => 'newest'|'oldest'|'popular'|'rating',
     *   'category' => int,
     *   'tag' => int,
     * ]
     * @return Video[]
     */
    public function findWithFilters(array $filters, int $limit = 24, int $offset = 0): array
    {
        $qb = $this->createQueryBuilder('v')
            ->leftJoin('v.createdBy', 'u')
            ->leftJoin('v.categories', 'c')
            ->leftJoin('v.performers', 'p')
            ->addSelect('u', 'c', 'p')
            ->where('v.status = :status')
            ->setParameter('status', Video::STATUS_PUBLISHED);

        // Duration filter
        if (!empty($filters['duration'])) {
            switch ($filters['duration']) {
                case 'short':
                    $qb->andWhere('v.duration < :maxDuration')
                       ->setParameter('maxDuration', 300); // < 5 min
                    break;
                case 'medium':
                    $qb->andWhere('v.duration >= :minDuration')
                       ->andWhere('v.duration <= :maxDuration')
                       ->setParameter('minDuration', 300)  // >= 5 min
                       ->setParameter('maxDuration', 1200); // <= 20 min
                    break;
                case 'long':
                    $qb->andWhere('v.duration > :minDuration')
                       ->setParameter('minDuration', 1200); // > 20 min
                    break;
            }
        }

        // Category filter (ManyToMany)
        if (!empty($filters['category'])) {
            $qb->leftJoin('v.categories', 'cat')
               ->andWhere('cat.id = :category')
               ->setParameter('category', $filters['category']);
        }

        // Tag filter
        if (!empty($filters['tag'])) {
            $qb->leftJoin('v.tags', 't')
               ->andWhere('t.id = :tag')
               ->setParameter('tag', $filters['tag']);
        }

        // Sorting
        $sort = $filters['sort'] ?? 'newest';
        switch ($sort) {
            case 'oldest':
                $qb->orderBy('v.createdAt', 'ASC');
                break;
            case 'popular':
                $qb->orderBy('v.viewsCount', 'DESC');
                break;
            case 'rating':
                // Sort by like ratio (likes / (likes + dislikes))
                $qb->addSelect('CASE WHEN (v.likesCount + v.dislikesCount) > 0 THEN (v.likesCount * 1.0 / (v.likesCount + v.dislikesCount)) ELSE 0 END AS HIDDEN rating')
                   ->orderBy('rating', 'DESC')
                   ->addOrderBy('v.likesCount', 'DESC');
                break;
            case 'newest':
            default:
                $qb->orderBy('v.createdAt', 'DESC');
                break;
        }

        return $qb->setMaxResults($limit)
                  ->setFirstResult($offset)
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Count videos with filters
     */
    public function countWithFilters(array $filters): int
    {
        $qb = $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->where('v.status = :status')
            ->setParameter('status', Video::STATUS_PUBLISHED);

        // Duration filter
        if (!empty($filters['duration'])) {
            switch ($filters['duration']) {
                case 'short':
                    $qb->andWhere('v.duration < :maxDuration')
                       ->setParameter('maxDuration', 300);
                    break;
                case 'medium':
                    $qb->andWhere('v.duration >= :minDuration')
                       ->andWhere('v.duration <= :maxDuration')
                       ->setParameter('minDuration', 300)
                       ->setParameter('maxDuration', 1200);
                    break;
                case 'long':
                    $qb->andWhere('v.duration > :minDuration')
                       ->setParameter('minDuration', 1200);
                    break;
            }
        }

        // Category filter (ManyToMany)
        if (!empty($filters['category'])) {
            $qb->leftJoin('v.categories', 'cat')
               ->andWhere('cat.id = :category')
               ->setParameter('category', $filters['category']);
        }

        // Tag filter
        if (!empty($filters['tag'])) {
            $qb->leftJoin('v.tags', 't')
               ->andWhere('t.id = :tag')
               ->setParameter('tag', $filters['tag']);
        }

        return $qb->getQuery()
            ->useQueryCache(true)
            ->setResultCacheLifetime(300)
            ->getSingleScalarResult();
    }

    /**
     * Оптимизированный поиск с полнотекстовым индексом
     */
    public function searchVideosOptimized(string $query, int $limit = 20, int $offset = 0): array
    {
        $cleanQuery = $this->sanitizeSearchQuery($query);
        
        // Используем MATCH AGAINST для полнотекстового поиска (если доступно)
        $qb = $this->createQueryBuilder('v')
            ->leftJoin('v.createdBy', 'u')
            ->addSelect('u')
            ->leftJoin('v.categories', 'c')
            ->addSelect('c')
            ->where('v.status = :status')
            ->setParameter('status', Video::STATUS_PUBLISHED);

        // Проверяем длину запроса для выбора стратегии поиска
        if (strlen($cleanQuery) >= 3) {
            // Для длинных запросов используем MATCH AGAINST
            $qb->andWhere('MATCH(v.title, v.description) AGAINST (:query IN BOOLEAN MODE)')
               ->setParameter('query', $cleanQuery . '*')
               ->orderBy('MATCH(v.title, v.description) AGAINST (:query2 IN BOOLEAN MODE)', 'DESC')
               ->setParameter('query2', $cleanQuery . '*');
        } else {
            // Для коротких запросов используем LIKE
            $qb->andWhere('(v.title LIKE :query OR v.description LIKE :query)')
               ->setParameter('query', '%' . $cleanQuery . '%')
               ->orderBy('v.viewsCount', 'DESC');
        }

        return $qb->addOrderBy('v.createdAt', 'DESC')
                  ->setMaxResults($limit)
                  ->setFirstResult($offset)
                  ->getQuery()
                  ->useQueryCache(true)
                  ->setResultCacheLifetime(120) // 2 минуты для поиска
                  ->getResult();
    }

    /**
     * Получить популярные видео с кэшированием по времени
     */
    public function findPopularCached(int $limit = 12, string $period = '24h'): array
    {
        $cacheTime = match($period) {
            '1h' => 300,   // 5 минут
            '24h' => 600,  // 10 минут
            '7d' => 1800,  // 30 минут
            default => 600
        };

        $qb = $this->createQueryBuilder('v')
            ->leftJoin('v.createdBy', 'u')
            ->leftJoin('v.categories', 'c')
            ->addSelect('u', 'c')
            ->where('v.status = :status')
            ->setParameter('status', Video::STATUS_PUBLISHED);

        // Добавляем фильтр по времени если нужно
        if ($period !== 'all') {
            $date = match($period) {
                '1h' => new \DateTime('-1 hour'),
                '24h' => new \DateTime('-1 day'),
                '7d' => new \DateTime('-7 days'),
                default => new \DateTime('-1 day')
            };
            
            $qb->andWhere('v.createdAt >= :date')
               ->setParameter('date', $date);
        }

        return $qb->orderBy('v.viewsCount', 'DESC')
                  ->addOrderBy('v.createdAt', 'DESC')
                  ->setMaxResults($limit)
                  ->getQuery()
                  ->useQueryCache(true)
                  ->setResultCacheLifetime($cacheTime)
                  ->getResult();
    }

    /**
     * Получить статистику по категориям (оптимизированно)
     */
    public function getCategoryStats(): array
    {
        return $this->createQueryBuilder('v')
            ->select('c.id, c.name, COUNT(v.id) as video_count')
            ->leftJoin('v.categories', 'c')
            ->where('v.status = :status')
            ->setParameter('status', Video::STATUS_PUBLISHED)
            ->groupBy('c.id, c.name')
            ->orderBy('video_count', 'DESC')
            ->getQuery()
            ->useQueryCache(true)
            ->setResultCacheLifetime(3600) // 1 час для статистики
            ->getResult();
    }

    /**
     * Поиск видео по названию для Live Component
     */
    public function searchByTitle(string $query, int $limit = 10): array
    {
        $cleanQuery = $this->sanitizeSearchQuery($query);
        
        if (empty($cleanQuery)) {
            return [];
        }

        return $this->createQueryBuilder('v')
            ->leftJoin('v.createdBy', 'u')
            ->leftJoin('v.categories', 'c')
            ->addSelect('u', 'c')
            ->where('v.status = :status')
            ->andWhere('v.title LIKE :query')
            ->setParameter('status', Video::STATUS_PUBLISHED)
            ->setParameter('query', '%' . $cleanQuery . '%')
            ->orderBy('v.viewsCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->useQueryCache(true)
            ->setResultCacheLifetime(60)
            ->getResult();
    }

    /**
     * Найти застрявшие видео (в статусе processing более 1 часа)
     */
    public function findStuckVideos(\DateTimeInterface $threshold): array
    {
        return $this->createQueryBuilder('v')
            ->where('v.status = :status')
            ->andWhere('v.updatedAt < :threshold')
            ->setParameter('status', 'processing')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getResult();
    }

    /**
     * Получить статистику загрузок по дням
     */
    public function getUploadStats(int $days = 30): array
    {
        $startDate = new \DateTime("-{$days} days");
        
        return $this->createQueryBuilder('v')
            ->select("DATE(v.createdAt) as date, COUNT(v.id) as count")
            ->where('v.createdAt >= :startDate')
            ->setParameter('startDate', $startDate)
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Получить статистику просмотров по дням
     */
    public function getViewsStats(int $days = 30): array
    {
        $startDate = new \DateTime("-{$days} days");
        
        return $this->createQueryBuilder('v')
            ->select("DATE(v.createdAt) as date, SUM(v.viewsCount) as count")
            ->where('v.createdAt >= :startDate')
            ->setParameter('startDate', $startDate)
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Оптимизированный поиск связанных видео по тегам и категории
     */
    public function findRelatedByTagsAndCategory(array $tags, ?Category $category, int $excludeVideoId, int $limit = 12): array
    {
        $qb = $this->createQueryBuilder('v')
            ->leftJoin('v.createdBy', 'u')
            ->leftJoin('v.categories', 'c')
            ->leftJoin('v.performers', 'p')
            ->addSelect('u', 'c', 'p')
            ->where('v.status = :status')
            ->andWhere('v.id != :excludeId')
            ->setParameter('status', Video::STATUS_PUBLISHED)
            ->setParameter('excludeId', $excludeVideoId);

        // Если есть теги, ищем по ним
        if (!empty($tags)) {
            $tagIds = array_map(fn($tag) => $tag->getId(), $tags);
            $qb->leftJoin('v.tags', 't')
               ->andWhere('t.id IN (:tagIds)')
               ->setParameter('tagIds', $tagIds)
               ->orderBy('v.viewsCount', 'DESC');
        } elseif ($category) {
            // Если нет тегов, но есть категория
            $qb->leftJoin('v.categories', 'cat')
               ->andWhere('cat.id = :categoryId')
               ->setParameter('categoryId', $category->getId())
               ->orderBy('v.viewsCount', 'DESC');
        } else {
            // Если нет ни тегов ни категории, просто сортируем по популярности
            $qb->orderBy('v.viewsCount', 'DESC');
        }

        return $qb->setMaxResults($limit)
                  ->getQuery()
                  ->useQueryCache(true)
                  ->setResultCacheLifetime(300) // 5 минут
                  ->getResult();
    }

    /**
     * Оптимизированный поиск видео с похожими тегами
     */
    public function findWithSimilarTags(array $tagIds, int $excludeVideoId, int $limit = 6): array
    {
        if (empty($tagIds)) {
            return [];
        }

        return $this->createQueryBuilder('v')
            ->leftJoin('v.createdBy', 'u')
            ->leftJoin('v.categories', 'c')
            ->leftJoin('v.tags', 't')
            ->addSelect('u', 'c')
            ->where('v.status = :status')
            ->andWhere('v.id != :excludeId')
            ->andWhere('t.id IN (:tagIds)')
            ->setParameter('status', Video::STATUS_PUBLISHED)
            ->setParameter('excludeId', $excludeVideoId)
            ->setParameter('tagIds', $tagIds)
            ->orderBy('v.viewsCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->useQueryCache(true)
            ->setResultCacheLifetime(300)
            ->getResult();
    }

    /**
     * Оптимизированный поиск других видео модели
     */
    public function findOtherVideosForModel(ModelProfile $model, Video $excludeVideo, int $limit = 3): array
    {
        return $this->createQueryBuilder('v')
            ->leftJoin('v.createdBy', 'u')
            ->leftJoin('v.categories', 'c')
            ->leftJoin('v.performers', 'p')
            ->addSelect('u', 'c')
            ->where('v.status = :status')
            ->andWhere('v.id != :excludeId')
            ->andWhere('p.id = :modelId')
            ->setParameter('status', Video::STATUS_PUBLISHED)
            ->setParameter('excludeId', $excludeVideo->getId())
            ->setParameter('modelId', $model->getId())
            ->orderBy('v.viewsCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->useQueryCache(true)
            ->setResultCacheLifetime(300)
            ->getResult();
    }

    /**
     * Оптимизированный метод для получения рекомендуемых видео на главной
     */
    public function findFeaturedForHome(int $limit = 10): array
    {
        return $this->createQueryBuilder('v')
            ->leftJoin('v.createdBy', 'u')
            ->leftJoin('v.categories', 'c')
            ->addSelect('u', 'c')
            ->where('v.status = :status')
            ->andWhere('v.isFeatured = :featured')
            ->setParameter('status', Video::STATUS_PUBLISHED)
            ->setParameter('featured', true)
            ->orderBy('v.viewsCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->useQueryCache(true)
            ->setResultCacheLifetime(300) // 5 минут
            ->getResult();
    }

    /**
     * Оптимизированный метод для получения новых видео на главной
     */
    public function findNewestForHome(int $limit = 12): array
    {
        return $this->createQueryBuilder('v')
            ->leftJoin('v.createdBy', 'u')
            ->leftJoin('v.categories', 'c')
            ->addSelect('u', 'c')
            ->where('v.status = :status')
            ->setParameter('status', Video::STATUS_PUBLISHED)
            ->orderBy('v.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->useQueryCache(true)
            ->setResultCacheLifetime(120) // 2 минуты для новых видео
            ->getResult();
    }

    /**
     * Оптимизированный метод для получения популярных видео на главной
     */
    public function findPopularForHome(int $limit = 12): array
    {
        return $this->createQueryBuilder('v')
            ->leftJoin('v.createdBy', 'u')
            ->leftJoin('v.categories', 'c')
            ->addSelect('u', 'c')
            ->where('v.status = :status')
            ->setParameter('status', Video::STATUS_PUBLISHED)
            ->orderBy('v.viewsCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->useQueryCache(true)
            ->setResultCacheLifetime(300) // 5 минут
            ->getResult();
    }

    /**
     * Оптимизированный метод для получения недавно просмотренных видео
     */
    public function findRecentlyWatchedForUser($user, int $limit = 8): array
    {
        return $this->createQueryBuilder('v')
            ->leftJoin('v.createdBy', 'u')
            ->leftJoin('v.categories', 'c')
            ->leftJoin('App\Entity\WatchHistory', 'wh', 'WITH', 'wh.video = v')
            ->addSelect('u', 'c')
            ->where('v.status = :status')
            ->andWhere('wh.user = :user')
            ->setParameter('status', Video::STATUS_PUBLISHED)
            ->setParameter('user', $user)
            ->orderBy('wh.watchedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Получить видео по исполнителю/модели
     */
    public function findByPerformer(int $performerId, int $limit = 12): array
    {
        return $this->createQueryBuilder('v')
            ->leftJoin('v.createdBy', 'u')
            ->leftJoin('v.categories', 'c')
            ->leftJoin('v.performers', 'p')
            ->addSelect('u', 'c')
            ->where('v.status = :status')
            ->andWhere('p.id = :performerId')
            ->setParameter('status', Video::STATUS_PUBLISHED)
            ->setParameter('performerId', $performerId)
            ->orderBy('v.viewsCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->useQueryCache(true)
            ->setResultCacheLifetime(300)
            ->getResult();
    }

    /**
     * Найти видео по каналу
     */
    public function findByChannel($channel, int $limit = 20, int $offset = 0): array
    {
        return $this->createQueryBuilder('v')
            ->leftJoin('v.createdBy', 'u')
            ->leftJoin('v.categories', 'c')
            ->leftJoin('v.performers', 'p')
            ->addSelect('u', 'c', 'p')
            ->where('v.status = :status')
            ->andWhere('v.channel = :channel')
            ->setParameter('status', Video::STATUS_PUBLISHED)
            ->setParameter('channel', $channel)
            ->orderBy('v.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Подсчет видео по каналу
     */
    public function countByChannel($channel): int
    {
        return $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->where('v.status = :status')
            ->andWhere('v.channel = :channel')
            ->setParameter('status', Video::STATUS_PUBLISHED)
            ->setParameter('channel', $channel)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Найти популярные видео канала
     */
    public function findPopularByChannel($channel, int $limit = 10): array
    {
        return $this->createQueryBuilder('v')
            ->leftJoin('v.createdBy', 'u')
            ->leftJoin('v.categories', 'c')
            ->addSelect('u', 'c')
            ->where('v.status = :status')
            ->andWhere('v.channel = :channel')
            ->setParameter('status', Video::STATUS_PUBLISHED)
            ->setParameter('channel', $channel)
            ->orderBy('v.viewsCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->useQueryCache(true)
            ->setResultCacheLifetime(300)
            ->getResult();
    }

    /**
     * Найти последние видео канала
     */
    public function findRecentByChannel($channel, int $limit = 10): array
    {
        return $this->createQueryBuilder('v')
            ->leftJoin('v.createdBy', 'u')
            ->leftJoin('v.categories', 'c')
            ->addSelect('u', 'c')
            ->where('v.status = :status')
            ->andWhere('v.channel = :channel')
            ->setParameter('status', Video::STATUS_PUBLISHED)
            ->setParameter('channel', $channel)
            ->orderBy('v.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->useQueryCache(true)
            ->setResultCacheLifetime(120)
            ->getResult();
    }

    /**
     * Подсчет видео за период
     */
    public function countByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): int
    {
        return (int) $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->where('v.createdAt >= :startDate')
            ->andWhere('v.createdAt < :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Сумма просмотров за период
     */
    public function sumViewsByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): int
    {
        $result = $this->createQueryBuilder('v')
            ->select('SUM(v.viewsCount)')
            ->where('v.createdAt >= :startDate')
            ->andWhere('v.createdAt < :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getSingleScalarResult();
        
        return (int) ($result ?? 0);
    }

    /**
     * Сумма лайков за период
     */
    public function sumLikesByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): int
    {
        $result = $this->createQueryBuilder('v')
            ->select('SUM(v.likesCount)')
            ->where('v.createdAt >= :startDate')
            ->andWhere('v.createdAt < :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getSingleScalarResult();
        
        return (int) ($result ?? 0);
    }

    /**
     * Подсчет комментариев за период
     */
    public function countCommentsByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): int
    {
        $result = $this->createQueryBuilder('v')
            ->select('SUM(v.commentsCount)')
            ->where('v.createdAt >= :startDate')
            ->andWhere('v.createdAt < :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getSingleScalarResult();
        
        return (int) ($result ?? 0);
    }
}
