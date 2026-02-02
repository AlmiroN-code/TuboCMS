<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Исправление FULLTEXT индексов для поиска
 * Удаляет старый индекс ft_title и создает новый ft_title_description
 */
final class Version20260115191000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix FULLTEXT indexes - replace ft_title with ft_title_description';
    }

    public function up(Schema $schema): void
    {
        // Безопасное удаление старого индекса ft_title если существует
        $this->addSql("
            SET @sql = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                 WHERE table_schema = DATABASE() 
                 AND table_name = 'video' 
                 AND index_name = 'ft_title') > 0,
                'ALTER TABLE video DROP INDEX ft_title',
                'SELECT \"Index ft_title does not exist on video table\"'
            ));
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ");
        
        // Безопасное создание нового индекса
        $this->addSql("
            SET @sql = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                 WHERE table_schema = DATABASE() 
                 AND table_name = 'video' 
                 AND index_name = 'ft_title_description') = 0,
                'ALTER TABLE video ADD FULLTEXT INDEX ft_title_description (title, description)',
                'SELECT \"Index ft_title_description already exists on video table\"'
            ));
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE video DROP INDEX IF EXISTS ft_title_description');
    }
}
