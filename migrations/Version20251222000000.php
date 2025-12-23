<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251222000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add default video encoding profiles';
    }

    public function up(Schema $schema): void
    {
        // Добавляем профили кодирования по умолчанию
        $this->addSql("INSERT INTO video_encoding_profile (name, resolution, bitrate, codec, is_active, order_position) VALUES 
            ('360p', '640x360', 800, 'libx264', 1, 1),
            ('480p', '854x480', 1200, 'libx264', 1, 2),
            ('720p HD', '1280x720', 2500, 'libx264', 1, 3),
            ('1080p Full HD', '1920x1080', 5000, 'libx264', 1, 4)
        ");
    }

    public function down(Schema $schema): void
    {
        // Удаляем профили
        $this->addSql("DELETE FROM video_encoding_profile WHERE name IN ('360p', '480p', '720p HD', '1080p Full HD')");
    }
}
