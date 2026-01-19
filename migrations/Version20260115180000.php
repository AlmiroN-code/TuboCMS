<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Добавление SEO полей в таблицу model_profile
 */
final class Version20260115180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add SEO fields (meta_title, meta_description, meta_keywords) to model_profile table';
    }

    public function up(Schema $schema): void
    {
        // Проверяем, существуют ли уже колонки
        $columns = $this->connection->fetchAllAssociative('SHOW COLUMNS FROM model_profile');
        $columnNames = array_column($columns, 'Field');
        
        if (!in_array('meta_title', $columnNames)) {
            $this->addSql('ALTER TABLE model_profile ADD meta_title VARCHAR(255) DEFAULT NULL');
        }
        if (!in_array('meta_description', $columnNames)) {
            $this->addSql('ALTER TABLE model_profile ADD meta_description LONGTEXT DEFAULT NULL');
        }
        if (!in_array('meta_keywords', $columnNames)) {
            $this->addSql('ALTER TABLE model_profile ADD meta_keywords VARCHAR(500) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE model_profile DROP COLUMN meta_title');
        $this->addSql('ALTER TABLE model_profile DROP COLUMN meta_description');
        $this->addSql('ALTER TABLE model_profile DROP COLUMN meta_keywords');
    }
}
