<?php

namespace App\Twig;

use App\Entity\Video;
use App\Service\ShareService;
use App\Service\EmbedService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ShareExtension extends AbstractExtension
{
    public function __construct(
        private ShareService $shareService,
        private EmbedService $embedService
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('share_urls', [$this, 'getShareUrls']),
            new TwigFunction('og_meta', [$this, 'getOpenGraphMeta']),
            new TwigFunction('video_url', [$this, 'getVideoUrl']),
            new TwigFunction('embed_code', [$this, 'getEmbedCode']),
            new TwigFunction('oembed_url', [$this, 'getOEmbedUrl']),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getShareUrls(Video $video): array
    {
        return $this->shareService->getShareUrls($video);
    }

    /**
     * @return array<string, string>
     */
    public function getOpenGraphMeta(Video $video): array
    {
        return $this->shareService->getOpenGraphMeta($video);
    }

    public function getVideoUrl(Video $video): string
    {
        return $this->shareService->getVideoUrl($video);
    }

    public function getEmbedCode(Video $video, int $width = 640, int $height = 360): string
    {
        return $this->embedService->generateEmbedCode($video, $width, $height);
    }

    public function getOEmbedUrl(Video $video): string
    {
        return $this->shareService->getVideoUrl($video) . '/oembed';
    }
}
