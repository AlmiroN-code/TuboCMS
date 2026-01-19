<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Добавляет FULLTEXT индексы для полнотекстового поиска
 */
final class Version20260114120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add FULLTEXT indexes for video, tag, category, and model_profile tables';
    }

    public function up(Schema $schema): void
    {
        // FULLTEXT индекс для видео (title + description)
        $this->addSql('CREATE FULLTEXT INDEX idx_video_fulltext ON video (title, description)');
        
        // FULLTEXT индекс только для title (для автодополнения)
        $this->addSql('CREATE FULLTEXT INDEX idx_video_title_fulltext ON video (title)');
        
        // FULLTEXT индекс для тегов
        $this->addSql('CREATE FULLTEXT INDEX idx_tag_fulltext ON tag (name)');
        
        // FULLTEXT индекс для категорий
        $this->addSql('CREATE FULLTEXT INDEX idx_category_fulltext ON category (name, description)');
        
        // FULLTEXT индекс для моделей
        $this->addSql('CREATE FULLTEXT INDEX idx_model_fulltext ON model_profile (display_name, bio)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_video_fulltext ON video');
        $this->addSql('DROP INDEX idx_video_title_fulltext ON video');
        $this->addSql('DROP INDEX idx_tag_fulltext ON tag');
        $this->addSql('DROP INDEX idx_category_fulltext ON category');
        $this->addSql('DROP INDEX idx_model_fulltext ON model_profile');
    }
}
