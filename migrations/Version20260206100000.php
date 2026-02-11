<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260206100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add email_verification_token table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE email_verification_token (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            token VARCHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX UNIQ_email_token (token),
            INDEX IDX_email_user (user_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        
        $this->addSql('ALTER TABLE email_verification_token ADD CONSTRAINT FK_email_user_id FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE email_verification_token DROP FOREIGN KEY FK_email_user_id');
        $this->addSql('DROP TABLE email_verification_token');
    }
}
