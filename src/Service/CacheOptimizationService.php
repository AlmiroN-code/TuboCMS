<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Psr\Log\LoggerInterface;

/**
 * Сервис для оптимизации кеширования
 */
class CacheOptimizationService
{
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly TagAwareCacheInterface $tagAwareCache,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Простой метод для прогрева кеша
     */
    public function warmupCache(string $key, mixed $data, int $ttl = 3600): void
    {
        try {
            $this->cache->get($key, function () use ($data) {
                return $data;
            });
            $this->logger->info('Cache warmed up', ['key' => $key]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to warm up cache', ['key' => $key, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Кеширование с тегами для инвалидации
     */
    public function cacheWithTags(string $key, callable $callback, array $tags = [], int $ttl = 3600): mixed
    {
        return $this->tagAwareCache->get($key, function () use ($callback, $tags, $ttl) {
            $result = $callback();
            
            // Устанавливаем теги для инвалидации
            if (!empty($tags)) {
                $this->tagAwareCache->invalidateTags($tags);
            }
            
            return $result;
        });
    }

    /**
     * Кеширование видео с автоматическими тегами
     */
    public function cacheVideo(int $videoId, callable $callback, int $ttl = 300): mixed
    {
        $key = "video_{$videoId}";
        $tags = ["video", "video_{$videoId}"];
        
        return $this->cacheWithTags($key, $callback, $tags, $ttl);
    }
}