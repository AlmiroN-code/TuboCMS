<?php

declare(strict_types=1);

namespace App\Storage\DTO;

/**
 * Результат загрузки файла в хранилище.
 * 
 * Validates: Requirements 2.7
 */
final readonly class UploadResult
{
    public function __construct(
        public bool $success,
        public ?string $remotePath = null,
        public ?string $url = null,
        public ?int $fileSize = null,
        public ?string $errorMessage = null,
    ) {}

    public static function success(string $remotePath, ?string $url = null, ?int $fileSize = null): self
    {
        return new self(
            success: true,
            remotePath: $remotePath,
            url: $url,
            fileSize: $fileSize,
        );
    }

    public static function failure(string $errorMessage): self
    {
        return new self(
            success: false,
            errorMessage: $errorMessage,
        );
    }
}
