<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Message for async file deletion from remote storage.
 * 
 * Requirement 5.1: WHEN a video is deleted THEN the System SHALL 
 * queue deletion jobs for all associated remote files
 * 
 * Requirement 5.2: WHEN deleting from FTP storage THEN the System SHALL 
 * remove the file using FTP DELETE command
 * 
 * Requirement 5.3: WHEN deleting from SFTP storage THEN the System SHALL 
 * remove the file using SFTP unlink command
 * 
 * Requirement 5.4: WHEN deleting from Remote Server THEN the System SHALL 
 * send HTTP DELETE request to the configured endpoint
 */
class DeleteFromStorageMessage
{
    public function __construct(
        public readonly int $storageId,
        public readonly string $remotePath,
        public readonly int $attempt = 1
    ) {
    }

    public function getStorageId(): int
    {
        return $this->storageId;
    }

    public function getRemotePath(): string
    {
        return $this->remotePath;
    }

    public function getAttempt(): int
    {
        return $this->attempt;
    }

    /**
     * Create a new message for retry with incremented attempt counter.
     */
    public function retry(): self
    {
        return new self(
            $this->storageId,
            $this->remotePath,
            $this->attempt + 1
        );
    }
}
