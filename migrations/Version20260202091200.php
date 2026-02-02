<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Финальная проверка целостности для продакшена
 */
final class Version20260202091200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Финальная проверка целостности базы данных для продакшена';
    }

    public function up(Schema $schema): void
    {
        // Проверяем существование всех критически важных таблиц
        $this->addSql("
            SELECT 'Checking critical tables...' as status;
            SELECT COUNT(*) FROM information_schema.tables 
            WHERE table_schema = DATABASE() 
            AND table_name IN ('video', 'user', 'channels', 'category', 'tag', 'comment');
        ");

        // Проверяем FULLTEXT индексы
        $this->addSql("
            SELECT 'Checking FULLTEXT indexes...' as status;
            SELECT table_name, index_name FROM information_schema.statistics 
            WHERE table_schema = DATABASE() 
            AND index_type = 'FULLTEXT'
            AND index_name IN ('ft_video_title_description', 'ft_tag_name', 'ft_category_name_description', 'ft_model_display_name_bio');
        ");

        // Проверяем материализованные представления
        $this->addSql("
            SELECT 'Checking materialized views...' as status;
            SELECT table_name FROM information_schema.views 
            WHERE table_schema = DATABASE() 
            AND table_name IN ('video_stats_daily_view', 'channel_stats_view', 'popular_videos_view');
        ");

        // Проверяем критически важные индексы производительности
        $this->addSql("
            SELECT 'Checking performance indexes...' as status;
            SELECT COUNT(*) as performance_indexes FROM information_schema.statistics 
            WHERE table_schema = DATABASE() 
            AND index_name IN ('idx_video_status_created', 'idx_video_status_views', 'idx_comment_moderation_video', 'idx_channel_active_subscribers');
        ");
    }

    public function down(Schema $schema): void
    {
        // Проверочная миграция - rollback не требуется
        $this->addSql('SELECT "Production integrity check - no rollback needed" as message');
    }
}