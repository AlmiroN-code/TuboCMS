<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Category;
use App\Entity\ModelProfile;
use App\Entity\Tag;
use App\Entity\Video;
use App\Repository\CategoryRepository;
use App\Repository\ModelProfileRepository;
use App\Repository\TagRepository;
use App\Repository\VideoRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Сервис универсального поиска с автодополнением.
 * Поддерживает поиск по видео, тегам, категориям и моделям.
 */
class SearchService
{
    public function __construct(
        private readonly VideoRepository $videoRepository,
        private readonly TagRepository $tagRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly ModelProfileRepository $modelRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Универсальный поиск для автодополнения.
     * Возвращает результаты из всех сущностей, сгруппированные по типу.
     * 
     * @return array{
     *   videos: array,
     *   tags: array,
     *   categories: array,
     *   models: array,
     *   total: int
     * }
     */
    public function autocomplete(string $query, int $limit = 10): array
    {
        $query = $this->sanitizeQuery($query);
        
        if (mb_strlen($query) < 2) {
            return [
                'videos' => [],
                'tags' => [],
                'categories' => [],
                'models' => [],
                'total' => 0,
            ];
        }

        // Распределяем лимит между типами
        $videoLimit = (int) ceil($limit * 0.5);  // 50% для видео
        $tagLimit = (int) ceil($limit * 0.2);    // 20% для тегов
        $categoryLimit = (int) ceil($limit * 0.15); // 15% для категорий
        $modelLimit = (int) ceil($limit * 0.15);    // 15% для моделей

        $videos = $this->searchVideosForAutocomplete($query, $videoLimit);
        $tags = $this->searchTags($query, $tagLimit);
        $categories = $this->searchCategories($query, $categoryLimit);
        $models = $this->searchModels($query, $modelLimit);

        return [
            'videos' => $videos,
            'tags' => $tags,
            'categories' => $categories,
            'models' => $models,
            'total' => count($videos) + count($tags) + count($categories) + count($models),
        ];
    }

    /**
     * Поиск видео для автодополнения с FULLTEXT
     */
    private function searchVideosForAutocomplete(string $query, int $limit): array
    {
        $conn = $this->em->getConnection();
        
        $fulltextQuery = $this->prepareFulltextQuery($query);
        $likeQuery = '%' . $query . '%';
        
        // Используем позиционные параметры с типами
        $sql = "
            SELECT v.id, v.title, v.slug, v.poster, v.views_count, v.duration,
                   MATCH(v.title, v.description) AGAINST(? IN BOOLEAN MODE) as relevance
            FROM video v
            WHERE v.status = ?
              AND (
                  MATCH(v.title, v.description) AGAINST(? IN BOOLEAN MODE)
                  OR v.title LIKE ?
              )
            ORDER BY relevance DESC, v.views_count DESC
            LIMIT " . (int) $limit;
        
        $result = $conn->executeQuery($sql, [
            $fulltextQuery,
            Video::STATUS_PUBLISHED,
            $fulltextQuery,
            $likeQuery,
        ]);
        
        $rows = $result->fetchAllAssociative();
        
        return array_map(function ($row) {
            return [
                'id' => (int) $row['id'],
                'title' => $row['title'],
                'slug' => $row['slug'],
                'poster' => $row['poster'],
                'views' => (int) $row['views_count'],
                'duration' => $this->formatDuration((int) $row['duration']),
                'type' => 'video',
            ];
        }, $rows);
    }

    /**
     * Поиск тегов с FULLTEXT
     */
    private function searchTags(string $query, int $limit): array
    {
        $conn = $this->em->getConnection();
        
        $fulltextQuery = $this->prepareFulltextQuery($query);
        $likeQuery = '%' . $query . '%';
        
        $sql = "
            SELECT t.id, t.name, t.slug, COUNT(vt.video_id) as video_count
            FROM tag t
            LEFT JOIN video_tag vt ON t.id = vt.tag_id
            LEFT JOIN video v ON vt.video_id = v.id AND v.status = ?
            WHERE MATCH(t.name) AGAINST(? IN BOOLEAN MODE)
               OR t.name LIKE ?
            GROUP BY t.id, t.name, t.slug
            HAVING video_count > 0
            ORDER BY video_count DESC
            LIMIT " . (int) $limit;
        
        $result = $conn->executeQuery($sql, [
            Video::STATUS_PUBLISHED,
            $fulltextQuery,
            $likeQuery,
        ]);
        
        $rows = $result->fetchAllAssociative();
        
        return array_map(function ($row) {
            return [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'slug' => $row['slug'],
                'videoCount' => (int) $row['video_count'],
                'type' => 'tag',
            ];
        }, $rows);
    }

    /**
     * Поиск категорий с FULLTEXT
     */
    private function searchCategories(string $query, int $limit): array
    {
        $conn = $this->em->getConnection();
        
        $fulltextQuery = $this->prepareFulltextQuery($query);
        $likeQuery = '%' . $query . '%';
        
        $sql = "
            SELECT c.id, c.name, c.slug, c.poster, COUNT(vc.video_id) as video_count
            FROM category c
            LEFT JOIN video_category vc ON c.id = vc.category_id
            LEFT JOIN video v ON vc.video_id = v.id AND v.status = ?
            WHERE MATCH(c.name, c.description) AGAINST(? IN BOOLEAN MODE)
               OR c.name LIKE ?
            GROUP BY c.id, c.name, c.slug, c.poster
            HAVING video_count > 0
            ORDER BY video_count DESC
            LIMIT " . (int) $limit;
        
        $result = $conn->executeQuery($sql, [
            Video::STATUS_PUBLISHED,
            $fulltextQuery,
            $likeQuery,
        ]);
        
        $rows = $result->fetchAllAssociative();
        
        return array_map(function ($row) {
            return [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'slug' => $row['slug'],
                'poster' => $row['poster'],
                'videoCount' => (int) $row['video_count'],
                'type' => 'category',
            ];
        }, $rows);
    }

    /**
     * Поиск моделей с FULLTEXT
     */
    private function searchModels(string $query, int $limit): array
    {
        $conn = $this->em->getConnection();
        
        $fulltextQuery = $this->prepareFulltextQuery($query);
        $likeQuery = '%' . $query . '%';
        
        $sql = "
            SELECT m.id, m.display_name, m.slug, m.avatar, COUNT(vm.video_id) as video_count
            FROM model_profile m
            LEFT JOIN video_model vm ON m.id = vm.model_id
            LEFT JOIN video v ON vm.video_id = v.id AND v.status = ?
            WHERE m.is_active = 1
              AND (
                  MATCH(m.display_name, m.bio) AGAINST(? IN BOOLEAN MODE)
                  OR m.display_name LIKE ?
              )
            GROUP BY m.id, m.display_name, m.slug, m.avatar
            ORDER BY video_count DESC
            LIMIT " . (int) $limit;
        
        $result = $conn->executeQuery($sql, [
            Video::STATUS_PUBLISHED,
            $fulltextQuery,
            $likeQuery,
        ]);
        
        $rows = $result->fetchAllAssociative();
        
        return array_map(function ($row) {
            return [
                'id' => (int) $row['id'],
                'name' => $row['display_name'],
                'slug' => $row['slug'],
                'avatar' => $row['avatar'],
                'videoCount' => (int) $row['video_count'],
                'type' => 'model',
            ];
        }, $rows);
    }

    /**
     * Подготавливает запрос для FULLTEXT поиска
     */
    private function prepareFulltextQuery(string $query): string
    {
        $words = preg_split('/\s+/', trim($query));
        $prepared = [];
        
        foreach ($words as $word) {
            if (mb_strlen($word) >= 2) {
                $prepared[] = '+' . $word . '*';
            }
        }
        
        return implode(' ', $prepared);
    }

    /**
     * Очистка поискового запроса
     */
    private function sanitizeQuery(string $query): string
    {
        $query = strip_tags($query);
        $query = preg_replace('/[^\p{L}\p{N}\s\-_]/u', '', $query);
        return trim(mb_substr($query, 0, 100));
    }

    /**
     * Форматирование длительности
     */
    private function formatDuration(int $seconds): string
    {
        $hours = (int) floor($seconds / 3600);
        $minutes = (int) floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
        }
        return sprintf('%02d:%02d', $minutes, $secs);
    }

    /**
     * Получить популярные поисковые запросы (на основе тегов)
     */
    public function getPopularSearches(int $limit = 10): array
    {
        return $this->tagRepository->findMostPopular($limit);
    }
}
