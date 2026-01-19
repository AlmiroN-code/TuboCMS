<?php

namespace App\Twig;

use App\Entity\Category;
use App\Entity\ModelProfile;
use App\Entity\Video;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig расширение для генерации Schema.org JSON-LD разметки
 * ItemList для списков и Person для профилей моделей
 */
class SchemaOrgExtension extends AbstractExtension
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('itemlist_schema', [$this, 'generateItemListSchema'], ['is_safe' => ['html']]),
            new TwigFunction('person_schema', [$this, 'generatePersonSchema'], ['is_safe' => ['html']]),
            new TwigFunction('category_schema', [$this, 'generateCategorySchema'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * Генерирует JSON-LD разметку ItemList для списка видео
     * 
     * @param Video[] $videos Массив видео
     * @param string $listName Название списка
     * @param string|null $listUrl URL списка
     */
    public function generateItemListSchema(array $videos, string $listName, ?string $listUrl = null): string
    {
        if (empty($videos)) {
            return '';
        }

        $siteUrl = $this->urlGenerator->generate('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $siteUrl = rtrim($siteUrl, '/');

        $itemListElements = [];
        $position = 1;

        foreach ($videos as $video) {
            if (!$video instanceof Video) {
                continue;
            }

            $videoUrl = $this->urlGenerator->generate('video_detail', [
                'slug' => $video->getSlug()
            ], UrlGeneratorInterface::ABSOLUTE_URL);

            $thumbnailUrl = $video->getPoster() 
                ? $siteUrl . '/media/' . $video->getPoster()
                : null;

            $item = [
                '@type' => 'ListItem',
                'position' => $position,
                'item' => [
                    '@type' => 'VideoObject',
                    'name' => $video->getTitle(),
                    'url' => $videoUrl,
                ]
            ];

            if ($thumbnailUrl) {
                $item['item']['thumbnailUrl'] = $thumbnailUrl;
            }

            if ($video->getDescription()) {
                $item['item']['description'] = mb_substr($video->getDescription(), 0, 200);
            }

            if ($video->getDuration() > 0) {
                $item['item']['duration'] = $this->formatDuration($video->getDuration());
            }

            $itemListElements[] = $item;
            $position++;
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => $listName,
            'numberOfItems' => count($itemListElements),
            'itemListElement' => $itemListElements,
        ];

        if ($listUrl) {
            $schema['url'] = $listUrl;
        }

        $json = json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return '<script type="application/ld+json">' . "\n" . $json . "\n" . '</script>';
    }

    /**
     * Генерирует JSON-LD разметку Person для профиля модели
     */
    public function generatePersonSchema(ModelProfile $model): string
    {
        $siteUrl = $this->urlGenerator->generate('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $siteUrl = rtrim($siteUrl, '/');

        $profileUrl = $this->urlGenerator->generate('app_model_show', [
            'slug' => $model->getSlug()
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            'name' => $model->getDisplayName(),
            'url' => $profileUrl,
        ];

        // Аватар
        if ($model->getAvatar()) {
            $schema['image'] = $siteUrl . '/media/avatars/' . $model->getAvatar();
        }

        // Биография
        if ($model->getBio()) {
            $schema['description'] = mb_substr($model->getBio(), 0, 500);
        }

        // Альтернативные имена (псевдонимы)
        $aliases = $model->getAliases();
        if (!empty($aliases)) {
            $schema['alternateName'] = $aliases;
        }

        // Пол
        if ($model->getGender()) {
            $genderMap = [
                'female' => 'Female',
                'male' => 'Male',
                'trans' => 'Person',
                'other' => 'Person',
            ];
            $schema['gender'] = $genderMap[$model->getGender()] ?? 'Person';
        }

        // Дата рождения
        if ($model->getBirthDate()) {
            $schema['birthDate'] = $model->getBirthDate()->format('Y-m-d');
        }

        // Страна
        if ($model->getCountry()) {
            $schema['nationality'] = [
                '@type' => 'Country',
                'name' => $model->getCountry(),
            ];
        }

        // Рост (в метрах для Schema.org)
        if ($model->getHeight()) {
            $schema['height'] = [
                '@type' => 'QuantitativeValue',
                'value' => $model->getHeight(),
                'unitCode' => 'CMT',
            ];
        }

        // Вес
        if ($model->getWeight()) {
            $schema['weight'] = [
                '@type' => 'QuantitativeValue',
                'value' => $model->getWeight(),
                'unitCode' => 'KGM',
            ];
        }

        // Профессия
        $schema['jobTitle'] = 'Performer';

        // Статистика взаимодействий
        $schema['interactionStatistic'] = [];

        // Подписчики
        if ($model->getSubscribersCount() > 0) {
            $schema['interactionStatistic'][] = [
                '@type' => 'InteractionCounter',
                'interactionType' => ['@type' => 'FollowAction'],
                'userInteractionCount' => $model->getSubscribersCount(),
            ];
        }

        // Просмотры
        if ($model->getViewsCount() > 0) {
            $schema['interactionStatistic'][] = [
                '@type' => 'InteractionCounter',
                'interactionType' => ['@type' => 'WatchAction'],
                'userInteractionCount' => $model->getViewsCount(),
            ];
        }

        // Лайки
        if ($model->getLikesCount() > 0) {
            $schema['interactionStatistic'][] = [
                '@type' => 'InteractionCounter',
                'interactionType' => ['@type' => 'LikeAction'],
                'userInteractionCount' => $model->getLikesCount(),
            ];
        }

        // Удаляем пустой массив статистики
        if (empty($schema['interactionStatistic'])) {
            unset($schema['interactionStatistic']);
        }

        // Количество работ (видео)
        if ($model->getVideosCount() > 0) {
            $schema['performerIn'] = [
                '@type' => 'ItemList',
                'numberOfItems' => $model->getVideosCount(),
            ];
        }

        $json = json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return '<script type="application/ld+json">' . "\n" . $json . "\n" . '</script>';
    }

    /**
     * Генерирует JSON-LD разметку CollectionPage для категории
     */
    public function generateCategorySchema(Category $category, array $videos, int $totalVideos): string
    {
        $siteUrl = $this->urlGenerator->generate('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $siteUrl = rtrim($siteUrl, '/');

        $categoryUrl = $this->urlGenerator->generate('app_category_show', [
            'slug' => $category->getSlug()
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => $category->getName(),
            'url' => $categoryUrl,
            'numberOfItems' => $totalVideos,
        ];

        if ($category->getDescription()) {
            $schema['description'] = $category->getDescription();
        }

        // Добавляем ItemList с видео
        if (!empty($videos)) {
            $itemListElements = [];
            $position = 1;

            foreach ($videos as $video) {
                if (!$video instanceof Video) {
                    continue;
                }

                $videoUrl = $this->urlGenerator->generate('video_detail', [
                    'slug' => $video->getSlug()
                ], UrlGeneratorInterface::ABSOLUTE_URL);

                $itemListElements[] = [
                    '@type' => 'ListItem',
                    'position' => $position,
                    'url' => $videoUrl,
                ];
                $position++;
            }

            $schema['mainEntity'] = [
                '@type' => 'ItemList',
                'numberOfItems' => $totalVideos,
                'itemListElement' => $itemListElements,
            ];
        }

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
