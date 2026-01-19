<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Исправление FULLTEXT индексов для поиска
 * Удаляет старый индекс ft_title и создает новый ft_title_description
 */
final class Version20260115191000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix FULLTEXT indexes - replace ft_title with ft_title_description';
    }

    public function up(Schema $schema): void
    {
        // Удаляем старый индекс ft_title если существует
        try {
            $this->addSql('ALTER TABLE video DROP INDEX ft_title');
        } catch (\Exception $e) {
            // Индекс может не существовать, игнорируем ошибку
        }
        
        // Создаем новый индекс на обе колонки
        $this->addSql('ALTER TABLE video ADD FULLTEXT INDEX ft_title_description (title, description)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE video DROP INDEX IF EXISTS ft_title_description');
    }
}
