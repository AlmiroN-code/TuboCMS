<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251211161027 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add performance indexes for better query optimization';
    }

    public function up(Schema $schema): void
    {
        // Добавляем только новые индексы, которых еще нет в системе
        
        // User table indexes
        $this->addSql('CREATE INDEX idx_user_stats ON `user` (subscribers_count, videos_count, total_views)');
        $this->addSql('CREATE INDEX idx_user_status ON `user` (is_verified, is_premium)');
        
        // Video table indexes  
        $this->addSql('CREATE INDEX idx_video_category_status ON video (category_id, status, created_at)');
        $this->addSql('CREATE INDEX idx_video_user_status ON video (created_by_id, status)');
        $this->addSql('CREATE INDEX idx_video_processing ON video (processing_status, processing_progress)');
        
        // Comment table indexes
        $this->addSql('CREATE INDEX idx_comment_user ON comment (user_id, created_at)');
        
        // Category table indexes
        $this->addSql('CREATE INDEX idx_category_active_order ON category (is_active, order_position)');
        
        // Tag table indexes
        $this->addSql('CREATE INDEX idx_tag_usage ON tag (usage_count DESC)');
        
        // Video file table indexes
        $this->addSql('CREATE INDEX idx_video_file_video_primary ON video_file (video_id, is_primary)');
        
        // Subscription table indexes
        $this->addSql('CREATE INDEX idx_subscription_subscriber ON subscription (subscriber_id, created_at)');
        $this->addSql('CREATE INDEX idx_subscription_channel ON subscription (channel_id, created_at)');
    }

    public function down(Schema $schema): void
    {
        // Удаляем созданные индексы
        $this->addSql('DROP INDEX idx_user_stats ON `user`');
        $this->addSql('DROP INDEX idx_user_status ON `user`');
        
        $this->addSql('DROP INDEX idx_video_category_status ON video');
        $this->addSql('DROP INDEX idx_video_user_status ON video');
        $this->addSql('DROP INDEX idx_video_processing ON video');
        
        $this->addSql('DROP INDEX idx_comment_user ON comment');
        
        $this->addSql('DROP INDEX idx_category_active_order ON category');
        
        $this->addSql('DROP INDEX idx_tag_usage ON tag');
        
        $this->addSql('DROP INDEX idx_video_file_video_primary ON video_file');
        
        $this->addSql('DROP INDEX idx_subscription_subscriber ON subscription');
        $this->addSql('DROP INDEX idx_subscription_channel ON subscription');
    }
}
