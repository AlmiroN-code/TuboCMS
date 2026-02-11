<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260207091803 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE video_chapters (id INT AUTO_INCREMENT NOT NULL, timestamp INT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, video_id INT NOT NULL, created_by_id INT NOT NULL, INDEX IDX_6FA371CDB03A8386 (created_by_id), INDEX idx_chapter_video (video_id), INDEX idx_chapter_timestamp (timestamp), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE video_chapters ADD CONSTRAINT FK_6FA371CD29C1004E FOREIGN KEY (video_id) REFERENCES video (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE video_chapters ADD CONSTRAINT FK_6FA371CDB03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE video_chapters DROP FOREIGN KEY FK_6FA371CD29C1004E');
        $this->addSql('ALTER TABLE video_chapters DROP FOREIGN KEY FK_6FA371CDB03A8386');
        $this->addSql('DROP TABLE video_chapters');
    }
}
