<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Message для асинхронной миграции файла между хранилищами.
 * 
 * Requirement 4.2: WHEN migration is started THEN the System SHALL 
 * queue migration jobs for each video file
 * 
 * Requirement 4.3: WHEN a file is migrated THEN the System SHALL copy the file 
 * to destination, verify integrity, and update the VideoFile record
 */
class MigrateFileMessage
{
    public function __construct(
        public readonly int $videoFileId,
        public readonly int $destinationStorageId,
        public readonly int $attempt = 1,
        public readonly ?string $migrationId = null
    ) {
    }

    public function getVideoFileId(): int
    {
        return $this->videoFileId;
    }

    public function getDestinationStorageId(): int
    {
        return $this->destinationStorageId;
    }

    public function getAttempt(): int
    {
        return $this->attempt;
    }

    public function getMigrationId(): ?string
    {
        return $this->migrationId;
    }

    /**
     * Create a new message for retry with incremented attempt counter.
     */
    public function retry(): self
    {
        return new self(
            $this->videoFileId,
            $this->destinationStorageId,
            $this->attempt + 1,
            $this->migrationId
        );
    }
}
