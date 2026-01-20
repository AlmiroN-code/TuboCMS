<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Creates missing video_category junction table
 */
final class Version20260120000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create video_category junction table for many-to-many relationship';
    }

    public function up(Schema $schema): void
    {
        // Check if table already exists to avoid errors
        if (!$schema->hasTable('video_category')) {
            $this->addSql('CREATE TABLE video_category (
                video_id INT NOT NULL,
                category_id INT NOT NULL,
                INDEX IDX_video_category_video (video_id),
                INDEX IDX_video_category_category (category_id),
                PRIMARY KEY (video_id, category_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
            
            $this->addSql('ALTER TABLE video_category ADD CONSTRAINT FK_video_category_video 
                FOREIGN KEY (video_id) REFERENCES video (id) ON DELETE CASCADE');
            $this->addSql('ALTER TABLE video_category ADD CONSTRAINT FK_video_category_category 
                FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE CASCADE');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('video_category')) {
            $this->addSql('DROP TABLE video_category');
        }
    }
}
