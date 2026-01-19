<?php

namespace App\Message;

/**
 * Message for async file upload to remote storage.
 * 
 * @see Requirements 2.1 - Upload video files to remote storage
 */
class UploadToStorageMessage
{
    public function __construct(
        public readonly int $videoFileId,
        public readonly string $localPath,
        public readonly int $attempt = 1
    ) {
    }

    public function getVideoFileId(): int
    {
        return $this->videoFileId;
    }

    public function getLocalPath(): string
    {
        return $this->localPath;
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
            $this->videoFileId,
            $this->localPath,
            $this->attempt + 1
        );
    }
}
