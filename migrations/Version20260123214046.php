<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260123214046 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE channel_members (id INT AUTO_INCREMENT NOT NULL, role VARCHAR(20) DEFAULT \'member\' NOT NULL, is_active TINYINT DEFAULT 1 NOT NULL, joined_at DATETIME NOT NULL, user_id INT NOT NULL, channel_id INT NOT NULL, invited_by_id INT DEFAULT NULL, INDEX IDX_F2E3EDFEA7B4A7E3 (invited_by_id), INDEX idx_member_user (user_id), INDEX idx_member_channel (channel_id), UNIQUE INDEX unique_channel_member (user_id, channel_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE channel_subscriptions (id INT AUTO_INCREMENT NOT NULL, is_paid TINYINT DEFAULT 0 NOT NULL, paid_until DATETIME DEFAULT NULL, notifications_enabled TINYINT DEFAULT 1 NOT NULL, subscribed_at DATETIME NOT NULL, user_id INT NOT NULL, channel_id INT NOT NULL, INDEX idx_subscription_user (user_id), INDEX idx_subscription_channel (channel_id), UNIQUE INDEX unique_channel_subscription (user_id, channel_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE channels (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, type VARCHAR(20) DEFAULT \'personal\' NOT NULL, avatar VARCHAR(255) DEFAULT NULL, banner VARCHAR(255) DEFAULT NULL, primary_color VARCHAR(7) DEFAULT NULL, secondary_color VARCHAR(7) DEFAULT NULL, website VARCHAR(255) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, twitter VARCHAR(100) DEFAULT NULL, instagram VARCHAR(100) DEFAULT NULL, onlyfans VARCHAR(100) DEFAULT NULL, is_verified TINYINT DEFAULT 0 NOT NULL, is_active TINYINT DEFAULT 1 NOT NULL, is_premium TINYINT DEFAULT 0 NOT NULL, subscription_price NUMERIC(10, 2) DEFAULT NULL, subscribers_count INT DEFAULT 0 NOT NULL, videos_count INT DEFAULT 0 NOT NULL, total_views BIGINT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, owner_id INT NOT NULL, UNIQUE INDEX UNIQ_F314E2B6989D9B62 (slug), INDEX IDX_F314E2B67E3C61F9 (owner_id), INDEX idx_channel_slug (slug), INDEX idx_channel_type (type), INDEX idx_channel_verified (is_verified), INDEX idx_channel_active (is_active), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE channel_members ADD CONSTRAINT FK_F2E3EDFEA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE channel_members ADD CONSTRAINT FK_F2E3EDFE72F5A1AA FOREIGN KEY (channel_id) REFERENCES channels (id)');
        $this->addSql('ALTER TABLE channel_members ADD CONSTRAINT FK_F2E3EDFEA7B4A7E3 FOREIGN KEY (invited_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE channel_subscriptions ADD CONSTRAINT FK_A5EA69B5A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE channel_subscriptions ADD CONSTRAINT FK_A5EA69B572F5A1AA FOREIGN KEY (channel_id) REFERENCES channels (id)');
        $this->addSql('ALTER TABLE channels ADD CONSTRAINT FK_F314E2B67E3C61F9 FOREIGN KEY (owner_id) REFERENCES `user` (id)');
        
        // Добавляем поле channel_id в таблицу video
        $this->addSql('ALTER TABLE video ADD channel_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE video ADD CONSTRAINT FK_7CC7DA2C72F5A1AA FOREIGN KEY (channel_id) REFERENCES channels (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_7CC7DA2C72F5A1AA ON video (channel_id)');
        
        // Создаем папки для медиафайлов каналов
        $this->addSql('-- Creating media directories for channels');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE video DROP FOREIGN KEY FK_7CC7DA2C72F5A1AA');
        $this->addSql('DROP INDEX IDX_7CC7DA2C72F5A1AA ON video');
        $this->addSql('ALTER TABLE video DROP channel_id');
        
        $this->addSql('ALTER TABLE channel_members DROP FOREIGN KEY FK_F2E3EDFEA76ED395');
        $this->addSql('ALTER TABLE channel_members DROP FOREIGN KEY FK_F2E3EDFE72F5A1AA');
        $this->addSql('ALTER TABLE channel_members DROP FOREIGN KEY FK_F2E3EDFEA7B4A7E3');
        $this->addSql('ALTER TABLE channel_subscriptions DROP FOREIGN KEY FK_A5EA69B5A76ED395');
        $this->addSql('ALTER TABLE channel_subscriptions DROP FOREIGN KEY FK_A5EA69B572F5A1AA');
        $this->addSql('ALTER TABLE channels DROP FOREIGN KEY FK_F314E2B67E3C61F9');
        $this->addSql('DROP TABLE channel_members');
        $this->addSql('DROP TABLE channel_subscriptions');
        $this->addSql('DROP TABLE channels');
    }
}
