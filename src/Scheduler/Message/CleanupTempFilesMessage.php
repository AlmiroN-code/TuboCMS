<?php

namespace App\Scheduler\Message;

/**
 * Сообщение для очистки временных файлов.
 */
class CleanupTempFilesMessage
{
    public function __construct(
        public readonly int $maxAgeHours = 24,
    ) {
    }
}
