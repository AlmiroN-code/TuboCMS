<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251207172727 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, slug VARCHAR(150) NOT NULL, description VARCHAR(255) DEFAULT NULL, videos_count INT NOT NULL, is_active TINYINT NOT NULL, order_position INT NOT NULL, UNIQUE INDEX UNIQ_64C19C1989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE comment (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT NOT NULL, is_edited TINYINT NOT NULL, is_pinned TINYINT NOT NULL, likes_count INT NOT NULL, replies_count INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, video_id INT NOT NULL, user_id INT NOT NULL, parent_id INT DEFAULT NULL, INDEX IDX_9474526C29C1004E (video_id), INDEX IDX_9474526CA76ED395 (user_id), INDEX IDX_9474526C727ACA70 (parent_id), INDEX idx_video_parent (video_id, parent_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE model_profile (id INT AUTO_INCREMENT NOT NULL, display_name VARCHAR(100) NOT NULL, slug VARCHAR(200) NOT NULL, bio LONGTEXT DEFAULT NULL, avatar VARCHAR(255) DEFAULT NULL, cover_photo VARCHAR(255) DEFAULT NULL, gender VARCHAR(10) NOT NULL, age INT DEFAULT NULL, birth_date DATE DEFAULT NULL, country VARCHAR(100) DEFAULT NULL, ethnicity VARCHAR(100) DEFAULT NULL, career_start DATE DEFAULT NULL, hair_color VARCHAR(20) DEFAULT NULL, eye_color VARCHAR(20) DEFAULT NULL, has_tattoos TINYINT NOT NULL, has_piercings TINYINT NOT NULL, breast_size VARCHAR(20) DEFAULT NULL, height INT DEFAULT NULL, weight INT DEFAULT NULL, views_count INT NOT NULL, subscribers_count INT NOT NULL, videos_count INT NOT NULL, likes_count INT NOT NULL, is_verified TINYINT NOT NULL, is_active TINYINT NOT NULL, is_premium TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_E1C4C3FE989D9B62 (slug), UNIQUE INDEX UNIQ_E1C4C3FEA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE subscription (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, subscriber_id INT NOT NULL, channel_id INT NOT NULL, INDEX IDX_A3C664D37808B1AD (subscriber_id), INDEX IDX_A3C664D372F5A1AA (channel_id), UNIQUE INDEX unique_subscription (subscriber_id, channel_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE tag (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(50) NOT NULL, slug VARCHAR(60) NOT NULL, usage_count INT NOT NULL, UNIQUE INDEX UNIQ_389B783989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, username VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, avatar VARCHAR(255) DEFAULT NULL, bio LONGTEXT DEFAULT NULL, birth_date DATE DEFAULT NULL, location VARCHAR(100) DEFAULT NULL, country VARCHAR(50) DEFAULT NULL, gender VARCHAR(20) DEFAULT NULL, orientation VARCHAR(20) DEFAULT NULL, marital_status VARCHAR(20) DEFAULT NULL, education VARCHAR(200) DEFAULT NULL, website VARCHAR(255) DEFAULT NULL, is_verified TINYINT NOT NULL, is_premium TINYINT NOT NULL, processing_priority INT NOT NULL, subscribers_count INT NOT NULL, videos_count INT NOT NULL, total_views INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_8D93D649F85E0677 (username), UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE video (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(200) NOT NULL, description LONGTEXT DEFAULT NULL, slug VARCHAR(250) NOT NULL, temp_video_file VARCHAR(255) DEFAULT NULL, converted_files JSON DEFAULT NULL, preview VARCHAR(255) DEFAULT NULL, poster VARCHAR(255) DEFAULT NULL, duration INT NOT NULL, resolution VARCHAR(20) DEFAULT NULL, format VARCHAR(10) DEFAULT NULL, status VARCHAR(20) NOT NULL, is_featured TINYINT NOT NULL, views_count INT NOT NULL, comments_count INT NOT NULL, meta_description VARCHAR(160) DEFAULT NULL, processing_status VARCHAR(20) NOT NULL, processing_progress INT NOT NULL, processing_error LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, created_by_id INT DEFAULT NULL, category_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_7CC7DA2C989D9B62 (slug), INDEX IDX_7CC7DA2CB03A8386 (created_by_id), INDEX IDX_7CC7DA2C12469DE2 (category_id), INDEX idx_status_created (status, created_at), INDEX idx_slug (slug), INDEX idx_views (views_count), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE video_tag (video_id INT NOT NULL, tag_id INT NOT NULL, INDEX IDX_F910728729C1004E (video_id), INDEX IDX_F9107287BAD26311 (tag_id), PRIMARY KEY (video_id, tag_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE video_model (video_id INT NOT NULL, model_profile_id INT NOT NULL, INDEX IDX_58F77B7B29C1004E (video_id), INDEX IDX_58F77B7BA5C5192F (model_profile_id), PRIMARY KEY (video_id, model_profile_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE video_encoding_profile (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(50) NOT NULL, resolution VARCHAR(20) NOT NULL, bitrate INT NOT NULL, codec VARCHAR(10) NOT NULL, is_active TINYINT NOT NULL, order_position INT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE video_file (id INT AUTO_INCREMENT NOT NULL, file VARCHAR(255) NOT NULL, file_size INT NOT NULL, duration INT NOT NULL, is_primary TINYINT NOT NULL, created_at DATETIME NOT NULL, video_id INT NOT NULL, profile_id INT NOT NULL, INDEX IDX_8B086BCC29C1004E (video_id), INDEX IDX_8B086BCCCCFA12B8 (profile_id), UNIQUE INDEX unique_video_profile (video_id, profile_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C29C1004E FOREIGN KEY (video_id) REFERENCES video (id)');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526CA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C727ACA70 FOREIGN KEY (parent_id) REFERENCES comment (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE model_profile ADD CONSTRAINT FK_E1C4C3FEA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE subscription ADD CONSTRAINT FK_A3C664D37808B1AD FOREIGN KEY (subscriber_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE subscription ADD CONSTRAINT FK_A3C664D372F5A1AA FOREIGN KEY (channel_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE video ADD CONSTRAINT FK_7CC7DA2CB03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE video ADD CONSTRAINT FK_7CC7DA2C12469DE2 FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE video_tag ADD CONSTRAINT FK_F910728729C1004E FOREIGN KEY (video_id) REFERENCES video (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE video_tag ADD CONSTRAINT FK_F9107287BAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE video_model ADD CONSTRAINT FK_58F77B7B29C1004E FOREIGN KEY (video_id) REFERENCES video (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE video_model ADD CONSTRAINT FK_58F77B7BA5C5192F FOREIGN KEY (model_profile_id) REFERENCES model_profile (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE video_file ADD CONSTRAINT FK_8B086BCC29C1004E FOREIGN KEY (video_id) REFERENCES video (id)');
        $this->addSql('ALTER TABLE video_file ADD CONSTRAINT FK_8B086BCCCCFA12B8 FOREIGN KEY (profile_id) REFERENCES video_encoding_profile (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C29C1004E');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526CA76ED395');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C727ACA70');
        $this->addSql('ALTER TABLE model_profile DROP FOREIGN KEY FK_E1C4C3FEA76ED395');
        $this->addSql('ALTER TABLE subscription DROP FOREIGN KEY FK_A3C664D37808B1AD');
        $this->addSql('ALTER TABLE subscription DROP FOREIGN KEY FK_A3C664D372F5A1AA');
        $this->addSql('ALTER TABLE video DROP FOREIGN KEY FK_7CC7DA2CB03A8386');
        $this->addSql('ALTER TABLE video DROP FOREIGN KEY FK_7CC7DA2C12469DE2');
        $this->addSql('ALTER TABLE video_tag DROP FOREIGN KEY FK_F910728729C1004E');
        $this->addSql('ALTER TABLE video_tag DROP FOREIGN KEY FK_F9107287BAD26311');
        $this->addSql('ALTER TABLE video_model DROP FOREIGN KEY FK_58F77B7B29C1004E');
        $this->addSql('ALTER TABLE video_model DROP FOREIGN KEY FK_58F77B7BA5C5192F');
        $this->addSql('ALTER TABLE video_file DROP FOREIGN KEY FK_8B086BCC29C1004E');
        $this->addSql('ALTER TABLE video_file DROP FOREIGN KEY FK_8B086BCCCCFA12B8');
        $this->addSql('DROP TABLE category');
        $this->addSql('DROP TABLE comment');
        $this->addSql('DROP TABLE model_profile');
        $this->addSql('DROP TABLE subscription');
        $this->addSql('DROP TABLE tag');
        $this->addSql('DROP TABLE `user`');
        $this->addSql('DROP TABLE video');
        $this->addSql('DROP TABLE video_tag');
        $this->addSql('DROP TABLE video_model');
        $this->addSql('DROP TABLE video_encoding_profile');
        $this->addSql('DROP TABLE video_file');
    }
}
