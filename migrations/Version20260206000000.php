<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260206000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add FULLTEXT index on video title and description for search';
    }

    public function up(Schema $schema): void
    {
        // FULLTEXT индексы для поиска
        $this->addSql('CREATE FULLTEXT INDEX idx_video_search ON video (title, description)');
        $this->addSql('CREATE FULLTEXT INDEX idx_tag_search ON tag (name)');
        $this->addSql('CREATE FULLTEXT INDEX idx_category_search ON category (name, description)');
        $this->addSql('CREATE FULLTEXT INDEX idx_model_search ON model_profile (display_name, bio)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_video_search ON video');
        $this->addSql('DROP INDEX idx_tag_search ON tag');
        $this->addSql('DROP INDEX idx_category_search ON category');
        $this->addSql('DROP INDEX idx_model_search ON model_profile');
    }
}
