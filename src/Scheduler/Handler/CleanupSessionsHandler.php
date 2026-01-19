<?php

namespace App\Scheduler\Handler;

use App\Scheduler\Message\CleanupSessionsMessage;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class CleanupSessionsHandler
{
    public function __construct(
        private Connection $connection,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(CleanupSessionsMessage $message): void
    {
        $this->logger->info('Начинаем очистку неактивных сессий');

        try {
            // Удаляем сессии старше 7 дней
            $threshold = new \DateTime('-7 days');
            
            // Проверяем существование таблицы sessions
            $schemaManager = $this->connection->createSchemaManager();
            if (!$schemaManager->tablesExist(['sessions'])) {
                $this->logger->info('Таблица sessions не существует, пропускаем очистку');
                return;
            }
            
            $deleted = $this->connection->executeStatement(
                'DELETE FROM sessions WHERE sess_time < :threshold',
                ['threshold' => $threshold->getTimestamp()]
            );
            
            $this->logger->info('Очистка сессий завершена', [
                'deleted_count' => $deleted,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Ошибка очистки сессий: ' . $e->getMessage());
        }
    }
}
