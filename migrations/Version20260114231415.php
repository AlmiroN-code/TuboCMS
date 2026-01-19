<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260114231415 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Добавление SEO метатегов для категорий';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE category ADD meta_title VARCHAR(255) DEFAULT NULL, ADD meta_description LONGTEXT DEFAULT NULL, ADD meta_keywords VARCHAR(500) DEFAULT NULL');
        $this->addSql('DROP INDEX idx_category_fulltext ON category');
        $this->addSql('DROP INDEX idx_model_fulltext ON model_profile');
        $this->addSql('DROP INDEX idx_tag_fulltext ON tag');
        $this->addSql('DROP INDEX idx_video_fulltext ON video');
        $this->addSql('DROP INDEX idx_video_title_fulltext ON video');
        $this->addSql('ALTER TABLE watch_later CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE watch_later RENAME INDEX idx_1e3051a7a76ed395 TO idx_watch_later_user');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE category DROP meta_title, DROP meta_description, DROP meta_keywords');
        $this->addSql('CREATE FULLTEXT INDEX idx_category_fulltext ON category (name, description)');
        $this->addSql('CREATE FULLTEXT INDEX idx_model_fulltext ON model_profile (display_name, bio)');
        $this->addSql('CREATE FULLTEXT INDEX idx_tag_fulltext ON tag (name)');
        $this->addSql('CREATE FULLTEXT INDEX idx_video_fulltext ON video (title, description)');
        $this->addSql('CREATE FULLTEXT INDEX idx_video_title_fulltext ON video (title)');
        $this->addSql('ALTER TABLE watch_later CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE watch_later RENAME INDEX idx_watch_later_user TO IDX_1E3051A7A76ED395');
    }
}
