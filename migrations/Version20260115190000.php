<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Добавление FULLTEXT индексов для поиска
 */
final class Version20260115190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add FULLTEXT indexes for search functionality';
    }

    public function up(Schema $schema): void
    {
        // Удаляем старый индекс если существует
        $this->addSql('ALTER TABLE video DROP INDEX IF EXISTS ft_title');
        // Создаем новый индекс на обе колонки
        $this->addSql('ALTER TABLE video ADD FULLTEXT INDEX ft_title_description (title, description)');
        $this->addSql('ALTER TABLE tag ADD FULLTEXT INDEX ft_name (name)');
        $this->addSql('ALTER TABLE category ADD FULLTEXT INDEX ft_name_description (name, description)');
        $this->addSql('ALTER TABLE model_profile ADD FULLTEXT INDEX ft_display_name_bio (display_name, bio)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE video DROP INDEX IF EXISTS ft_title_description');
        $this->addSql('ALTER TABLE tag DROP INDEX IF EXISTS ft_name');
        $this->addSql('ALTER TABLE category DROP INDEX IF EXISTS ft_name_description');
        $this->addSql('ALTER TABLE model_profile DROP INDEX IF EXISTS ft_display_name_bio');
    }
}
