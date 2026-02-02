<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Очистка дублирующихся FULLTEXT индексов (Production Safe)
 */
final class Version20260202090712 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Очистка дублирующихся FULLTEXT индексов для оптимизации';
    }

    public function up(Schema $schema): void
    {
        // Удаляем старые дублирующиеся индексы, оставляем только именованные
        
        // Видео - удаляем безымянные индексы
        $this->addSql("
            SET @sql = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                 WHERE table_schema = DATABASE() 
                 AND table_name = 'video' 
                 AND index_name = 'title') > 0,
                'ALTER TABLE video DROP INDEX title',
                'SELECT \"Index title does not exist on video table\"'
            ));
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ");
        
        $this->addSql("
            SET @sql = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                 WHERE table_schema = DATABASE() 
                 AND table_name = 'video' 
                 AND index_name = 'title_2') > 0,
                'ALTER TABLE video DROP INDEX title_2',
                'SELECT \"Index title_2 does not exist on video table\"'
            ));
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ");

        // Теги - удаляем безымянные индексы
        $this->addSql("
            SET @sql = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                 WHERE table_schema = DATABASE() 
                 AND table_name = 'tag' 
                 AND index_name = 'name') > 0,
                'ALTER TABLE tag DROP INDEX name',
                'SELECT \"Index name does not exist on tag table\"'
            ));
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ");
        
        $this->addSql("
            SET @sql = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                 WHERE table_schema = DATABASE() 
                 AND table_name = 'tag' 
                 AND index_name = 'name_2') > 0,
                'ALTER TABLE tag DROP INDEX name_2',
                'SELECT \"Index name_2 does not exist on tag table\"'
            ));
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ");

        // Категории - удаляем безымянные индексы
        $this->addSql("
            SET @sql = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                 WHERE table_schema = DATABASE() 
                 AND table_name = 'category' 
                 AND index_name = 'name') > 0,
                'ALTER TABLE category DROP INDEX name',
                'SELECT \"Index name does not exist on category table\"'
            ));
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ");
        
        $this->addSql("
            SET @sql = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                 WHERE table_schema = DATABASE() 
                 AND table_name = 'category' 
                 AND index_name = 'name_2') > 0,
                'ALTER TABLE category DROP INDEX name_2',
                'SELECT \"Index name_2 does not exist on category table\"'
            ));
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ");

        // Модели - удаляем безымянные индексы
        $this->addSql("
            SET @sql = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                 WHERE table_schema = DATABASE() 
                 AND table_name = 'model_profile' 
                 AND index_name = 'display_name') > 0,
                'ALTER TABLE model_profile DROP INDEX display_name',
                'SELECT \"Index display_name does not exist on model_profile table\"'
            ));
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ");
        
        $this->addSql("
            SET @sql = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                 WHERE table_schema = DATABASE() 
                 AND table_name = 'model_profile' 
                 AND index_name = 'display_name_2') > 0,
                'ALTER TABLE model_profile DROP INDEX display_name_2',
                'SELECT \"Index display_name_2 does not exist on model_profile table\"'
            ));
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ");
    }

    public function down(Schema $schema): void
    {
        // В rollback не восстанавливаем дублирующиеся индексы
        // Основные именованные индексы остаются
        $this->addSql('SELECT "Rollback: duplicate indexes cleanup - no action needed"');
    }
}