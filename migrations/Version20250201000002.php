<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Создание материализованных представлений для аналитики (Production Safe)
 */
final class Version20250201000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Создание материализованных представлений для быстрой аналитики (исправленная версия)';
    }

    public function up(Schema $schema): void
    {
        // Создание представления ежедневной статистики видео
        $this->addSql("
            CREATE OR REPLACE VIEW video_stats_daily_view AS
            SELECT 
                DATE(v.created_at) as date,
                COUNT(v.id) as total_videos,
                COALESCE(SUM(v.views_count), 0) as total_views,
                COUNT(CASE WHEN DATE(v.created_at) = CURDATE() THEN 1 END) as total_uploads,
                COALESCE(SUM(v.likes_count), 0) as total_likes,
                COALESCE(AVG(v.duration), 0) as avg_duration
            FROM video v
            WHERE v.status = 'published'
            GROUP BY DATE(v.created_at)
            ORDER BY date DESC
        ");

        // Создание представления статистики каналов
        $this->addSql("
            CREATE OR REPLACE VIEW channel_stats_view AS
            SELECT 
                c.id as channel_id,
                c.name as channel_name,
                COUNT(v.id) as videos_count,
                COALESCE(SUM(v.views_count), 0) as total_views,
                c.subscribers_count,
                COALESCE(SUM(v.likes_count), 0) as total_likes,
                CASE 
                    WHEN COUNT(v.id) > 0 THEN COALESCE(SUM(v.views_count), 0) / COUNT(v.id)
                    ELSE 0 
                END as avg_views_per_video,
                NOW() as last_updated
            FROM channels c
            LEFT JOIN video v ON c.id = v.channel_id AND v.status = 'published'
            WHERE c.is_active = 1
            GROUP BY c.id, c.name, c.subscribers_count
            ORDER BY total_views DESC
        ");

        // Создание представления популярных видео
        $this->addSql("
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
                (v.views_count * 0.7 + v.likes_count * 0.3) as popularity_score
            FROM video v
            LEFT JOIN channels c ON v.channel_id = c.id
            WHERE v.status = 'published'
            AND v.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY popularity_score DESC
            LIMIT 1000
        ");

        // Безопасное создание индексов с проверками существования
        $this->addSql("
            SET @sql = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                 WHERE table_schema = DATABASE() 
                 AND table_name = 'video' 
                 AND index_name = 'idx_video_status_created') = 0,
                'ALTER TABLE video ADD INDEX idx_video_status_created (status, created_at)',
                'SELECT \"Index idx_video_status_created already exists\"'
            ));
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ");
        
        $this->addSql("
            SET @sql = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                 WHERE table_schema = DATABASE() 
                 AND table_name = 'video' 
                 AND index_name = 'idx_video_status_views') = 0,
                'ALTER TABLE video ADD INDEX idx_video_status_views (status, views_count)',
                'SELECT \"Index idx_video_status_views already exists\"'
            ));
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ");
        
        $this->addSql("
            SET @sql = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                 WHERE table_schema = DATABASE() 
                 AND table_name = 'comment' 
                 AND index_name = 'idx_comment_moderation_video') = 0,
                'ALTER TABLE comment ADD INDEX idx_comment_moderation_video (moderation_status, video_id)',
                'SELECT \"Index idx_comment_moderation_video already exists\"'
            ));
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ");
        
        $this->addSql("
            SET @sql = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                 WHERE table_schema = DATABASE() 
                 AND table_name = 'channels' 
                 AND index_name = 'idx_channel_active_subscribers') = 0,
                'ALTER TABLE channels ADD INDEX idx_channel_active_subscribers (is_active, subscribers_count)',
                'SELECT \"Index idx_channel_active_subscribers already exists\"'
            ));
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ");
    }

    public function down(Schema $schema): void
    {
        // Удаление представлений
        $this->addSql("DROP VIEW IF EXISTS video_stats_daily_view");
        $this->addSql("DROP VIEW IF EXISTS channel_stats_view");
        $this->addSql("DROP VIEW IF EXISTS popular_videos_view");

        // Безопасное удаление индексов с проверками
        $this->addSql("
            SET @sql = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                 WHERE table_schema = DATABASE() 
                 AND table_name = 'video' 
                 AND index_name = 'idx_video_status_created') > 0,
                'ALTER TABLE video DROP INDEX idx_video_status_created',
                'SELECT \"Index idx_video_status_created does not exist\"'
            ));
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ");
        
        $this->addSql("
            SET @sql = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                 WHERE table_schema = DATABASE() 
                 AND table_name = 'video' 
                 AND index_name = 'idx_video_status_views') > 0,
                'ALTER TABLE video DROP INDEX idx_video_status_views',
                'SELECT \"Index idx_video_status_views does not exist\"'
            ));
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ");
        
        $this->addSql("
            SET @sql = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                 WHERE table_schema = DATABASE() 
                 AND table_name = 'comment' 
                 AND index_name = 'idx_comment_moderation_video') > 0,
                'ALTER TABLE comment DROP INDEX idx_comment_moderation_video',
                'SELECT \"Index idx_comment_moderation_video does not exist\"'
            ));
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ");
        
        $this->addSql("
            SET @sql = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                 WHERE table_schema = DATABASE() 
                 AND table_name = 'channels' 
                 AND index_name = 'idx_channel_active_subscribers') > 0,
                'ALTER TABLE channels DROP INDEX idx_channel_active_subscribers',
                'SELECT \"Index idx_channel_active_subscribers does not exist\"'
            ));
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ");
    }
}