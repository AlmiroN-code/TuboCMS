<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260112082230 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Создание таблиц для системы управления рекламой: объявления, места размещения, кампании, сегменты, A/B тесты и статистика';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE ad (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(200) NOT NULL, description LONGTEXT DEFAULT NULL, format VARCHAR(30) NOT NULL, status VARCHAR(20) NOT NULL, image_url VARCHAR(500) DEFAULT NULL, video_url VARCHAR(500) DEFAULT NULL, vast_url VARCHAR(1000) DEFAULT NULL, html_content LONGTEXT DEFAULT NULL, script_code LONGTEXT DEFAULT NULL, click_url VARCHAR(500) DEFAULT NULL, alt_text VARCHAR(200) DEFAULT NULL, is_active TINYINT NOT NULL, open_in_new_tab TINYINT NOT NULL, start_date DATETIME DEFAULT NULL, end_date DATETIME DEFAULT NULL, priority INT NOT NULL, weight INT NOT NULL, budget NUMERIC(10, 2) DEFAULT NULL, cpm NUMERIC(10, 4) DEFAULT NULL, cpc NUMERIC(10, 4) DEFAULT NULL, impression_limit INT DEFAULT NULL, click_limit INT DEFAULT NULL, daily_impression_limit INT DEFAULT NULL, daily_click_limit INT DEFAULT NULL, impressions_count INT NOT NULL, clicks_count INT NOT NULL, unique_impressions_count INT NOT NULL, unique_clicks_count INT NOT NULL, spent_amount NUMERIC(10, 2) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, geo_targeting JSON DEFAULT NULL, time_targeting JSON DEFAULT NULL, device_targeting JSON DEFAULT NULL, category_targeting JSON DEFAULT NULL, ab_test_variant VARCHAR(10) DEFAULT NULL, placement_id INT NOT NULL, campaign_id INT DEFAULT NULL, created_by_id INT DEFAULT NULL, ab_test_id INT DEFAULT NULL, INDEX IDX_77E0ED582F966E9D (placement_id), INDEX IDX_77E0ED58F639F774 (campaign_id), INDEX IDX_77E0ED58B03A8386 (created_by_id), INDEX IDX_77E0ED58A00D9457 (ab_test_id), INDEX idx_ad_status_dates (status, start_date, end_date), INDEX idx_ad_active (is_active), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE ad_segment_relation (ad_id INT NOT NULL, ad_segment_id INT NOT NULL, INDEX IDX_C6F9F5084F34D596 (ad_id), INDEX IDX_C6F9F50824A03917 (ad_segment_id), PRIMARY KEY (ad_id, ad_segment_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE ad_ab_test (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(200) NOT NULL, description LONGTEXT DEFAULT NULL, status VARCHAR(20) NOT NULL, start_date DATETIME DEFAULT NULL, end_date DATETIME DEFAULT NULL, traffic_split_a INT NOT NULL, traffic_split_b INT NOT NULL, winner_metric VARCHAR(50) NOT NULL, winner VARCHAR(10) DEFAULT NULL, statistical_significance NUMERIC(10, 4) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, created_by_id INT DEFAULT NULL, INDEX IDX_7BA326F0B03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE ad_campaign (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(200) NOT NULL, description LONGTEXT DEFAULT NULL, status VARCHAR(20) NOT NULL, start_date DATETIME DEFAULT NULL, end_date DATETIME DEFAULT NULL, total_budget NUMERIC(12, 2) DEFAULT NULL, daily_budget NUMERIC(10, 2) DEFAULT NULL, spent_amount NUMERIC(12, 2) NOT NULL, total_impressions INT NOT NULL, total_clicks INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, created_by_id INT DEFAULT NULL, INDEX IDX_F50D1F0DB03A8386 (created_by_id), INDEX idx_ad_campaign_status (status), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE ad_placement (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, slug VARCHAR(100) NOT NULL, description LONGTEXT DEFAULT NULL, type VARCHAR(30) NOT NULL, position VARCHAR(30) NOT NULL, width INT DEFAULT NULL, height INT DEFAULT NULL, is_active TINYINT NOT NULL, order_position INT NOT NULL, allowed_pages JSON DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_CEE2BFD7989D9B62 (slug), INDEX idx_ad_placement_slug (slug), INDEX idx_ad_placement_active (is_active), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE ad_segment (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, slug VARCHAR(100) NOT NULL, description LONGTEXT DEFAULT NULL, type VARCHAR(30) NOT NULL, rules JSON DEFAULT NULL, is_active TINYINT NOT NULL, users_count INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_D5CB1CE1989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE ad_statistic (id INT AUTO_INCREMENT NOT NULL, date DATE NOT NULL, impressions INT NOT NULL, clicks INT NOT NULL, unique_impressions INT NOT NULL, unique_clicks INT NOT NULL, spent NUMERIC(10, 2) NOT NULL, revenue NUMERIC(10, 2) NOT NULL, conversions INT NOT NULL, hourly_data JSON DEFAULT NULL, geo_data JSON DEFAULT NULL, device_data JSON DEFAULT NULL, ad_id INT NOT NULL, INDEX IDX_E2A28C454F34D596 (ad_id), INDEX idx_ad_stat_date (date), UNIQUE INDEX unique_ad_date (ad_id, date), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE ad ADD CONSTRAINT FK_77E0ED582F966E9D FOREIGN KEY (placement_id) REFERENCES ad_placement (id)');
        $this->addSql('ALTER TABLE ad ADD CONSTRAINT FK_77E0ED58F639F774 FOREIGN KEY (campaign_id) REFERENCES ad_campaign (id)');
        $this->addSql('ALTER TABLE ad ADD CONSTRAINT FK_77E0ED58B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE ad ADD CONSTRAINT FK_77E0ED58A00D9457 FOREIGN KEY (ab_test_id) REFERENCES ad_ab_test (id)');
        $this->addSql('ALTER TABLE ad_segment_relation ADD CONSTRAINT FK_C6F9F5084F34D596 FOREIGN KEY (ad_id) REFERENCES ad (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ad_segment_relation ADD CONSTRAINT FK_C6F9F50824A03917 FOREIGN KEY (ad_segment_id) REFERENCES ad_segment (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ad_ab_test ADD CONSTRAINT FK_7BA326F0B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE ad_campaign ADD CONSTRAINT FK_F50D1F0DB03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE ad_statistic ADD CONSTRAINT FK_E2A28C454F34D596 FOREIGN KEY (ad_id) REFERENCES ad (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ad DROP FOREIGN KEY FK_77E0ED582F966E9D');
        $this->addSql('ALTER TABLE ad DROP FOREIGN KEY FK_77E0ED58F639F774');
        $this->addSql('ALTER TABLE ad DROP FOREIGN KEY FK_77E0ED58B03A8386');
        $this->addSql('ALTER TABLE ad DROP FOREIGN KEY FK_77E0ED58A00D9457');
        $this->addSql('ALTER TABLE ad_segment_relation DROP FOREIGN KEY FK_C6F9F5084F34D596');
        $this->addSql('ALTER TABLE ad_segment_relation DROP FOREIGN KEY FK_C6F9F50824A03917');
        $this->addSql('ALTER TABLE ad_ab_test DROP FOREIGN KEY FK_7BA326F0B03A8386');
        $this->addSql('ALTER TABLE ad_campaign DROP FOREIGN KEY FK_F50D1F0DB03A8386');
        $this->addSql('ALTER TABLE ad_statistic DROP FOREIGN KEY FK_E2A28C454F34D596');
        $this->addSql('DROP TABLE ad');
        $this->addSql('DROP TABLE ad_segment_relation');
        $this->addSql('DROP TABLE ad_ab_test');
        $this->addSql('DROP TABLE ad_campaign');
        $this->addSql('DROP TABLE ad_placement');
        $this->addSql('DROP TABLE ad_segment');
        $this->addSql('DROP TABLE ad_statistic');
    }
}
