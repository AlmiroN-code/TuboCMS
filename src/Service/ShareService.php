<?php

namespace App\Service;

use App\Entity\Video;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ShareService
{
    private const SOCIAL_NETWORKS = [
        'twitter' => 'https://twitter.com/intent/tweet?url=%s&text=%s',
        'facebook' => 'https://www.facebook.com/sharer/sharer.php?u=%s',
        'telegram' => 'https://t.me/share/url?url=%s&text=%s',
        'vk' => 'https://vk.com/share.php?url=%s&title=%s',
        'whatsapp' => 'https://wa.me/?text=%s%%20%s',
        'reddit' => 'https://www.reddit.com/submit?url=%s&title=%s',
        'pinterest' => 'https://pinterest.com/pin/create/button/?url=%s&description=%s&media=%s',
    ];

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function getVideoUrl(Video $video): string
    {
        return $this->urlGenerator->generate(
            'video_detail',
            ['slug' => $video->getSlug()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }

    /**
     * @return array<string, string>
     */
    public function getShareUrls(Video $video): array
    {
        $videoUrl = urlencode($this->getVideoUrl($video));
        $title = urlencode($video->getTitle());
        $posterUrl = $video->getPoster() 
            ? urlencode($this->urlGenerator->generate('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL) . 'media/posters/' . $video->getPoster())
            : '';

        return [
            'twitter' => sprintf(self::SOCIAL_NETWORKS['twitter'], $videoUrl, $title),
            'facebook' => sprintf(self::SOCIAL_NETWORKS['facebook'], $videoUrl),
            'telegram' => sprintf(self::SOCIAL_NETWORKS['telegram'], $videoUrl, $title),
            'vk' => sprintf(self::SOCIAL_NETWORKS['vk'], $videoUrl, $title),
            'whatsapp' => sprintf(self::SOCIAL_NETWORKS['whatsapp'], $title, $videoUrl),
            'reddit' => sprintf(self::SOCIAL_NETWORKS['reddit'], $videoUrl, $title),
            'pinterest' => sprintf(self::SOCIAL_NETWORKS['pinterest'], $videoUrl, $title, $posterUrl),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getOpenGraphMeta(Video $video): array
    {
        $url = $this->getVideoUrl($video);
        $posterUrl = $video->getPoster() 
            ? $this->urlGenerator->generate('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL) . 'media/posters/' . $video->getPoster()
            : null;

        // Get first video file URL for og:video
        $videoUrl = null;
        if ($video->getEncodedFiles() && $video->getEncodedFiles()->count() > 0) {
            $firstFile = $video->getEncodedFiles()->first();
            if ($firstFile) {
                $videoUrl = $this->urlGenerator->generate('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL) . 'storage/file/' . $firstFile->getId();
            }
        }

        return [
            'og:type' => 'video.other',
            'og:url' => $url,
            'og:title' => $video->getTitle(),
            'og:description' => $video->getDescription() ? mb_substr($video->getDescription(), 0, 200) : '',
            'og:image' => $posterUrl,
            'og:image:width' => '640',
            'og:image:height' => '360',
            'og:video' => $videoUrl,
            'og:video:type' => 'video/mp4',
            'og:video:width' => '640',
            'og:video:height' => '360',
            'og:video:duration' => (string) $video->getDuration(),
            'twitter:card' => 'summary_large_image',
            'twitter:title' => $video->getTitle(),
            'twitter:description' => $video->getDescription() ? mb_substr($video->getDescription(), 0, 200) : '',
            'twitter:image' => $posterUrl,
        ];
    }
}
