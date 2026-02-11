<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260208021704 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE playlist_subscriptions (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, playlist_id INT NOT NULL, INDEX idx_playlist_subscription_user (user_id), INDEX idx_playlist_subscription_playlist (playlist_id), UNIQUE INDEX unique_playlist_subscription (user_id, playlist_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE playlist_subscriptions ADD CONSTRAINT FK_12B0B1B4A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE playlist_subscriptions ADD CONSTRAINT FK_12B0B1B46BBD148 FOREIGN KEY (playlist_id) REFERENCES channel_playlists (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE channel_playlists RENAME INDEX share_token TO UNIQ_F17BBA50D6594DD6');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE playlist_subscriptions DROP FOREIGN KEY FK_12B0B1B4A76ED395');
        $this->addSql('ALTER TABLE playlist_subscriptions DROP FOREIGN KEY FK_12B0B1B46BBD148');
        $this->addSql('DROP TABLE playlist_subscriptions');
        $this->addSql('ALTER TABLE channel_playlists RENAME INDEX uniq_f17bba50d6594dd6 TO share_token');
    }
}
