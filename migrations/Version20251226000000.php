<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Миграция для создания таблицы storage и добавления полей storage_id, remote_path в video_file
 */
final class Version20251226000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create storage table and add storage_id, remote_path columns to video_file table';
    }

    public function up(Schema $schema): void
    {
        // Создаём таблицу storage
        $this->addSql('CREATE TABLE storage (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(100) NOT NULL,
            type VARCHAR(20) NOT NULL,
            config JSON NOT NULL,
            is_default TINYINT(1) NOT NULL DEFAULT 0,
            is_enabled TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Добавляем индекс для быстрого поиска дефолтного хранилища
        $this->addSql('CREATE INDEX idx_storage_default ON storage (is_default)');
        
        // Добавляем индекс для поиска по типу
        $this->addSql('CREATE INDEX idx_storage_type ON storage (type)');
        
        // Добавляем индекс для поиска активных хранилищ
        $this->addSql('CREATE INDEX idx_storage_enabled ON storage (is_enabled)');

        // Добавляем колонки в video_file
        $this->addSql('ALTER TABLE video_file ADD storage_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE video_file ADD remote_path VARCHAR(500) DEFAULT NULL');

        // Добавляем внешний ключ
        $this->addSql('ALTER TABLE video_file ADD CONSTRAINT FK_8B086BCC5CC5DB90 FOREIGN KEY (storage_id) REFERENCES storage (id) ON DELETE SET NULL');
        
        // Добавляем индекс для storage_id
        $this->addSql('CREATE INDEX idx_video_file_storage ON video_file (storage_id)');
    }

    public function down(Schema $schema): void
    {
        // Удаляем внешний ключ и индекс из video_file
        $this->addSql('ALTER TABLE video_file DROP FOREIGN KEY FK_8B086BCC5CC5DB90');
        $this->addSql('DROP INDEX idx_video_file_storage ON video_file');
        
        // Удаляем колонки из video_file
        $this->addSql('ALTER TABLE video_file DROP COLUMN storage_id');
        $this->addSql('ALTER TABLE video_file DROP COLUMN remote_path');

        // Удаляем таблицу storage
        $this->addSql('DROP TABLE storage');
    }
}
