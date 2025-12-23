<?php

namespace App\Message;

class ProcessVideoEncodingMessage
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