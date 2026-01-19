<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260108170204 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Добавляет оптимизационные индексы для улучшения производительности запросов к видео и полнотекстовый поиск';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bookmark CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('CREATE INDEX idx_user_created ON comment (user_id, created_at)');
        $this->addSql('CREATE INDEX idx_video_created ON comment (video_id, created_at)');
        $this->addSql('ALTER TABLE model_like CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE model_like RENAME INDEX idx_model_like_user TO IDX_81346C54A76ED395');
        $this->addSql('ALTER TABLE model_like RENAME INDEX idx_model_like_model TO IDX_81346C547975B7E7');
        $this->addSql('ALTER TABLE model_profile CHANGE dislikes_count dislikes_count INT NOT NULL');
        $this->addSql('ALTER TABLE model_subscription CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE model_subscription RENAME INDEX idx_model_sub_user TO IDX_A14C8B9A76ED395');
        $this->addSql('ALTER TABLE model_subscription RENAME INDEX idx_model_sub_model TO IDX_A14C8B97975B7E7');
        $this->addSql('ALTER TABLE notification CHANGE is_read is_read TINYINT NOT NULL, CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE playlist CHANGE is_public is_public TINYINT NOT NULL, CHANGE videos_count videos_count INT NOT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE playlist_video CHANGE position position INT NOT NULL, CHANGE added_at added_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE playlist_video RENAME INDEX idx_f542a1e36bbd148 TO IDX_DFDBC36F6BBD148');
        $this->addSql('ALTER TABLE playlist_video RENAME INDEX idx_f542a1e329c1004e TO IDX_DFDBC36F29C1004E');
        $this->addSql('ALTER TABLE season CHANGE number number INT NOT NULL');
        $this->addSql('ALTER TABLE series CHANGE videos_count videos_count INT NOT NULL, CHANGE created_at created_at DATETIME NOT NULL');
        
        // Оптимизационные индексы для видео
        $this->addSql('CREATE INDEX idx_user_status ON video (created_by_id, status)');
        $this->addSql('CREATE INDEX idx_category_status ON video (category_id, status)');
        $this->addSql('CREATE INDEX idx_featured_status ON video (is_featured, status)');
        
        // Дополнительные индексы для производительности
        $this->addSql('CREATE INDEX idx_duration_status ON video (duration, status)');
        $this->addSql('CREATE INDEX idx_views_status ON video (views_count, status)');
        $this->addSql('CREATE INDEX idx_likes_status ON video (likes_count, status)');
        
        // Полнотекстовый индекс для поиска (если поддерживается MySQL)
        $this->addSql('ALTER TABLE video ADD FULLTEXT idx_fulltext_search (title, description)');
        
        $this->addSql('ALTER TABLE video_like CHANGE is_like is_like TINYINT NOT NULL');
        $this->addSql('ALTER TABLE watch_history CHANGE watched_seconds watched_seconds INT NOT NULL, CHANGE watch_progress watch_progress INT NOT NULL, CHANGE watched_at watched_at DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bookmark CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('DROP INDEX idx_user_created ON comment');
        $this->addSql('DROP INDEX idx_video_created ON comment');
        $this->addSql('ALTER TABLE model_like CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE model_like RENAME INDEX idx_81346c54a76ed395 TO IDX_MODEL_LIKE_USER');
        $this->addSql('ALTER TABLE model_like RENAME INDEX idx_81346c547975b7e7 TO IDX_MODEL_LIKE_MODEL');
        $this->addSql('ALTER TABLE model_profile CHANGE dislikes_count dislikes_count INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE model_subscription CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE model_subscription RENAME INDEX idx_a14c8b9a76ed395 TO IDX_MODEL_SUB_USER');
        $this->addSql('ALTER TABLE model_subscription RENAME INDEX idx_a14c8b97975b7e7 TO IDX_MODEL_SUB_MODEL');
        $this->addSql('ALTER TABLE notification CHANGE is_read is_read TINYINT DEFAULT 0 NOT NULL, CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE playlist CHANGE is_public is_public TINYINT DEFAULT 1 NOT NULL, CHANGE videos_count videos_count INT DEFAULT 0 NOT NULL, CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE playlist_video CHANGE position position INT DEFAULT 0 NOT NULL, CHANGE added_at added_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE playlist_video RENAME INDEX idx_dfdbc36f6bbd148 TO IDX_F542A1E36BBD148');
        $this->addSql('ALTER TABLE playlist_video RENAME INDEX idx_dfdbc36f29c1004e TO IDX_F542A1E329C1004E');
        $this->addSql('ALTER TABLE season CHANGE number number INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE series CHANGE videos_count videos_count INT DEFAULT 0 NOT NULL, CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        
        // Удаляем оптимизационные индексы
        $this->addSql('DROP INDEX idx_user_status ON video');
        $this->addSql('DROP INDEX idx_category_status ON video');
        $this->addSql('DROP INDEX idx_featured_status ON video');
        $this->addSql('DROP INDEX idx_duration_status ON video');
        $this->addSql('DROP INDEX idx_views_status ON video');
        $this->addSql('DROP INDEX idx_likes_status ON video');
        $this->addSql('DROP INDEX idx_fulltext_search ON video');
        
        $this->addSql('ALTER TABLE video_like CHANGE is_like is_like TINYINT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE watch_history CHANGE watched_seconds watched_seconds INT DEFAULT 0 NOT NULL, CHANGE watch_progress watch_progress INT DEFAULT 0 NOT NULL, CHANGE watched_at watched_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }
}
