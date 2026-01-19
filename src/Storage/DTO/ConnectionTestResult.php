<?php

declare(strict_types=1);

namespace App\Storage\DTO;

/**
 * Результат тестирования подключения к хранилищу.
 * 
 * Validates: Requirements 1.6
 */
final readonly class ConnectionTestResult
{
    public function __construct(
        public bool $success,
        public string $message,
        public ?float $latencyMs = null,
        public ?string $serverInfo = null,
    ) {}

    public static function success(string $message = 'Connection successful', ?float $latencyMs = null, ?string $serverInfo = null): self
    {
        return new self(
            success: true,
            message: $message,
            latencyMs: $latencyMs,
            serverInfo: $serverInfo,
        );
    }

    public static function failure(string $message): self
    {
        return new self(
            success: false,
            message: $message,
        );
    }
}
