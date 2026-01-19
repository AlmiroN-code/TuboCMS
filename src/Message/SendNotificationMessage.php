<?php

namespace App\Message;

/**
 * Message for async notification sending.
 * 
 * @see Requirements 4.3 - Send notifications to subscribers
 */
class SendNotificationMessage
{
    public function __construct(
        private int $userId,
        private string $type,
        private array $data
    ) {
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getData(): array
    {
        return $this->data;
    }
}
