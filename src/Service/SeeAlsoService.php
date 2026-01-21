<?php

namespace App\Service;

use App\Entity\Category;
use App\Entity\Tag;
use App\Entity\Video;
use App\Entity\ModelProfile;
use App\Repository\VideoRepository;
use App\Repository\TagRepository;
use App\Repository\ModelProfileRepository;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class SeeAlsoService
{
    public function __construct(
        private VideoRepository $videoRepository,
        private TagRepository $tagRepository,
        private ModelProfileRepository $modelProfileRepository,
        private CacheInterface $cache
    ) {
    }

    /**
     * Получить видео с похожими тегами
     */
    public function getVideosWithSimilarTags(array $tags, Video $excludeVideo, int $limit = 6): array
    {
        if (empty($tags)) {
            return [];
        }

        $tagIds = array_map(fn($tag) => $tag->getId(), $tags);
        
        return $this->cache->get(
            "similar_tags_videos_{$excludeVideo->getId()}_{$limit}",
            function (ItemInterface $item) use ($tagIds, $excludeVideo, $limit) {
                $item->expiresAfter(300); // 5 минут
                
                return $this->videoRepository->findWithSimilarTags(
                    $tagIds,
                    $excludeVideo->getId(),
                    $limit
                );
            }
        );
    }

    /**
     * Получить другие видео модели
     */
    public function getOtherVideosForModel(ModelProfile $model, ?Video $excludeVideo = null, int $limit = 3): array
    {
        $cacheKey = "model_videos_{$model->getId()}_" . ($excludeVideo ? $excludeVideo->getId() : 'all') . "_{$limit}";
        
        return $this->cache->get(
            $cacheKey,
            function (ItemInterface $item) use ($model, $excludeVideo, $limit) {
                $item->expiresAfter(300); // 5 минут
                
                if (!$excludeVideo) {
                    // Если видео не указано, просто получаем видео модели
                    return $this->videoRepository->findByPerformer($model->getId(), $limit);
                }
                
                return $this->videoRepository->findOtherVideosForModel(
                    $model,
                    $excludeVideo,
                    $limit
                );
            }
        );
    }

    /**
     * Получить популярные видео в категории
     */
    public function getPopularVideosForCategory(Category $category, int $limit = 6): array
    {
        return $this->cache->get(
            "popular_videos_category_{$category->getId()}_{$limit}",
            function (ItemInterface $item) use ($category, $limit) {
                $item->expiresAfter(300); // 5 минут
                
                $conn = $this->videoRepository->getEntityManager()->getConnection();
                $sql = "
                    SELECT v.id
                    FROM video v
                    INNER JOIN video_category vc ON v.id = vc.video_id
                    WHERE vc.category_id = :categoryId
                    AND v.status = :status
                    ORDER BY v.likes_count DESC, v.impressions_count DESC
                    LIMIT " . (int)$limit . "
                ";
                
                $result = $conn->executeQuery($sql, [
                    'categoryId' => $category->getId(),
                    'status' => 'published'
                ]);
                $videoIds = array_column($result->fetchAllAssociative(), 'id');
                
                if (empty($videoIds)) {
                    return [];
                }
                
                return $this->videoRepository->findBy(['id' => $videoIds]);
            }
        );
    }

    /**
     * Получить популярные теги в категории
     */
    public function getPopularTagsForCategory(Category $category, int $limit = 10): array
    {
        return $this->cache->get(
            "popular_tags_category_{$category->getId()}_{$limit}",
            function (ItemInterface $item) use ($category, $limit) {
                $item->expiresAfter(600); // 10 минут
                
                $conn = $this->videoRepository->getEntityManager()->getConnection();
                $sql = "
                    SELECT DISTINCT t.id, t.name, COUNT(vt.tag_id) as tag_count
                    FROM tag t
                    INNER JOIN video_tag vt ON t.id = vt.tag_id
                    INNER JOIN video v ON vt.video_id = v.id
                    INNER JOIN video_category vc ON v.id = vc.video_id
                    WHERE vc.category_id = :categoryId
                    AND v.status = :status
                    GROUP BY t.id, t.name
                    ORDER BY tag_count DESC, t.name ASC
                    LIMIT " . (int)$limit . "
                ";
                
                $result = $conn->executeQuery($sql, [
                    'categoryId' => $category->getId(),
                    'status' => 'published'
                ]);
                $tagIds = array_column($result->fetchAllAssociative(), 'id');
                
                if (empty($tagIds)) {
                    return [];
                }
                
                return $this->tagRepository->findBy(['id' => $tagIds]);
            }
        );
    }

    /**
     * Получить популярные модели в категории
     */
    public function getPopularModelsForCategory(Category $category, int $limit = 8): array
    {
        return $this->cache->get(
            "popular_models_category_{$category->getId()}_{$limit}",
            function (ItemInterface $item) use ($category, $limit) {
                $item->expiresAfter(600); // 10 минут
                
                $conn = $this->videoRepository->getEntityManager()->getConnection();
                $sql = "
                    SELECT DISTINCT mp.id, mp.display_name, COUNT(vm.model_profile_id) as model_count
                    FROM model_profile mp
                    INNER JOIN video_model vm ON mp.id = vm.model_profile_id
                    INNER JOIN video v ON vm.video_id = v.id
                    INNER JOIN video_category vc ON v.id = vc.video_id
                    WHERE vc.category_id = :categoryId
                    AND v.status = :status
                    GROUP BY mp.id, mp.display_name
                    ORDER BY model_count DESC, mp.display_name ASC
                    LIMIT " . (int)$limit . "
                ";
                
                $result = $conn->executeQuery($sql, [
                    'categoryId' => $category->getId(),
                    'status' => 'published'
                ]);
                $modelIds = array_column($result->fetchAllAssociative(), 'id');
                
                if (empty($modelIds)) {
                    return [];
                }
                
                return $this->modelProfileRepository->findBy(['id' => $modelIds]);
            }
        );
    }

    /**
     * Получить связанные категории для модели
     */
    public function getRelatedCategoriesForModel(ModelProfile $model, int $limit = 6): array
    {
        return $this->cache->get(
            "related_categories_model_{$model->getId()}_{$limit}",
            function (ItemInterface $item) use ($model, $limit) {
                $item->expiresAfter(600); // 10 минут
                
                // Получаем категории, в которых есть видео этой модели
                // Используем прямой SQL запрос для оптимизации
                $conn = $this->videoRepository->getEntityManager()->getConnection();
                $sql = "
                    SELECT DISTINCT c.id, c.name
                    FROM category c
                    INNER JOIN video_category vc ON c.id = vc.category_id
                    INNER JOIN video v ON vc.video_id = v.id
                    INNER JOIN video_model vm ON v.id = vm.video_id
                    WHERE vm.model_profile_id = :modelId
                    AND v.status = :status
                    ORDER BY c.name ASC
                    LIMIT " . (int)$limit . "
                ";
                
                $result = $conn->executeQuery($sql, [
                    'modelId' => $model->getId(),
                    'status' => 'published'
                ]);
                $categoryIds = array_column($result->fetchAllAssociative(), 'id');
                
                if (empty($categoryIds)) {
                    return [];
                }
                
                return $this->videoRepository->getEntityManager()
                    ->getRepository(Category::class)
                    ->findBy(['id' => $categoryIds]);
            }
        );
    }

    /**
     * Получить связанные теги для модели
     */
    public function getRelatedTagsForModel(ModelProfile $model, int $limit = 10): array
    {
        return $this->cache->get(
            "related_tags_model_{$model->getId()}_{$limit}",
            function (ItemInterface $item) use ($model, $limit) {
                $item->expiresAfter(600); // 10 минут
                
                // Получаем теги, которые часто встречаются в видео этой модели
                $conn = $this->videoRepository->getEntityManager()->getConnection();
                $sql = "
                    SELECT DISTINCT t.id, t.name, COUNT(vt.tag_id) as tag_count
                    FROM tag t
                    INNER JOIN video_tag vt ON t.id = vt.tag_id
                    INNER JOIN video v ON vt.video_id = v.id
                    INNER JOIN video_model vm ON v.id = vm.video_id
                    WHERE vm.model_profile_id = :modelId
                    AND v.status = :status
                    GROUP BY t.id, t.name
                    ORDER BY tag_count DESC, t.name ASC
                    LIMIT " . (int)$limit . "
                ";
                
                $result = $conn->executeQuery($sql, [
                    'modelId' => $model->getId(),
                    'status' => 'published'
                ]);
                $tagIds = array_column($result->fetchAllAssociative(), 'id');
                
                if (empty($tagIds)) {
                    return [];
                }
                
                return $this->videoRepository->getEntityManager()
                    ->getRepository(Tag::class)
                    ->findBy(['id' => $tagIds]);
            }
        );
    }

    /**
     * Получить связанные категории для тега
     */
    public function getRelatedCategoriesForTag(Tag $tag, int $limit = 6): array
    {
        return $this->cache->get(
            "related_categories_tag_{$tag->getId()}_{$limit}",
            function (ItemInterface $item) use ($tag, $limit) {
                $item->expiresAfter(600); // 10 минут
                
                // Получаем категории, в которых есть видео с этим тегом
                $conn = $this->videoRepository->getEntityManager()->getConnection();
                $sql = "
                    SELECT DISTINCT c.id, c.name, COUNT(vc.category_id) as category_count
                    FROM category c
                    INNER JOIN video_category vc ON c.id = vc.category_id
                    INNER JOIN video v ON vc.video_id = v.id
                    INNER JOIN video_tag vt ON v.id = vt.video_id
                    WHERE vt.tag_id = :tagId
                    AND v.status = :status
                    GROUP BY c.id, c.name
                    ORDER BY category_count DESC, c.name ASC
                    LIMIT " . (int)$limit . "
                ";
                
                $result = $conn->executeQuery($sql, [
                    'tagId' => $tag->getId(),
                    'status' => 'published'
                ]);
                $categoryIds = array_column($result->fetchAllAssociative(), 'id');
                
                if (empty($categoryIds)) {
                    return [];
                }
                
                return $this->videoRepository->getEntityManager()
                    ->getRepository(Category::class)
                    ->findBy(['id' => $categoryIds]);
            }
        );
    }

    /**
     * Получить связанные модели для тега
     */
    public function getRelatedModelsForTag(Tag $tag, int $limit = 8): array
    {
        return $this->cache->get(
            "related_models_tag_{$tag->getId()}_{$limit}",
            function (ItemInterface $item) use ($tag, $limit) {
                $item->expiresAfter(600); // 10 минут
                
                // Получаем модели, которые часто встречаются в видео с этим тегом
                $conn = $this->videoRepository->getEntityManager()->getConnection();
                $sql = "
                    SELECT DISTINCT mp.id, mp.display_name, COUNT(vm.model_profile_id) as model_count
                    FROM model_profile mp
                    INNER JOIN video_model vm ON mp.id = vm.model_profile_id
                    INNER JOIN video v ON vm.video_id = v.id
                    INNER JOIN video_tag vt ON v.id = vt.video_id
                    WHERE vt.tag_id = :tagId
                    AND v.status = :status
                    GROUP BY mp.id, mp.display_name
                    ORDER BY model_count DESC, mp.display_name ASC
                    LIMIT " . (int)$limit . "
                ";
                
                $result = $conn->executeQuery($sql, [
                    'tagId' => $tag->getId(),
                    'status' => 'published'
                ]);
                $modelIds = array_column($result->fetchAllAssociative(), 'id');
                
                if (empty($modelIds)) {
                    return [];
                }
                
                return $this->modelProfileRepository->findBy(['id' => $modelIds]);
            }
        );
    }

}