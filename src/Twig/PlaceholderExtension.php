<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class PlaceholderExtension extends AbstractExtension
{
    public function __construct(
        private string $projectDir
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('placeholder_image', [$this, 'getPlaceholderImage']),
            new TwigFunction('video_poster', [$this, 'getVideoPoster']),
            new TwigFunction('channel_avatar', [$this, 'getChannelAvatar']),
            new TwigFunction('user_avatar', [$this, 'getUserAvatar']),
        ];
    }

    public function getPlaceholderImage(int $width = 1280, int $height = 720, string $text = 'No Image'): string
    {
        // Генерируем SVG placeholder
        $bgColor = 'e5e7eb'; // gray-200
        $textColor = '6b7280'; // gray-500
        
        $svg = <<<SVG
<svg width="{$width}" height="{$height}" xmlns="http://www.w3.org/2000/svg">
    <rect width="100%" height="100%" fill="#{$bgColor}"/>
    <text x="50%" y="50%" font-family="Arial, sans-serif" font-size="48" fill="#{$textColor}" text-anchor="middle" dominant-baseline="middle">{$text}</text>
</svg>
SVG;

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    public function getVideoPoster(?string $posterPath): string
    {
        if ($posterPath) {
            // Постеры хранятся как 'posters/filename.jpg'
            $fullPath = $this->projectDir . '/public/media/' . $posterPath;
            if (file_exists($fullPath)) {
                return '/media/' . $posterPath;
            }
        }

        // return $this->getPlaceholderImage(1280, 720, 'No Poster');    
        // Или используй статическое изображение
           return '/media/placeholders/video-placeholder.jpg';
}

    public function getChannelAvatar(?string $avatarPath): string
    {
        if ($avatarPath) {
            $fullPath = $this->projectDir . '/public/media/channels/avatars/' . $avatarPath;
            if (file_exists($fullPath)) {
                return '/media/channels/avatars/' . $avatarPath;
            }
        }

        //return $this->getPlaceholderImage(200, 200, 'Channel');
        return '/media/placeholders/channel-placeholder.jpg';
    }

    public function getUserAvatar(?string $avatarPath): string
    {
        if ($avatarPath) {
            $fullPath = $this->projectDir . '/public/media/avatars/' . $avatarPath;
            if (file_exists($fullPath)) {
                return '/media/avatars/' . $avatarPath;
            }
        }

      //return $this->getPlaceholderImage(200, 200, 'User');
        return '/media/placeholders/user-placeholder.jpg';
    }
}
