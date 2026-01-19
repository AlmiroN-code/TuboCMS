<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251222000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create video_encoding_profile table and add default profiles';
    }

    public function up(Schema $schema): void
    {
        // Create video_encoding_profile table
        $this->addSql('CREATE TABLE video_encoding_profile (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(50) NOT NULL,
            resolution VARCHAR(20) NOT NULL,
            bitrate INT NOT NULL,
            codec VARCHAR(10) NOT NULL DEFAULT "libx264",
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            order_position INT NOT NULL DEFAULT 0,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Insert default encoding profiles
        $this->addSql("INSERT INTO video_encoding_profile (name, resolution, bitrate, codec, is_active, order_position) VALUES
            ('360p', '640x360', 800, 'libx264', 1, 1),
            ('480p', '854x480', 1200, 'libx264', 1, 2),
            ('720p', '1280x720', 2500, 'libx264', 1, 3),
            ('1080p', '1920x1080', 5000, 'libx264', 1, 4)
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE video_encoding_profile');
    }
}
