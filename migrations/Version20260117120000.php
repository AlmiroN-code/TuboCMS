<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Добавляем индексы для оптимизации производительности
 */
final class Version20260117120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Добавляем критически важные индексы для оптимизации производительности';
    }

    public function up(Schema $schema): void
    {
        // Индекс для фильтрации рекомендуемых видео
        $this->addSql('CREATE INDEX idx_video_featured_status ON video (is_featured, status)');
        
        // Индекс для фильтрации по длительности
        $this->addSql('CREATE INDEX idx_video_duration ON video (duration)');
        
        // Индекс для связи с пользователем
        $this->addSql('CREATE INDEX idx_video_created_by ON video (created_by_id)');
        
        // Полнотекстовый индекс для поиска
        $this->addSql('CREATE FULLTEXT INDEX idx_video_search ON video (title, description)');
        
        // Индекс для лайков/дизлайков (для сортировки по рейтингу)
        $this->addSql('CREATE INDEX idx_video_likes ON video (likes_count, dislikes_count)');
        
        // Индекс для показов (impressions)
        $this->addSql('CREATE INDEX idx_video_impressions ON video (impressions_count)');
        
        // Индекс для статуса обработки
        $this->addSql('CREATE INDEX idx_video_processing ON video (processing_status)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_video_featured_status ON video');
        $this->addSql('DROP INDEX idx_video_duration ON video');
        $this->addSql('DROP INDEX idx_video_created_by ON video');
        $this->addSql('DROP INDEX idx_video_search ON video');
        $this->addSql('DROP INDEX idx_video_likes ON video');
        $this->addSql('DROP INDEX idx_video_impressions ON video');
        $this->addSql('DROP INDEX idx_video_processing ON video');
    }
}