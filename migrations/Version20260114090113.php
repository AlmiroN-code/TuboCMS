<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260114090113 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Добавляет таблицу watch_later для функционала "Смотреть позже"';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE watch_later (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, video_id INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_1E3051A7A76ED395 (user_id), INDEX IDX_1E3051A729C1004E (video_id), INDEX idx_watch_later_created (created_at), UNIQUE INDEX watch_later_user_video_unique (user_id, video_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE watch_later ADD CONSTRAINT FK_1E3051A7A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watch_later ADD CONSTRAINT FK_1E3051A729C1004E FOREIGN KEY (video_id) REFERENCES video (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE watch_later DROP FOREIGN KEY FK_1E3051A7A76ED395');
        $this->addSql('ALTER TABLE watch_later DROP FOREIGN KEY FK_1E3051A729C1004E');
        $this->addSql('DROP TABLE watch_later');
    }
}
