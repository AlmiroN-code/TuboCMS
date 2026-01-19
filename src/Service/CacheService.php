<?php

namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Psr\Log\LoggerInterface;

class CacheService
{
    private const DEFAULT_TTL = 3600; // 1 час
    private const SETTINGS_TTL = 1800; // 30 минут
    private const CATEGORIES_TTL = 3600; // 1 час
    private const STATS_TTL = 300; // 5 минут

    public function __construct(
        private CacheInterface $cache,
        private LoggerInterface $logger
    ) {
    }

    public function get(string $key, callable $callback, int $ttl = self::DEFAULT_TTL): mixed
    {
        try {
            return $this->cache->get($key, function (ItemInterface $item) use ($callback, $ttl) {
                $item->expiresAfter($ttl);
                return $callback();
            });
        } catch (\Exception $e) {
            $this->logger->error('Cache error', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            // Fallback: выполняем callback напрямую
            return $callback();
        }
    }

    public function getSetting(string $key, callable $callback): mixed
    {
        return $this->get("setting_{$key}", $callback, self::SETTINGS_TTL);
    }

    public function getCategories(callable $callback): array
    {
        return $this->get('categories_active', $callback, self::CATEGORIES_TTL);
    }

    public function getStats(string $statsKey, callable $callback): mixed
    {
        return $this->get("stats_{$statsKey}", $callback, self::STATS_TTL);
    }

    public function invalidate(string $key): bool
    {
        try {
            return $this->cache->delete($key);
        } catch (\Exception $e) {
            $this->logger->error('Cache invalidation error', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function invalidateSettings(): void
    {
        $this->invalidateByPattern('setting_*');
    }

    public function invalidateCategories(): void
    {
        $this->invalidate('categories_active');
    }

    public function invalidateStats(): void
    {
        $this->invalidateByPattern('stats_*');
    }

    private function invalidateByPattern(string $pattern): void
    {
        // Для простоты очищаем весь кэш, в продакшене можно использовать TaggedCache
        try {
            $this->cache->clear();
        } catch (\Exception $e) {
            $this->logger->error('Cache clear error', [
                'pattern' => $pattern,
                'error' => $e->getMessage()
            ]);
        }
    }
}