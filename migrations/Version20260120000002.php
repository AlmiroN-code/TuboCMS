<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Creates missing role_permission junction table
 */
final class Version20260120000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create role_permission junction table for many-to-many relationship';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('role_permission')) {
            $this->addSql('CREATE TABLE role_permission (
                role_id INT NOT NULL,
                permission_id INT NOT NULL,
                INDEX IDX_role_permission_role (role_id),
                INDEX IDX_role_permission_permission (permission_id),
                PRIMARY KEY (role_id, permission_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
            
            $this->addSql('ALTER TABLE role_permission ADD CONSTRAINT FK_role_permission_role 
                FOREIGN KEY (role_id) REFERENCES role (id) ON DELETE CASCADE');
            $this->addSql('ALTER TABLE role_permission ADD CONSTRAINT FK_role_permission_permission 
                FOREIGN KEY (permission_id) REFERENCES permission (id) ON DELETE CASCADE');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('role_permission')) {
            $this->addSql('DROP TABLE role_permission');
        }
    }
}
