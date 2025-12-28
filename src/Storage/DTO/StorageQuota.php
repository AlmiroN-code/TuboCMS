<?php

declare(strict_types=1);

namespace App\Storage\DTO;

/**
 * Информация о квоте хранилища.
 * 
 * Validates: Requirements 6.2
 */
final readonly class StorageQuota
{
    public function __construct(
        public int $usedBytes,
        public ?int $totalBytes = null,
    ) {}

    /**
     * Возвращает доступное пространство в байтах.
     * Null если общий размер неизвестен.
     */
    public function getAvailableBytes(): ?int
    {
        if ($this->totalBytes === null) {
            return null;
        }

        return max(0, $this->totalBytes - $this->usedBytes);
    }

    /**
     * Возвращает процент использования (0-100).
     * Null если общий размер неизвестен.
     */
    public function getUsagePercent(): ?float
    {
        if ($this->totalBytes === null || $this->totalBytes === 0) {
            return null;
        }

        return round(($this->usedBytes / $this->totalBytes) * 100, 2);
    }

    /**
     * Проверяет, превышен ли порог предупреждения (80%).
     */
    public function isWarningThresholdExceeded(): bool
    {
        $percent = $this->getUsagePercent();
        
        return $percent !== null && $percent >= 80.0;
    }
}
