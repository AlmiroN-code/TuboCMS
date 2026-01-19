<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Миграция для раздела моделей: создание таблиц model_subscription и model_like,
 * добавление поля dislikes_count в model_profile
 */
final class Version20251229000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Создание таблиц model_subscription и model_like, добавление поля dislikes_count в model_profile';
    }

    public function up(Schema $schema): void
    {
        // Создание таблицы model_subscription
        $this->addSql('CREATE TABLE model_subscription (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            model_id INT NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_MODEL_SUB_USER (user_id),
            INDEX IDX_MODEL_SUB_MODEL (model_id),
            UNIQUE INDEX unique_model_subscription (user_id, model_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Создание таблицы model_like
        $this->addSql('CREATE TABLE model_like (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            model_id INT NOT NULL,
            type VARCHAR(10) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_MODEL_LIKE_USER (user_id),
            INDEX IDX_MODEL_LIKE_MODEL (model_id),
            UNIQUE INDEX unique_model_like (user_id, model_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Добавление внешних ключей для model_subscription
        $this->addSql('ALTER TABLE model_subscription ADD CONSTRAINT FK_MODEL_SUB_USER FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE model_subscription ADD CONSTRAINT FK_MODEL_SUB_MODEL FOREIGN KEY (model_id) REFERENCES model_profile (id) ON DELETE CASCADE');

        // Добавление внешних ключей для model_like
        $this->addSql('ALTER TABLE model_like ADD CONSTRAINT FK_MODEL_LIKE_USER FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE model_like ADD CONSTRAINT FK_MODEL_LIKE_MODEL FOREIGN KEY (model_id) REFERENCES model_profile (id) ON DELETE CASCADE');

        // Добавление поля dislikes_count в model_profile
        $this->addSql('ALTER TABLE model_profile ADD dislikes_count INT NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        // Удаление внешних ключей
        $this->addSql('ALTER TABLE model_subscription DROP FOREIGN KEY FK_MODEL_SUB_USER');
        $this->addSql('ALTER TABLE model_subscription DROP FOREIGN KEY FK_MODEL_SUB_MODEL');
        $this->addSql('ALTER TABLE model_like DROP FOREIGN KEY FK_MODEL_LIKE_USER');
        $this->addSql('ALTER TABLE model_like DROP FOREIGN KEY FK_MODEL_LIKE_MODEL');

        // Удаление таблиц
        $this->addSql('DROP TABLE model_subscription');
        $this->addSql('DROP TABLE model_like');

        // Удаление поля dislikes_count из model_profile
        $this->addSql('ALTER TABLE model_profile DROP dislikes_count');
    }
}
