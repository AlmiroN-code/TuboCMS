<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260208023426 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add publishedAt field to video table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE video ADD published_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE INDEX idx_published_at ON video (published_at)');
        
        // Устанавливаем publishedAt для уже опубликованных видео
        $this->addSql('UPDATE video SET published_at = created_at WHERE status = \'published\' AND published_at IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_published_at ON video');
        $this->addSql('ALTER TABLE video DROP published_at');
    }
}
