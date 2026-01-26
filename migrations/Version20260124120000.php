<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Добавление расширенной функциональности каналов: аналитика, плейлисты, донаты
 */
final class Version20260124120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Добавление таблиц для аналитики каналов, плейлистов и донатов';
    }

    public function up(Schema $schema): void
    {
        // Таблица аналитики каналов
        $this->addSql('CREATE TABLE channel_analytics (
            id INT AUTO_INCREMENT NOT NULL,
            channel_id INT NOT NULL,
            date DATE NOT NULL,
            views INT DEFAULT 0 NOT NULL,
            unique_views INT DEFAULT 0 NOT NULL,
            new_subscribers INT DEFAULT 0 NOT NULL,
            unsubscribers INT DEFAULT 0 NOT NULL,
            likes INT DEFAULT 0 NOT NULL,
            comments INT DEFAULT 0 NOT NULL,
            shares INT DEFAULT 0 NOT NULL,
            revenue NUMERIC(10, 2) DEFAULT \'0.00\' NOT NULL,
            watch_time_minutes INT DEFAULT 0 NOT NULL,
            demographic_data JSON DEFAULT NULL,
            traffic_sources JSON DEFAULT NULL,
            created_at DATETIME NOT NULL,
            INDEX IDX_channel_analytics_date (channel_id, date),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Таблица плейлистов каналов
        $this->addSql('CREATE TABLE channel_playlists (
            id INT AUTO_INCREMENT NOT NULL,
            channel_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL UNIQUE,
            description LONGTEXT DEFAULT NULL,
            thumbnail VARCHAR(255) DEFAULT NULL,
            visibility VARCHAR(20) DEFAULT \'public\' NOT NULL,
            is_active TINYINT(1) DEFAULT 1 NOT NULL,
            videos_count INT DEFAULT 0 NOT NULL,
            views_count INT DEFAULT 0 NOT NULL,
            sort_order INT DEFAULT 0 NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX IDX_playlist_channel (channel_id),
            INDEX IDX_playlist_slug (slug),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Таблица связи плейлистов и видео
        $this->addSql('CREATE TABLE playlist_videos (
            id INT AUTO_INCREMENT NOT NULL,
            playlist_id INT NOT NULL,
            video_id INT NOT NULL,
            sort_order INT DEFAULT 0 NOT NULL,
            added_at DATETIME NOT NULL,
            added_by_id INT DEFAULT NULL,
            INDEX IDX_playlist_sort (playlist_id, sort_order),
            UNIQUE INDEX unique_playlist_video (playlist_id, video_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Таблица донатов каналов
        $this->addSql('CREATE TABLE channel_donations (
            id INT AUTO_INCREMENT NOT NULL,
            channel_id INT NOT NULL,
            donor_id INT NOT NULL,
            amount NUMERIC(10, 2) NOT NULL,
            currency VARCHAR(3) DEFAULT \'USD\' NOT NULL,
            message LONGTEXT DEFAULT NULL,
            is_anonymous TINYINT(1) DEFAULT 0 NOT NULL,
            status VARCHAR(20) DEFAULT \'pending\' NOT NULL,
            payment_id VARCHAR(255) DEFAULT NULL,
            payment_method VARCHAR(50) DEFAULT NULL,
            fee NUMERIC(10, 2) DEFAULT NULL,
            net_amount NUMERIC(10, 2) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            processed_at DATETIME DEFAULT NULL,
            metadata JSON DEFAULT NULL,
            INDEX IDX_donation_channel (channel_id),
            INDEX IDX_donation_donor (donor_id),
            INDEX IDX_donation_date (created_at),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Добавляем внешние ключи
        $this->addSql('ALTER TABLE channel_analytics ADD CONSTRAINT FK_channel_analytics_channel FOREIGN KEY (channel_id) REFERENCES channels (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE channel_playlists ADD CONSTRAINT FK_channel_playlists_channel FOREIGN KEY (channel_id) REFERENCES channels (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE playlist_videos ADD CONSTRAINT FK_playlist_videos_playlist FOREIGN KEY (playlist_id) REFERENCES channel_playlists (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE playlist_videos ADD CONSTRAINT FK_playlist_videos_video FOREIGN KEY (video_id) REFERENCES video (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE playlist_videos ADD CONSTRAINT FK_playlist_videos_added_by FOREIGN KEY (added_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE channel_donations ADD CONSTRAINT FK_channel_donations_channel FOREIGN KEY (channel_id) REFERENCES channels (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE channel_donations ADD CONSTRAINT FK_channel_donations_donor FOREIGN KEY (donor_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // Удаляем внешние ключи
        $this->addSql('ALTER TABLE channel_analytics DROP FOREIGN KEY FK_channel_analytics_channel');
        $this->addSql('ALTER TABLE channel_playlists DROP FOREIGN KEY FK_channel_playlists_channel');
        $this->addSql('ALTER TABLE playlist_videos DROP FOREIGN KEY FK_playlist_videos_playlist');
        $this->addSql('ALTER TABLE playlist_videos DROP FOREIGN KEY FK_playlist_videos_video');
        $this->addSql('ALTER TABLE playlist_videos DROP FOREIGN KEY FK_playlist_videos_added_by');
        $this->addSql('ALTER TABLE channel_donations DROP FOREIGN KEY FK_channel_donations_channel');
        $this->addSql('ALTER TABLE channel_donations DROP FOREIGN KEY FK_channel_donations_donor');

        // Удаляем таблицы
        $this->addSql('DROP TABLE channel_donations');
        $this->addSql('DROP TABLE playlist_videos');
        $this->addSql('DROP TABLE channel_playlists');
        $this->addSql('DROP TABLE channel_analytics');
    }
}