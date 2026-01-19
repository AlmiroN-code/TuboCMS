<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251229100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create model_subscription table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE model_subscription (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            model_id INT NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_MODEL_SUB_USER (user_id),
            INDEX IDX_MODEL_SUB_MODEL (model_id),
            UNIQUE INDEX unique_model_subscription (user_id, model_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_MODEL_SUB_USER FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE,
            CONSTRAINT FK_MODEL_SUB_MODEL FOREIGN KEY (model_id) REFERENCES model_profile (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE model_subscription');
    }
}
