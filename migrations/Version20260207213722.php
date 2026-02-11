<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260207213722 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE playlist_collaborators (id INT AUTO_INCREMENT NOT NULL, permission VARCHAR(20) DEFAULT \'add\' NOT NULL, added_at DATETIME NOT NULL, playlist_id INT NOT NULL, user_id INT NOT NULL, added_by_id INT DEFAULT NULL, INDEX IDX_541AA4E155B127A4 (added_by_id), INDEX idx_collaborator_playlist (playlist_id), INDEX idx_collaborator_user (user_id), UNIQUE INDEX unique_playlist_user (playlist_id, user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE playlist_collaborators ADD CONSTRAINT FK_541AA4E16BBD148 FOREIGN KEY (playlist_id) REFERENCES channel_playlists (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE playlist_collaborators ADD CONSTRAINT FK_541AA4E1A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE playlist_collaborators ADD CONSTRAINT FK_541AA4E155B127A4 FOREIGN KEY (added_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE channel_playlists ADD is_collaborative TINYINT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE playlist_collaborators DROP FOREIGN KEY FK_541AA4E16BBD148');
        $this->addSql('ALTER TABLE playlist_collaborators DROP FOREIGN KEY FK_541AA4E1A76ED395');
        $this->addSql('ALTER TABLE playlist_collaborators DROP FOREIGN KEY FK_541AA4E155B127A4');
        $this->addSql('DROP TABLE playlist_collaborators');
        $this->addSql('ALTER TABLE channel_playlists DROP is_collaborative');
    }
}
