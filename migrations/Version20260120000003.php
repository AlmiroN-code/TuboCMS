<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Creates missing ad_segment_relation junction table
 */
final class Version20260120000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create ad_segment_relation junction table for many-to-many relationship';
    }

    public function up(Schema $schema): void
    {
        // Only create if both parent tables exist
        if (!$schema->hasTable('ad_segment_relation') && $schema->hasTable('ad') && $schema->hasTable('ad_segment')) {
            $this->addSql('CREATE TABLE ad_segment_relation (
                ad_id INT NOT NULL,
                ad_segment_id INT NOT NULL,
                INDEX IDX_ad_segment_relation_ad (ad_id),
                INDEX IDX_ad_segment_relation_segment (ad_segment_id),
                PRIMARY KEY (ad_id, ad_segment_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
            
            $this->addSql('ALTER TABLE ad_segment_relation ADD CONSTRAINT FK_ad_segment_relation_ad 
                FOREIGN KEY (ad_id) REFERENCES ad (id) ON DELETE CASCADE');
            $this->addSql('ALTER TABLE ad_segment_relation ADD CONSTRAINT FK_ad_segment_relation_segment 
                FOREIGN KEY (ad_segment_id) REFERENCES ad_segment (id) ON DELETE CASCADE');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('ad_segment_relation')) {
            $this->addSql('DROP TABLE ad_segment_relation');
        }
    }
}
