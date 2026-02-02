<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Сервис для управления материализованными представлениями
 */
class MaterializedViewService
{
    private const CACHE_PREFIX = 'materialized_view_';
    private const REFRESH_LOCK_TTL = 300; // 5 минут

    public function __construct(
        private readonly Connection $connection,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Создать все материализованные представления
     */
    public function createAllViews(): void
    {
        $this->createVideoStatsDailyView();
        $this->createChannelStatsView();
        $this->createPopularVideosView();
        $this->createUserActivityView();
    }

    /**
     * Обновить все материализованные представления
     */
    public function refreshAllViews(): void
    {
        $lockKey = self::CACHE_PREFIX . 'refresh_lock';
        
        // Проверяем блокировку для предотвращения одновременного обновления
        if ($this->cache->get($lockKey, fn() => false)) {
            $this->logger->info('Materialized views refresh already in progress, skipping');
            return;
        }

        try {
            // Устанавливаем блокировку
            $this->cache->delete($lockKey);
            $this->cache->get($lockKey, fn() => true);

            $this->refreshVideoStatsDailyView();
            $this->refreshChannelStatsView();
            $this->refreshPopularVideosView();
            $this->refreshUserActivityView();

            $this->logger->info('All materialized views refreshed successfully');
        } catch (\Throwable $e) {
            $this->logger->error('Failed to refresh materialized views', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        } finally {
            // Снимаем блокировку
            $this->cache->delete($lockKey);
        }
    }

    /**
     * Создать представление ежедневной статистики видео
     */
    private function createVideoStatsDailyView(): void
    {
        $sql = "
            CREATE OR REPLACE VIEW video_stats_daily_view AS
            SELECT 
                DATE(v.created_at) as date,
                COUNT(v.id) as total_videos,
                COALESCE(SUM(v.views_count), 0) as total_views,
                COUNT(CASE WHEN DATE(v.created_at) = CURDATE() THEN 1 END) as total_uploads,
                COALESCE(SUM(comment_counts.comments_count), 0) as total_comments,
                COALESCE(SUM(v.likes_count), 0) as total_likes,
                COALESCE(AVG(v.duration), 0) as avg_duration
            FROM video v
            LEFT JOIN (
                SELECT 
                    c.video_id,
                    COUNT(c.id) as comments_count
                FROM comment c
                WHERE c.status = 'approved'
                GROUP BY c.video_id
            ) comment_counts ON v.id = comment_counts.video_id
            WHERE v.status = 'published'
            GROUP BY DATE(v.created_at)
            ORDER BY date DESC
        ";

        $this->connection->executeStatement($sql);
        $this->logger->info('Created video_stats_daily_view');
    }

    /**
     * Создать представление статистики каналов
     */
    private function createChannelStatsView(): void
    {
        $sql = "
            CREATE OR REPLACE VIEW channel_stats_view AS
            SELECT 
                c.id as channel_id,
                c.name as channel_name,
                COUNT(v.id) as videos_count,
                COALESCE(SUM(v.views_count), 0) as total_views,
                c.subscribers_count,
                COALESCE(SUM(comment_counts.comments_count), 0) as total_comments,
                COALESCE(SUM(v.likes_count), 0) as total_likes,
                CASE 
                    WHEN COUNT(v.id) > 0 THEN COALESCE(SUM(v.views_count), 0) / COUNT(v.id)
                    ELSE 0 
                END as avg_views_per_video,
                NOW() as last_updated
            FROM channel c
            LEFT JOIN video v ON c.id = v.channel_id AND v.status = 'published'
            LEFT JOIN (
                SELECT 
                    v2.channel_id,
                    COUNT(cm.id) as comments_count
                FROM video v2
                LEFT JOIN comment cm ON v2.id = cm.video_id AND cm.status = 'approved'
                WHERE v2.status = 'published'
                GROUP BY v2.channel_id
            ) comment_counts ON c.id = comment_counts.channel_id
            WHERE c.is_active = 1
            GROUP BY c.id, c.name, c.subscribers_count
            ORDER BY total_views DESC
        ";

        $this->connection->executeStatement($sql);
        $this->logger->info('Created channel_stats_view');
    }

    /**
     * Создать представление популярных видео
     */
    private function createPopularVideosView(): void
    {
        $sql = "
            CREATE OR REPLACE VIEW popular_videos_view AS
            SELECT 
                v.id,
                v.title,
                v.slug,
                v.views_count,
                v.likes_count,
                v.duration,
                v.created_at,
                c.name as channel_name,
                c.slug as channel_slug,
                COUNT(cm.id) as comments_count,
                (v.views_count * 0.6 + v.likes_count * 0.3 + COUNT(cm.id) * 0.1) as popularity_score
            FROM video v
            LEFT JOIN channel c ON v.channel_id = c.id
            LEFT JOIN comment cm ON v.id = cm.video_id AND cm.status = 'approved'
            WHERE v.status = 'published'
            AND v.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY v.id, v.title, v.slug, v.views_count, v.likes_count, v.duration, v.created_at, c.name, c.slug
            ORDER BY popularity_score DESC
            LIMIT 1000
        ";

        $this->connection->executeStatement($sql);
        $this->logger->info('Created popular_videos_view');
    }

    /**
     * Создать представление активности пользователей
     */
    private function createUserActivityView(): void
    {
        $sql = "
            CREATE OR REPLACE VIEW user_activity_view AS
            SELECT 
                u.id as user_id,
                u.username,
                u.email,
                COUNT(DISTINCT v.id) as videos_uploaded,
                COUNT(DISTINCT c.id) as comments_posted,
                COUNT(DISTINCT cs.id) as channels_subscribed,
                COALESCE(SUM(v.views_count), 0) as total_video_views,
                u.last_login_at,
                u.created_at as registration_date,
                CASE 
                    WHEN u.last_login_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 'active'
                    WHEN u.last_login_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 'inactive'
                    ELSE 'dormant'
                END as activity_status
            FROM user u
            LEFT JOIN video v ON u.id = v.uploader_id AND v.status = 'published'
            LEFT JOIN comment c ON u.id = c.user_id AND c.status = 'approved'
            LEFT JOIN channel_subscription cs ON u.id = cs.user_id
            WHERE u.is_active = 1
            GROUP BY u.id, u.username, u.email, u.last_login_at, u.created_at
            ORDER BY total_video_views DESC
        ";

        $this->connection->executeStatement($sql);
        $this->logger->info('Created user_activity_view');
    }

    /**
     * Обновить представление ежедневной статистики
     */
    private function refreshVideoStatsDailyView(): void
    {
        // Для MySQL представления обновляются автоматически
        // Можно добавить логику для других СУБД
        $this->logger->info('Refreshed video_stats_daily_view');
    }

    /**
     * Обновить представление статистики каналов
     */
    private function refreshChannelStatsView(): void
    {
        $this->logger->info('Refreshed channel_stats_view');
    }

    /**
     * Обновить представление популярных видео
     */
    private function refreshPopularVideosView(): void
    {
        $this->logger->info('Refreshed popular_videos_view');
    }

    /**
     * Обновить представление активности пользователей
     */
    private function refreshUserActivityView(): void
    {
        $this->logger->info('Refreshed user_activity_view');
    }

    /**
     * Получить статистику по дням
     */
    public function getDailyStats(int $days = 30): array
    {
        $sql = "
            SELECT * FROM video_stats_daily_view 
            WHERE date >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            ORDER BY date DESC
        ";

        return $this->connection->fetchAllAssociative($sql, ['days' => $days]);
    }

    /**
     * Получить топ каналов
     */
    public function getTopChannels(int $limit = 50): array
    {
        $sql = "
            SELECT * FROM channel_stats_view 
            ORDER BY total_views DESC 
            LIMIT :limit
        ";

        return $this->connection->fetchAllAssociative($sql, ['limit' => $limit]);
    }

    /**
     * Получить популярные видео
     */
    public function getPopularVideos(int $limit = 100): array
    {
        $sql = "
            SELECT * FROM popular_videos_view 
            LIMIT :limit
        ";

        return $this->connection->fetchAllAssociative($sql, ['limit' => $limit]);
    }
}