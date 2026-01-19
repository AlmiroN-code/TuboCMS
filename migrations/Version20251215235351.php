<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251215235351 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        
        // Проверяем существование таблицы video_like
        $tableExists = $this->connection->fetchOne(
            "SELECT COUNT(*) FROM information_schema.tables 
             WHERE table_schema = DATABASE() 
             AND table_name = 'video_like'"
        );
        
        if (!$tableExists) {
            $this->addSql('CREATE TABLE video_like (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(10) NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, video_id INT NOT NULL, INDEX IDX_ABF41D6FA76ED395 (user_id), INDEX IDX_ABF41D6F29C1004E (video_id), UNIQUE INDEX unique_user_video_like (user_id, video_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
            $this->addSql('ALTER TABLE video_like ADD CONSTRAINT FK_ABF41D6FA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
            $this->addSql('ALTER TABLE video_like ADD CONSTRAINT FK_ABF41D6F29C1004E FOREIGN KEY (video_id) REFERENCES video (id) ON DELETE CASCADE');
        }
        
        // Удаляем индексы только если они существуют (используем процедуру для проверки)
        $indexes = [
            ['table' => 'category', 'index' => 'idx_category_active_order'],
            ['table' => 'comment', 'index' => 'idx_comment_user'],
            ['table' => 'subscription', 'index' => 'idx_subscription_channel'],
            ['table' => 'subscription', 'index' => 'idx_subscription_subscriber'],
            ['table' => 'tag', 'index' => 'idx_tag_usage'],
            ['table' => 'user', 'index' => 'idx_user_created'],
            ['table' => 'user', 'index' => 'idx_user_stats'],
            ['table' => 'user', 'index' => 'idx_user_status'],
            ['table' => 'video', 'index' => 'idx_video_category_status'],
            ['table' => 'video', 'index' => 'idx_video_views'],
            ['table' => 'video', 'index' => 'idx_video_processing'],
            ['table' => 'video', 'index' => 'idx_video_status_created'],
            ['table' => 'video', 'index' => 'title'],
            ['table' => 'video', 'index' => 'idx_video_user_status'],
            ['table' => 'video_file', 'index' => 'idx_video_file_video_primary'],
        ];
        
        foreach ($indexes as $item) {
            $tableName = $item['table'];
            $indexName = $item['index'];
            
            // Проверяем существование индекса
            $result = $this->connection->fetchOne(
                "SELECT COUNT(*) FROM information_schema.statistics 
                 WHERE table_schema = DATABASE() 
                 AND table_name = ? 
                 AND index_name = ?",
                [$tableName, $indexName]
            );
            
            if ($result > 0) {
                $this->addSql("DROP INDEX {$indexName} ON {$tableName}");
            }
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE video_like DROP FOREIGN KEY FK_ABF41D6FA76ED395');
        $this->addSql('ALTER TABLE video_like DROP FOREIGN KEY FK_ABF41D6F29C1004E');
        $this->addSql('DROP TABLE video_like');
        $this->addSql('CREATE INDEX idx_category_active_order ON category (is_active, order_position)');
        $this->addSql('CREATE INDEX idx_comment_user ON comment (user_id, created_at)');
        $this->addSql('CREATE INDEX idx_subscription_channel ON subscription (channel_id, created_at)');
        $this->addSql('CREATE INDEX idx_subscription_subscriber ON subscription (subscriber_id, created_at)');
        $this->addSql('CREATE INDEX idx_tag_usage ON tag (usage_count)');
        $this->addSql('CREATE INDEX idx_user_created ON `user` (created_at)');
        $this->addSql('CREATE INDEX idx_user_stats ON `user` (subscribers_count, videos_count, total_views)');
        $this->addSql('CREATE INDEX idx_user_status ON `user` (is_verified, is_premium)');
        $this->addSql('CREATE INDEX idx_video_category_status ON video (category_id, status, created_at)');
        $this->addSql('CREATE INDEX idx_video_views ON video (views_count)');
        $this->addSql('CREATE INDEX idx_video_processing ON video (processing_status, processing_progress)');
        $this->addSql('CREATE INDEX idx_video_status_created ON video (status, created_at)');
        $this->addSql('CREATE FULLTEXT INDEX title ON video (title, description)');
        $this->addSql('CREATE INDEX idx_video_user_status ON video (created_by_id, status)');
        $this->addSql('CREATE INDEX idx_video_file_video_primary ON video_file (video_id, is_primary)');
    }
}
