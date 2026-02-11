<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260208063752 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE live_stream (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(200) NOT NULL, description LONGTEXT DEFAULT NULL, slug VARCHAR(250) NOT NULL, stream_key VARCHAR(64) NOT NULL, status VARCHAR(20) NOT NULL, viewers_count INT NOT NULL, peak_viewers_count INT NOT NULL, total_views INT NOT NULL, thumbnail VARCHAR(255) DEFAULT NULL, scheduled_at DATETIME DEFAULT NULL, started_at DATETIME DEFAULT NULL, ended_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, metadata JSON DEFAULT NULL, streamer_id INT NOT NULL, channel_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_93BF08C8989D9B62 (slug), UNIQUE INDEX UNIQ_93BF08C820F533D7 (stream_key), INDEX IDX_93BF08C825F432AD (streamer_id), INDEX IDX_93BF08C872F5A1AA (channel_id), INDEX idx_status (status), INDEX idx_started_at (started_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE live_stream ADD CONSTRAINT FK_93BF08C825F432AD FOREIGN KEY (streamer_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE live_stream ADD CONSTRAINT FK_93BF08C872F5A1AA FOREIGN KEY (channel_id) REFERENCES channels (id) ON DELETE SET NULL');
        $this->addSql('DROP INDEX idx_published_at ON video');
        $this->addSql('DROP INDEX idx_status_created ON video');
        $this->addSql('ALTER TABLE video CHANGE published_at published_at DATETIME DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_status_published ON video (status, published_at)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE live_stream DROP FOREIGN KEY FK_93BF08C825F432AD');
        $this->addSql('ALTER TABLE live_stream DROP FOREIGN KEY FK_93BF08C872F5A1AA');
        $this->addSql('DROP TABLE live_stream');
        $this->addSql('DROP INDEX idx_status_published ON video');
        $this->addSql('ALTER TABLE video CHANGE published_at published_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE INDEX idx_published_at ON video (published_at)');
        $this->addSql('CREATE INDEX idx_status_created ON video (status, created_at)');
    }
}
