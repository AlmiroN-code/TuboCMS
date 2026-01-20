<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Creates missing user_role junction table
 */
final class Version20260120000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create user_role junction table for many-to-many relationship';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('user_role')) {
            $this->addSql('CREATE TABLE user_role (
                user_id INT NOT NULL,
                role_id INT NOT NULL,
                INDEX IDX_user_role_user (user_id),
                INDEX IDX_user_role_role (role_id),
                PRIMARY KEY (user_id, role_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
            
            $this->addSql('ALTER TABLE user_role ADD CONSTRAINT FK_user_role_user 
                FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
            $this->addSql('ALTER TABLE user_role ADD CONSTRAINT FK_user_role_role 
                FOREIGN KEY (role_id) REFERENCES role (id) ON DELETE CASCADE');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('user_role')) {
            $this->addSql('DROP TABLE user_role');
        }
    }
}
