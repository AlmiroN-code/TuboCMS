<?php

namespace App\Twig;

use App\Entity\Video;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig расширение для генерации Schema.org VideoObject JSON-LD разметки
 */
class VideoSchemaExtension extends AbstractExtension
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('video_schema', [$this, 'generateVideoSchema'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * Генерирует JSON-LD разметку VideoObject для видео
     */
    public function generateVideoSchema(Video $video, ?string $siteUrl = null): string
    {
        $siteUrl = $siteUrl ?? $this->urlGenerator->generate('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $siteUrl = rtrim($siteUrl, '/');
        
        // URL видео
        $videoUrl = $this->urlGenerator->generate('video_detail', [
            'slug' => $video->getSlug()
        ], UrlGeneratorInterface::ABSOLUTE_URL);
        
        // URL постера
        $thumbnailUrl = $video->getPoster() 
            ? $siteUrl . '/media/' . $video->getPoster()
            : null;
        
        // URL контента (первый закодированный файл или временный)
        $contentUrl = null;
        $encodedFiles = $video->getEncodedFiles();
        if ($encodedFiles->count() > 0) {
            $primaryFile = null;
            foreach ($encodedFiles as $file) {
                if ($file->isPrimary()) {
                    $primaryFile = $file;
                    break;
                }
            }
            if (!$primaryFile) {
                $primaryFile = $encodedFiles->first();
            }
            if ($primaryFile) {
                $contentUrl = $this->urlGenerator->generate('storage_file', [
                    'id' => $primaryFile->getId()
                ], UrlGeneratorInterface::ABSOLUTE_URL);
            }
        } elseif ($video->getTempVideoFile()) {
            $contentUrl = $siteUrl . '/media/' . $video->getTempVideoFile();
        }
        
        // Embed URL
        $embedUrl = $this->urlGenerator->generate('video_detail', [
            'slug' => $video->getSlug()
        ], UrlGeneratorInterface::ABSOLUTE_URL);
        
        // Базовая структура VideoObject
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'VideoObject',
            'name' => $video->getTitle(),
            'description' => $video->getDescription() ?? $video->getTitle(),
            'uploadDate' => $video->getCreatedAt()->format('c'),
            'duration' => $this->formatDuration($video->getDuration()),
        ];
        
        // Добавляем URL
        $schema['url'] = $videoUrl;
        
        if ($thumbnailUrl) {
            $schema['thumbnailUrl'] = $thumbnailUrl;
        }
        
        if ($contentUrl) {
            $schema['contentUrl'] = $contentUrl;
            $schema['embedUrl'] = $embedUrl;
        }
        
        // Информация о создателе
        $creator = $video->getCreatedBy();
        if ($creator) {
            $schema['author'] = [
                '@type' => 'Person',
                'name' => $creator->getUsername(),
                'url' => $this->urlGenerator->generate('app_member_profile', [
                    'username' => $creator->getUsername()
                ], UrlGeneratorInterface::ABSOLUTE_URL),
            ];
        }
        
        // Статистика взаимодействий
        $schema['interactionStatistic'] = [];
        
        // Просмотры
        $schema['interactionStatistic'][] = [
            '@type' => 'InteractionCounter',
            'interactionType' => ['@type' => 'WatchAction'],
            'userInteractionCount' => $video->getViewsCount(),
        ];
        
        // Лайки
        if ($video->getLikesCount() > 0) {
            $schema['interactionStatistic'][] = [
                '@type' => 'InteractionCounter',
                'interactionType' => ['@type' => 'LikeAction'],
                'userInteractionCount' => $video->getLikesCount(),
            ];
        }
        
        // Комментарии
        if ($video->getCommentsCount() > 0) {
            $schema['interactionStatistic'][] = [
                '@type' => 'InteractionCounter',
                'interactionType' => ['@type' => 'CommentAction'],
                'userInteractionCount' => $video->getCommentsCount(),
            ];
        }
        
        // Категории как keywords
        $categories = $video->getCategories();
        if ($categories->count() > 0) {
            $keywords = [];
            foreach ($categories as $category) {
                $keywords[] = $category->getName();
            }
            $schema['keywords'] = implode(', ', $keywords);
        }
        
        // Теги
        $tags = $video->getTags();
        if ($tags->count() > 0) {
            $tagNames = [];
            foreach ($tags as $tag) {
                $tagNames[] = $tag->getName();
            }
            if (isset($schema['keywords'])) {
                $schema['keywords'] .= ', ' . implode(', ', $tagNames);
            } else {
                $schema['keywords'] = implode(', ', $tagNames);
            }
        }
        
        // Исполнители/модели
        $performers = $video->getPerformers();
        if ($performers->count() > 0) {
            $schema['actor'] = [];
            foreach ($performers as $performer) {
                $schema['actor'][] = [
                    '@type' => 'Person',
                    'name' => $performer->getDisplayName(),
                    'url' => $this->urlGenerator->generate('app_model_show', [
                        'slug' => $performer->getSlug()
                    ], UrlGeneratorInterface::ABSOLUTE_URL),
                ];
            }
        }
        
        // Разрешение видео
        if ($video->getResolution()) {
            $schema['videoQuality'] = $video->getResolution();
        }
        
        // Формат
        if ($video->getFormat()) {
            $schema['encodingFormat'] = 'video/' . strtolower($video->getFormat());
        }
        
        // Генерируем JSON
        $json = json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
        return '<script type="application/ld+json">' . "\n" . $json . "\n" . '</script>';
    }
    
    /**
     * Форматирует длительность в формат ISO 8601 (PT1H30M45S)
     */
    private function formatDuration(int $seconds): string
    {
        if ($seconds <= 0) {
            return 'PT0S';
        }
        
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        
        $duration = 'PT';
        
        if ($hours > 0) {
            $duration .= $hours . 'H';
        }
        if ($minutes > 0) {
            $duration .= $minutes . 'M';
        }
        if ($secs > 0 || ($hours === 0 && $minutes === 0)) {
            $duration .= $secs . 'S';
        }
        
        return $duration;
    }
}
