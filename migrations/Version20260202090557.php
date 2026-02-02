<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Создание FULLTEXT индексов для поиска (Production Safe)
 */
final class Version20260202090557 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Создание FULLTEXT индексов для быстрого поиска (Production Safe)';
    }

    public function up(Schema $schema): void
    {
        // Проверяем и создаем FULLTEXT индекс для видео (title, description)
        $this->addSql("
            SET @sql = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                 WHERE table_schema = DATABASE() 
                 AND table_name = 'video' 
                 AND index_name = 'ft_video_title_description') = 0,
                'ALTER TABLE video ADD FULLTEXT ft_video_title_description (title, description)',
                'SELECT \"FULLTEXT index ft_video_title_description already exists on video table\"'
            ));
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ");
        
        // Проверяем и создаем FULLTEXT индекс для тегов
        $this->addSql("
            SET @sql = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                 WHERE table_schema = DATABASE() 
                 AND table_name = 'tag' 
                 AND index_name = 'ft_tag_name') = 0,
                'ALTER TABLE tag ADD FULLTEXT ft_tag_name (name)',
                'SELECT \"FULLTEXT index ft_tag_name already exists on tag table\"'
            ));
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ");
        
        // Проверяем и создаем FULLTEXT индекс для категорий
        $this->addSql("
            SET @sql = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                 WHERE table_schema = DATABASE() 
                 AND table_name = 'category' 
                 AND index_name = 'ft_category_name_description') = 0,
                'ALTER TABLE category ADD FULLTEXT ft_category_name_description (name, description)',
                'SELECT \"FULLTEXT index ft_category_name_description already exists on category table\"'
            ));
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ");
        
        // Проверяем и создаем FULLTEXT индекс для моделей
        $this->addSql("
            SET @sql = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                 WHERE table_schema = DATABASE() 
                 AND table_name = 'model_profile' 
                 AND index_name = 'ft_model_display_name_bio') = 0,
                'ALTER TABLE model_profile ADD FULLTEXT ft_model_display_name_bio (display_name, bio)',
                'SELECT \"FULLTEXT index ft_model_display_name_bio already exists on model_profile table\"'
            ));
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ");
    }

    public function down(Schema $schema): void
    {
        // Безопасное удаление FULLTEXT индексов с проверками
        $this->addSql("
            SET @sql = (SELECT IF(
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                 WHERE table_schema = DATABASE() 
                 AND table_name = 'video' 
                 AND index_name = 'ft_video_title_description') > 0,
                'ALTER TABLE video DROP INDEX ft_video_title_description',
                'SELECT \"FULLTEXT index ft_video_title_description does not exist on video table\"'
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
                 AND index_name = 'ft_tag_name') > 0,
                'ALTER TABLE tag DROP INDEX ft_tag_name',
                'SELECT \"FULLTEXT index ft_tag_name does not exist on tag table\"'
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
                 AND index_name = 'ft_category_name_description') > 0,
                'ALTER TABLE category DROP INDEX ft_category_name_description',
                'SELECT \"FULLTEXT index ft_category_name_description does not exist on category table\"'
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
                 AND index_name = 'ft_model_display_name_bio') > 0,
                'ALTER TABLE model_profile DROP INDEX ft_model_display_name_bio',
                'SELECT \"FULLTEXT index ft_model_display_name_bio does not exist on model_profile table\"'
            ));
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ");
    }
}