<?php

namespace App\Twig;

use App\Service\ContentProtectionService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class SecureMediaExtension extends AbstractExtension
{
    public function __construct(
        private ContentProtectionService $protectionService,
        private UrlGeneratorInterface $urlGenerator
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('secure_video_url', [$this, 'getSecureVideoUrl']),
            new TwigFunction('secure_poster_url', [$this, 'getSecurePosterUrl']),
            new TwigFunction('secure_preview_url', [$this, 'getSecurePreviewUrl']),
        ];
    }

    /**
     * Генерация защищенного URL для видео
     */
    public function getSecureVideoUrl(string $filename): string
    {
        // Убираем префикс videos/ если он есть
        $filename = $this->cleanFilename($filename, 'videos/');
        
        if (!$this->protectionService->isSignedUrlsEnabled()) {
            // Если защита отключена, возвращаем обычный путь
            return '/media/videos/' . $filename;
        }

        $path = $this->urlGenerator->generate('secure_media_video', [
            'filename' => $filename
        ]);

        return $this->protectionService->generateSignedUrl($path);
    }

    /**
     * Генерация защищенного URL для постера
     */
    public function getSecurePosterUrl(string $filename): string
    {
        // Убираем префикс posters/ если он есть
        $filename = $this->cleanFilename($filename, 'posters/');
        
        if (!$this->protectionService->isSignedUrlsEnabled()) {
            return '/media/posters/' . $filename;
        }

        $path = $this->urlGenerator->generate('secure_media_poster', [
            'filename' => $filename
        ]);

        return $this->protectionService->generateSignedUrl($path);
    }

    /**
     * Генерация защищенного URL для превью
     */
    public function getSecurePreviewUrl(string $filename): string
    {
        // Убираем префикс previews/ если он есть
        $filename = $this->cleanFilename($filename, 'previews/');
        
        if (!$this->protectionService->isSignedUrlsEnabled()) {
            return '/media/previews/' . $filename;
        }

        $path = $this->urlGenerator->generate('secure_media_preview', [
            'filename' => $filename
        ]);

        return $this->protectionService->generateSignedUrl($path);
    }
    
    /**
     * Очистка имени файла от префикса директории
     */
    private function cleanFilename(string $filename, string $prefix): string
    {
        if (str_starts_with($filename, $prefix)) {
            return substr($filename, strlen($prefix));
        }
        return $filename;
    }
}
