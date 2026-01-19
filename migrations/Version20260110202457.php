<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260110202457 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Создаём таблицу связи video_category
        $this->addSql('CREATE TABLE video_category (video_id INT NOT NULL, category_id INT NOT NULL, INDEX IDX_AECE2B7D29C1004E (video_id), INDEX IDX_AECE2B7D12469DE2 (category_id), PRIMARY KEY (video_id, category_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE video_category ADD CONSTRAINT FK_AECE2B7D29C1004E FOREIGN KEY (video_id) REFERENCES video (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE video_category ADD CONSTRAINT FK_AECE2B7D12469DE2 FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE CASCADE');
        
        // Переносим существующие связи из video.category_id в video_category
        $this->addSql('INSERT INTO video_category (video_id, category_id) SELECT id, category_id FROM video WHERE category_id IS NOT NULL');
        
        // Удаляем старую колонку category_id
        $this->addSql('ALTER TABLE video DROP FOREIGN KEY `FK_7CC7DA2C12469DE2`');
        $this->addSql('DROP INDEX IDX_7CC7DA2C12469DE2 ON video');
        $this->addSql('ALTER TABLE video DROP category_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE video_category DROP FOREIGN KEY FK_AECE2B7D29C1004E');
        $this->addSql('ALTER TABLE video_category DROP FOREIGN KEY FK_AECE2B7D12469DE2');
        $this->addSql('DROP TABLE video_category');
        $this->addSql('ALTER TABLE video ADD category_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE video ADD CONSTRAINT `FK_7CC7DA2C12469DE2` FOREIGN KEY (category_id) REFERENCES category (id) ON UPDATE NO ACTION ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_7CC7DA2C12469DE2 ON video (category_id)');
    }
}
