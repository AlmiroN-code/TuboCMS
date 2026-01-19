<?php

namespace App\Message;

/**
 * Message for async animated preview generation.
 * 
 * @see Requirements 13.3 - Generate animated preview (WebP/GIF)
 */
class GeneratePreviewMessage
{
    public function __construct(
        private int $videoId
    ) {
    }

    public function getVideoId(): int
    {
        return $this->videoId;
    }
}
