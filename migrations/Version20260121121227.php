<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260121121227 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE content_protection_setting (id INT AUTO_INCREMENT NOT NULL, setting_key VARCHAR(255) NOT NULL, setting_value LONGTEXT DEFAULT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_A939ECCF5FA1E697 (setting_key), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE model_like (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(10) NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, model_id INT NOT NULL, INDEX IDX_81346C54A76ED395 (user_id), INDEX IDX_81346C547975B7E7 (model_id), UNIQUE INDEX unique_model_like (user_id, model_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE model_subscription (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, model_id INT NOT NULL, INDEX IDX_A14C8B9A76ED395 (user_id), INDEX IDX_A14C8B97975B7E7 (model_id), UNIQUE INDEX unique_model_subscription (user_id, model_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE notification (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(50) NOT NULL, data JSON NOT NULL, is_read TINYINT NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_BF5476CAA76ED395 (user_id), INDEX idx_user_unread (user_id, is_read, created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE playlist (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(200) NOT NULL, description LONGTEXT DEFAULT NULL, thumbnail VARCHAR(255) DEFAULT NULL, is_public TINYINT NOT NULL, videos_count INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, owner_id INT NOT NULL, INDEX IDX_D782112D7E3C61F9 (owner_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE playlist_video (id INT AUTO_INCREMENT NOT NULL, position INT NOT NULL, added_at DATETIME NOT NULL, playlist_id INT NOT NULL, video_id INT NOT NULL, INDEX IDX_DFDBC36F6BBD148 (playlist_id), INDEX IDX_DFDBC36F29C1004E (video_id), UNIQUE INDEX unique_playlist_video (playlist_id, video_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE season (id INT AUTO_INCREMENT NOT NULL, number INT NOT NULL, title VARCHAR(200) DEFAULT NULL, series_id INT NOT NULL, INDEX IDX_F0E45BA95278319C (series_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE series (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(200) NOT NULL, description LONGTEXT DEFAULT NULL, thumbnail VARCHAR(255) DEFAULT NULL, slug VARCHAR(250) NOT NULL, videos_count INT NOT NULL, created_at DATETIME NOT NULL, author_id INT NOT NULL, UNIQUE INDEX UNIQ_3A10012D989D9B62 (slug), INDEX IDX_3A10012DF675F31B (author_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE storage (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, type VARCHAR(20) NOT NULL, config JSON NOT NULL, is_default TINYINT NOT NULL, is_enabled TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE video_category (video_id INT NOT NULL, category_id INT NOT NULL, INDEX IDX_AECE2B7D29C1004E (video_id), INDEX IDX_AECE2B7D12469DE2 (category_id), PRIMARY KEY (video_id, category_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE watch_history (id INT AUTO_INCREMENT NOT NULL, watched_seconds INT NOT NULL, watch_progress INT NOT NULL, watched_at DATETIME NOT NULL, user_id INT NOT NULL, video_id INT NOT NULL, INDEX IDX_DE44EFD8A76ED395 (user_id), INDEX IDX_DE44EFD829C1004E (video_id), INDEX idx_user_watched (user_id, watched_at), UNIQUE INDEX unique_user_video (user_id, video_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE watch_later (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, video_id INT NOT NULL, INDEX IDX_1E3051A729C1004E (video_id), INDEX idx_watch_later_user (user_id), INDEX idx_watch_later_created (created_at), UNIQUE INDEX watch_later_user_video_unique (user_id, video_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE model_like ADD CONSTRAINT FK_81346C54A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE model_like ADD CONSTRAINT FK_81346C547975B7E7 FOREIGN KEY (model_id) REFERENCES model_profile (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE model_subscription ADD CONSTRAINT FK_A14C8B9A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE model_subscription ADD CONSTRAINT FK_A14C8B97975B7E7 FOREIGN KEY (model_id) REFERENCES model_profile (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE playlist ADD CONSTRAINT FK_D782112D7E3C61F9 FOREIGN KEY (owner_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE playlist_video ADD CONSTRAINT FK_DFDBC36F6BBD148 FOREIGN KEY (playlist_id) REFERENCES playlist (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE playlist_video ADD CONSTRAINT FK_DFDBC36F29C1004E FOREIGN KEY (video_id) REFERENCES video (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE season ADD CONSTRAINT FK_F0E45BA95278319C FOREIGN KEY (series_id) REFERENCES series (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE series ADD CONSTRAINT FK_3A10012DF675F31B FOREIGN KEY (author_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE video_category ADD CONSTRAINT FK_AECE2B7D29C1004E FOREIGN KEY (video_id) REFERENCES video (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE video_category ADD CONSTRAINT FK_AECE2B7D12469DE2 FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watch_history ADD CONSTRAINT FK_DE44EFD8A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watch_history ADD CONSTRAINT FK_DE44EFD829C1004E FOREIGN KEY (video_id) REFERENCES video (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watch_later ADD CONSTRAINT FK_1E3051A7A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watch_later ADD CONSTRAINT FK_1E3051A729C1004E FOREIGN KEY (video_id) REFERENCES video (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ad ADD CONSTRAINT FK_77E0ED582F966E9D FOREIGN KEY (placement_id) REFERENCES ad_placement (id)');
        $this->addSql('ALTER TABLE ad ADD CONSTRAINT FK_77E0ED58F639F774 FOREIGN KEY (campaign_id) REFERENCES ad_campaign (id)');
        $this->addSql('ALTER TABLE ad ADD CONSTRAINT FK_77E0ED58B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE ad ADD CONSTRAINT FK_77E0ED58A00D9457 FOREIGN KEY (ab_test_id) REFERENCES ad_ab_test (id)');
        $this->addSql('ALTER TABLE ad_segment_relation ADD CONSTRAINT FK_C6F9F5084F34D596 FOREIGN KEY (ad_id) REFERENCES ad (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ad_segment_relation ADD CONSTRAINT FK_C6F9F50824A03917 FOREIGN KEY (ad_segment_id) REFERENCES ad_segment (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ad_ab_test ADD CONSTRAINT FK_7BA326F0B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE ad_campaign ADD CONSTRAINT FK_F50D1F0DB03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE ad_statistic ADD CONSTRAINT FK_E2A28C454F34D596 FOREIGN KEY (ad_id) REFERENCES ad (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE bookmark ADD CONSTRAINT FK_DA62921DA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE bookmark ADD CONSTRAINT FK_DA62921D29C1004E FOREIGN KEY (video_id) REFERENCES video (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE category ADD poster VARCHAR(255) DEFAULT NULL, ADD meta_title VARCHAR(255) DEFAULT NULL, ADD meta_description LONGTEXT DEFAULT NULL, ADD meta_keywords VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE comment ADD moderation_status VARCHAR(20) NOT NULL');
        $this->addSql('CREATE INDEX idx_moderation_status ON comment (moderation_status)');
        $this->addSql('ALTER TABLE model_profile DROP FOREIGN KEY `FK_E1C4C3FEA76ED395`');
        $this->addSql('ALTER TABLE model_profile ADD aliases JSON DEFAULT NULL, ADD dislikes_count INT NOT NULL, ADD meta_title VARCHAR(255) DEFAULT NULL, ADD meta_description LONGTEXT DEFAULT NULL, ADD meta_keywords VARCHAR(500) DEFAULT NULL, CHANGE user_id user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE model_profile ADD CONSTRAINT FK_E1C4C3FEA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tag ADD description LONGTEXT DEFAULT NULL, ADD meta_title VARCHAR(255) DEFAULT NULL, ADD meta_description LONGTEXT DEFAULT NULL, ADD meta_keywords VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD cover_image VARCHAR(255) DEFAULT NULL, ADD city VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE video DROP FOREIGN KEY `FK_7CC7DA2C12469DE2`');
        $this->addSql('DROP INDEX IDX_7CC7DA2C12469DE2 ON video');
        $this->addSql('ALTER TABLE video ADD impressions_count INT NOT NULL, ADD animated_preview VARCHAR(255) DEFAULT NULL, ADD season_id INT DEFAULT NULL, CHANGE category_id episode_number INT DEFAULT NULL');
        $this->addSql('ALTER TABLE video ADD CONSTRAINT FK_7CC7DA2C4EC001D1 FOREIGN KEY (season_id) REFERENCES season (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_7CC7DA2C4EC001D1 ON video (season_id)');
        $this->addSql('ALTER TABLE video_file ADD remote_path VARCHAR(500) DEFAULT NULL, ADD storage_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE video_file ADD CONSTRAINT FK_8B086BCC5CC5DB90 FOREIGN KEY (storage_id) REFERENCES storage (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_8B086BCC5CC5DB90 ON video_file (storage_id)');
        $this->addSql('ALTER TABLE video_like ADD is_like TINYINT NOT NULL, DROP type');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE model_like DROP FOREIGN KEY FK_81346C54A76ED395');
        $this->addSql('ALTER TABLE model_like DROP FOREIGN KEY FK_81346C547975B7E7');
        $this->addSql('ALTER TABLE model_subscription DROP FOREIGN KEY FK_A14C8B9A76ED395');
        $this->addSql('ALTER TABLE model_subscription DROP FOREIGN KEY FK_A14C8B97975B7E7');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CAA76ED395');
        $this->addSql('ALTER TABLE playlist DROP FOREIGN KEY FK_D782112D7E3C61F9');
        $this->addSql('ALTER TABLE playlist_video DROP FOREIGN KEY FK_DFDBC36F6BBD148');
        $this->addSql('ALTER TABLE playlist_video DROP FOREIGN KEY FK_DFDBC36F29C1004E');
        $this->addSql('ALTER TABLE season DROP FOREIGN KEY FK_F0E45BA95278319C');
        $this->addSql('ALTER TABLE series DROP FOREIGN KEY FK_3A10012DF675F31B');
        $this->addSql('ALTER TABLE video_category DROP FOREIGN KEY FK_AECE2B7D29C1004E');
        $this->addSql('ALTER TABLE video_category DROP FOREIGN KEY FK_AECE2B7D12469DE2');
        $this->addSql('ALTER TABLE watch_history DROP FOREIGN KEY FK_DE44EFD8A76ED395');
        $this->addSql('ALTER TABLE watch_history DROP FOREIGN KEY FK_DE44EFD829C1004E');
        $this->addSql('ALTER TABLE watch_later DROP FOREIGN KEY FK_1E3051A7A76ED395');
        $this->addSql('ALTER TABLE watch_later DROP FOREIGN KEY FK_1E3051A729C1004E');
        $this->addSql('DROP TABLE content_protection_setting');
        $this->addSql('DROP TABLE model_like');
        $this->addSql('DROP TABLE model_subscription');
        $this->addSql('DROP TABLE notification');
        $this->addSql('DROP TABLE playlist');
        $this->addSql('DROP TABLE playlist_video');
        $this->addSql('DROP TABLE season');
        $this->addSql('DROP TABLE series');
        $this->addSql('DROP TABLE storage');
        $this->addSql('DROP TABLE video_category');
        $this->addSql('DROP TABLE watch_history');
        $this->addSql('DROP TABLE watch_later');
        $this->addSql('DROP TABLE messenger_messages');
        $this->addSql('ALTER TABLE ad DROP FOREIGN KEY FK_77E0ED582F966E9D');
        $this->addSql('ALTER TABLE ad DROP FOREIGN KEY FK_77E0ED58F639F774');
        $this->addSql('ALTER TABLE ad DROP FOREIGN KEY FK_77E0ED58B03A8386');
        $this->addSql('ALTER TABLE ad DROP FOREIGN KEY FK_77E0ED58A00D9457');
        $this->addSql('ALTER TABLE ad_ab_test DROP FOREIGN KEY FK_7BA326F0B03A8386');
        $this->addSql('ALTER TABLE ad_campaign DROP FOREIGN KEY FK_F50D1F0DB03A8386');
        $this->addSql('ALTER TABLE ad_segment_relation DROP FOREIGN KEY FK_C6F9F5084F34D596');
        $this->addSql('ALTER TABLE ad_segment_relation DROP FOREIGN KEY FK_C6F9F50824A03917');
        $this->addSql('ALTER TABLE ad_statistic DROP FOREIGN KEY FK_E2A28C454F34D596');
        $this->addSql('ALTER TABLE bookmark DROP FOREIGN KEY FK_DA62921DA76ED395');
        $this->addSql('ALTER TABLE bookmark DROP FOREIGN KEY FK_DA62921D29C1004E');
        $this->addSql('ALTER TABLE category DROP poster, DROP meta_title, DROP meta_description, DROP meta_keywords');
        $this->addSql('DROP INDEX idx_moderation_status ON comment');
        $this->addSql('ALTER TABLE comment DROP moderation_status');
        $this->addSql('ALTER TABLE model_profile DROP FOREIGN KEY FK_E1C4C3FEA76ED395');
        $this->addSql('ALTER TABLE model_profile DROP aliases, DROP dislikes_count, DROP meta_title, DROP meta_description, DROP meta_keywords, CHANGE user_id user_id INT NOT NULL');
        $this->addSql('ALTER TABLE model_profile ADD CONSTRAINT `FK_E1C4C3FEA76ED395` FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE tag DROP description, DROP meta_title, DROP meta_description, DROP meta_keywords');
        $this->addSql('ALTER TABLE `user` DROP cover_image, DROP city');
        $this->addSql('ALTER TABLE video DROP FOREIGN KEY FK_7CC7DA2C4EC001D1');
        $this->addSql('DROP INDEX IDX_7CC7DA2C4EC001D1 ON video');
        $this->addSql('ALTER TABLE video ADD category_id INT DEFAULT NULL, DROP impressions_count, DROP episode_number, DROP animated_preview, DROP season_id');
        $this->addSql('ALTER TABLE video ADD CONSTRAINT `FK_7CC7DA2C12469DE2` FOREIGN KEY (category_id) REFERENCES category (id) ON UPDATE NO ACTION ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_7CC7DA2C12469DE2 ON video (category_id)');
        $this->addSql('ALTER TABLE video_file DROP FOREIGN KEY FK_8B086BCC5CC5DB90');
        $this->addSql('DROP INDEX IDX_8B086BCC5CC5DB90 ON video_file');
        $this->addSql('ALTER TABLE video_file DROP remote_path, DROP storage_id');
        $this->addSql('ALTER TABLE video_like ADD type VARCHAR(10) NOT NULL, DROP is_like');
    }
}
