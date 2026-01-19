<?php

namespace App\Scheduler\Message;

/**
 * Сообщение для проверки застрявших видео в обработке.
 */
class CheckStuckVideosMessage
{
    public function __construct(
        public readonly int $stuckThresholdMinutes = 60,
    ) {
    }
}
