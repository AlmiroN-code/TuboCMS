<?php

namespace App\Service;

use App\Entity\Video;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class EmbedService
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator
    ) {
    }

    /**
     * Генерирует HTML код для встраивания видео
     */
    public function generateEmbedCode(Video $video, int $width = 640, int $height = 360): string
    {
        $embedUrl = $this->urlGenerator->generate(
            'video_embed',
            ['slug' => $video->getSlug()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        return sprintf(
            '<iframe src="%s" width="%d" height="%d" frameborder="0" allowfullscreen></iframe>',
            htmlspecialchars($embedUrl, ENT_QUOTES, 'UTF-8'),
            $width,
            $height
        );
    }

    /**
     * Генерирует oEmbed JSON для видео
     */
    public function generateOEmbed(Video $video, int $maxWidth = 640, int $maxHeight = 360): array
    {
        $videoUrl = $this->urlGenerator->generate(
            'video_detail',
            ['slug' => $video->getSlug()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $embedCode = $this->generateEmbedCode($video, $maxWidth, $maxHeight);

        return [
            'version' => '1.0',
            'type' => 'video',
            'provider_name' => 'RexTube',
            'provider_url' => $this->urlGenerator->generate('home', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'title' => $video->getTitle(),
            'author_name' => $video->getUser()->getUsername(),
            'author_url' => $videoUrl,
            'width' => $maxWidth,
            'height' => $maxHeight,
            'html' => $embedCode,
            'thumbnail_url' => $video->getPosterUrl() ? $this->urlGenerator->generate('home', [], UrlGeneratorInterface::ABSOLUTE_URL) . $video->getPosterUrl() : null,
            'thumbnail_width' => 1280,
            'thumbnail_height' => 720,
        ];
    }
}
