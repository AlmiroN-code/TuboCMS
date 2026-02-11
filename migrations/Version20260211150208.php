<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260211150208 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE video_view (id INT AUTO_INCREMENT NOT NULL, ip_address VARCHAR(45) NOT NULL, country_code VARCHAR(2) DEFAULT NULL, user_agent VARCHAR(255) DEFAULT NULL, referer VARCHAR(255) DEFAULT NULL, watched_seconds INT DEFAULT 0 NOT NULL, viewed_at DATETIME NOT NULL, video_id INT NOT NULL, user_id INT DEFAULT NULL, INDEX IDX_F96AF65229C1004E (video_id), INDEX IDX_F96AF652A76ED395 (user_id), INDEX idx_video_view_video_date (video_id, viewed_at), INDEX idx_video_view_country (country_code), INDEX idx_video_view_ip (ip_address), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE video_view ADD CONSTRAINT FK_F96AF65229C1004E FOREIGN KEY (video_id) REFERENCES video (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE video_view ADD CONSTRAINT FK_F96AF652A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE `user` ADD last_ip_address VARCHAR(45) DEFAULT NULL, ADD country_code VARCHAR(2) DEFAULT NULL, ADD country_manually_set TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE video_view DROP FOREIGN KEY FK_F96AF65229C1004E');
        $this->addSql('ALTER TABLE video_view DROP FOREIGN KEY FK_F96AF652A76ED395');
        $this->addSql('DROP TABLE video_view');
        $this->addSql('ALTER TABLE `user` DROP last_ip_address, DROP country_code, DROP country_manually_set');
    }
}
