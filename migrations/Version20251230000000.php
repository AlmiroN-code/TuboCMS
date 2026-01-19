<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * User Engagement Features - Phase 1: Core Entities
 * - Playlist, PlaylistVideo, WatchHistory
 * - Bookmark, Notification
 * - Series, Season
 * - Video updates (season, episodeNumber, animatedPreview)
 * - VideoLike update (type -> isLike)
 */
final class Version20251230000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user engagement features: playlists, watch history, bookmarks, notifications, series/seasons';
    }

    public function up(Schema $schema): void
    {
        // Playlist table
        $this->addSql('CREATE TABLE playlist (
            id INT AUTO_INCREMENT NOT NULL,
            owner_id INT NOT NULL,
            title VARCHAR(200) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            thumbnail VARCHAR(255) DEFAULT NULL,
            is_public TINYINT(1) NOT NULL DEFAULT 1,
            videos_count INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_D782112D7E3C61F9 (owner_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // PlaylistVideo table
        $this->addSql('CREATE TABLE playlist_video (
            id INT AUTO_INCREMENT NOT NULL,
            playlist_id INT NOT NULL,
            video_id INT NOT NULL,
            position INT NOT NULL DEFAULT 0,
            added_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_F542A1E36BBD148 (playlist_id),
            INDEX IDX_F542A1E329C1004E (video_id),
            UNIQUE INDEX unique_playlist_video (playlist_id, video_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // WatchHistory table
        $this->addSql('CREATE TABLE watch_history (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            video_id INT NOT NULL,
            watched_seconds INT NOT NULL DEFAULT 0,
            watch_progress INT NOT NULL DEFAULT 0,
            watched_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_DE44EFD8A76ED395 (user_id),
            INDEX IDX_DE44EFD829C1004E (video_id),
            INDEX idx_user_watched (user_id, watched_at),
            UNIQUE INDEX unique_user_video (user_id, video_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Bookmark table
        $this->addSql('CREATE TABLE bookmark (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            video_id INT NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_DA62921DA76ED395 (user_id),
            INDEX IDX_DA62921D29C1004E (video_id),
            UNIQUE INDEX unique_user_video_bookmark (user_id, video_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Notification table
        $this->addSql('CREATE TABLE notification (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            type VARCHAR(50) NOT NULL,
            data JSON NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_BF5476CAA76ED395 (user_id),
            INDEX idx_user_unread (user_id, is_read, created_at),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Series table
        $this->addSql('CREATE TABLE series (
            id INT AUTO_INCREMENT NOT NULL,
            author_id INT NOT NULL,
            title VARCHAR(200) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            thumbnail VARCHAR(255) DEFAULT NULL,
            slug VARCHAR(250) NOT NULL,
            videos_count INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_3A10012DF675F31B (author_id),
            UNIQUE INDEX UNIQ_3A10012D989D9B62 (slug),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Season table
        $this->addSql('CREATE TABLE season (
            id INT AUTO_INCREMENT NOT NULL,
            series_id INT NOT NULL,
            number INT NOT NULL DEFAULT 1,
            title VARCHAR(200) DEFAULT NULL,
            INDEX IDX_F0E45BA95278319C (series_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Add foreign keys
        $this->addSql('ALTER TABLE playlist ADD CONSTRAINT FK_D782112D7E3C61F9 FOREIGN KEY (owner_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE playlist_video ADD CONSTRAINT FK_F542A1E36BBD148 FOREIGN KEY (playlist_id) REFERENCES playlist (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE playlist_video ADD CONSTRAINT FK_F542A1E329C1004E FOREIGN KEY (video_id) REFERENCES video (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watch_history ADD CONSTRAINT FK_DE44EFD8A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watch_history ADD CONSTRAINT FK_DE44EFD829C1004E FOREIGN KEY (video_id) REFERENCES video (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE bookmark ADD CONSTRAINT FK_DA62921DA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE bookmark ADD CONSTRAINT FK_DA62921D29C1004E FOREIGN KEY (video_id) REFERENCES video (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE series ADD CONSTRAINT FK_3A10012DF675F31B FOREIGN KEY (author_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE season ADD CONSTRAINT FK_F0E45BA95278319C FOREIGN KEY (series_id) REFERENCES series (id) ON DELETE CASCADE');

        // Update video table - add season, episode_number, animated_preview
        $this->addSql('ALTER TABLE video ADD season_id INT DEFAULT NULL, ADD episode_number INT DEFAULT NULL, ADD animated_preview VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE video ADD CONSTRAINT FK_7CC7DA2C4EC001D1 FOREIGN KEY (season_id) REFERENCES season (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_7CC7DA2C4EC001D1 ON video (season_id)');

        // Update video_like table - change type to is_like
        $this->addSql('ALTER TABLE video_like ADD is_like TINYINT(1) NOT NULL DEFAULT 1');
        $this->addSql('UPDATE video_like SET is_like = CASE WHEN type = \'like\' THEN 1 ELSE 0 END');
        $this->addSql('ALTER TABLE video_like DROP COLUMN type');
    }

    public function down(Schema $schema): void
    {
        // Restore video_like type column
        $this->addSql('ALTER TABLE video_like ADD type VARCHAR(10) NOT NULL DEFAULT \'like\'');
        $this->addSql('UPDATE video_like SET type = CASE WHEN is_like = 1 THEN \'like\' ELSE \'dislike\' END');
        $this->addSql('ALTER TABLE video_like DROP COLUMN is_like');

        // Remove video columns
        $this->addSql('ALTER TABLE video DROP FOREIGN KEY FK_7CC7DA2C4EC001D1');
        $this->addSql('DROP INDEX IDX_7CC7DA2C4EC001D1 ON video');
        $this->addSql('ALTER TABLE video DROP season_id, DROP episode_number, DROP animated_preview');

        // Drop tables in reverse order
        $this->addSql('ALTER TABLE season DROP FOREIGN KEY FK_F0E45BA95278319C');
        $this->addSql('ALTER TABLE series DROP FOREIGN KEY FK_3A10012DF675F31B');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CAA76ED395');
        $this->addSql('ALTER TABLE bookmark DROP FOREIGN KEY FK_DA62921D29C1004E');
        $this->addSql('ALTER TABLE bookmark DROP FOREIGN KEY FK_DA62921DA76ED395');
        $this->addSql('ALTER TABLE watch_history DROP FOREIGN KEY FK_DE44EFD829C1004E');
        $this->addSql('ALTER TABLE watch_history DROP FOREIGN KEY FK_DE44EFD8A76ED395');
        $this->addSql('ALTER TABLE playlist_video DROP FOREIGN KEY FK_F542A1E329C1004E');
        $this->addSql('ALTER TABLE playlist_video DROP FOREIGN KEY FK_F542A1E36BBD148');
        $this->addSql('ALTER TABLE playlist DROP FOREIGN KEY FK_D782112D7E3C61F9');

        $this->addSql('DROP TABLE season');
        $this->addSql('DROP TABLE series');
        $this->addSql('DROP TABLE notification');
        $this->addSql('DROP TABLE bookmark');
        $this->addSql('DROP TABLE watch_history');
        $this->addSql('DROP TABLE playlist_video');
        $this->addSql('DROP TABLE playlist');
    }
}
