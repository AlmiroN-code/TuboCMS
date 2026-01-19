<?php

namespace App\Scheduler\Message;

/**
 * Сообщение для очистки старых уведомлений.
 */
class CleanupOldNotificationsMessage
{
    public function __construct(
        public readonly int $maxAgeDays = 30,
    ) {
    }
}
