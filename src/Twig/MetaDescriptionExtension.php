<?php

namespace App\Twig;

use App\Entity\Category;
use App\Entity\ModelProfile;
use App\Entity\Tag;
use App\Entity\Video;
use App\Service\SettingsService;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig расширение для автоматической генерации мета-описаний
 */
class MetaDescriptionExtension extends AbstractExtension
{
    private const MAX_LENGTH = 160;
    private const MIN_LENGTH = 50;

    public function __construct(
        private SettingsService $settingsService,
        private TranslatorInterface $translator
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('auto_meta_description', [$this, 'generateMetaDescription']),
            new TwigFunction('auto_meta_title', [$this, 'generateMetaTitle']),
        ];
    }

    /**
     * Генерирует мета-описание для различных типов контента
     */
    public function generateMetaDescription(mixed $entity, ?string $fallback = null): string
    {
        if ($entity instanceof Video) {
            return $this->generateVideoDescription($entity);
        }

        if ($entity instanceof Category) {
            return $this->generateCategoryDescription($entity);
        }

        if ($entity instanceof ModelProfile) {
            return $this->generateModelDescription($entity);
        }

        if ($entity instanceof Tag) {
            return $this->generateTagDescription($entity);
        }

        return $fallback ?? $this->settingsService->get('site_description', '');
    }

    /**
     * Генерирует мета-заголовок для различных типов контента
     */
    public function generateMetaTitle(mixed $entity, ?string $fallback = null): string
    {
        if ($entity instanceof Video) {
            return $this->generateVideoTitle($entity);
        }

        if ($entity instanceof Category) {
            return $this->generateCategoryTitle($entity);
        }

        if ($entity instanceof ModelProfile) {
            return $this->generateModelTitle($entity);
        }

        if ($entity instanceof Tag) {
            return $this->generateTagTitle($entity);
        }

        return $fallback ?? '';
    }

    /**
     * Генерирует описание для видео
     */
    private function generateVideoDescription(Video $video): string
    {
        // Если есть кастомное описание, используем его
        if ($video->getDescription()) {
            return $this->truncate($video->getDescription(), self::MAX_LENGTH);
        }

        // Генерируем автоматически с использованием переводов
        $categoryNames = [];
        foreach ($video->getCategories()->slice(0, 3) as $category) {
            $categoryNames[] = $category->getName();
        }
        
        $description = $this->translator->trans('seo.video.auto_description', [
            '%title%' => $video->getTitle(),
            '%duration%' => $video->getDuration() > 0 ? $this->formatDuration($video->getDuration()) : '',
            '%categories%' => implode(', ', $categoryNames),
            '%views%' => $this->formatNumber($video->getViewsCount()),
        ]);
        
        return $this->truncate($description, self::MAX_LENGTH);
    }

    /**
     * Генерирует заголовок для видео
     */
    private function generateVideoTitle(Video $video): string
    {
        $prefix = $this->settingsService->get('seo_video_title_prefix', '');
        $suffix = $this->settingsService->get('seo_video_title_suffix', '');
        
        $title = trim($prefix . ' ' . $video->getTitle() . ' ' . $suffix);
        
        return $title;
    }

    /**
     * Генерирует описание для категории
     */
    private function generateCategoryDescription(Category $category): string
    {
        // Если есть кастомное мета-описание
        if ($category->getMetaDescription()) {
            return $this->truncate($category->getMetaDescription(), self::MAX_LENGTH);
        }

        // Если есть обычное описание
        if ($category->getDescription()) {
            return $this->truncate($category->getDescription(), self::MAX_LENGTH);
        }

        // Генерируем автоматически с использованием переводов
        $videosCount = $category->getVideosCount();
        
        $description = $this->translator->trans('seo.category.auto_description', [
            '%count%' => $videosCount,
            '%name%' => $category->getName(),
        ]);
        
        return $this->truncate($description, self::MAX_LENGTH);
    }

    /**
     * Генерирует заголовок для категории
     */
    private function generateCategoryTitle(Category $category): string
    {
        if ($category->getMetaTitle()) {
            return $category->getMetaTitle();
        }

        $videosCount = $category->getVideosCount();
        
        return $this->translator->trans('seo.category.auto_title', [
            '%count%' => $videosCount,
            '%name%' => $category->getName(),
        ]);
    }

    /**
     * Генерирует описание для модели
     */
    private function generateModelDescription(ModelProfile $model): string
    {
        // Если есть кастомное мета-описание
        $metaDescription = $model->getMetaDescription();
        if ($metaDescription) {
            return $this->truncate($metaDescription, self::MAX_LENGTH);
        }

        // Если есть биография
        if ($model->getBio()) {
            return $this->truncate($model->getBio(), self::MAX_LENGTH);
        }

        // Генерируем автоматически с использованием переводов
        $videosCount = $model->getVideosCount();
        $viewsCount = $model->getViewsCount();
        
        $description = $this->translator->trans('seo.model.auto_description', [
            '%name%' => $model->getDisplayName(),
            '%videos%' => $videosCount,
            '%views%' => $this->formatNumber($viewsCount),
        ]);
        
        return $this->truncate($description, self::MAX_LENGTH);
    }

    /**
     * Генерирует заголовок для модели
     */
    private function generateModelTitle(ModelProfile $model): string
    {
        $metaTitle = $model->getMetaTitle();
        if ($metaTitle) {
            return $metaTitle;
        }

        $videosCount = $model->getVideosCount();
        
        return $this->translator->trans('seo.model.auto_title', [
            '%name%' => $model->getDisplayName(),
            '%count%' => $videosCount,
        ]);
    }

    /**
     * Генерирует описание для тега
     */
    private function generateTagDescription(Tag $tag): string
    {
        // Если есть кастомное мета-описание
        if ($tag->getMetaDescription()) {
            return $this->truncate($tag->getMetaDescription(), self::MAX_LENGTH);
        }

        // Если есть обычное описание
        if ($tag->getDescription()) {
            return $this->truncate($tag->getDescription(), self::MAX_LENGTH);
        }

        // Генерируем автоматически с использованием переводов
        $usageCount = $tag->getUsageCount();
        
        $description = $this->translator->trans('seo.tag.auto_description', [
            '%count%' => $usageCount,
            '%name%' => $tag->getName(),
        ]);
        
        return $this->truncate($description, self::MAX_LENGTH);
    }

    /**
     * Генерирует заголовок для тега
     */
    private function generateTagTitle(Tag $tag): string
    {
        if ($tag->getMetaTitle()) {
            return $tag->getMetaTitle();
        }

        $usageCount = $tag->getUsageCount();
        
        return $this->translator->trans('seo.tag.auto_title', [
            '%name%' => $tag->getName(),
            '%count%' => $usageCount,
        ]);
    }

    /**
     * Обрезает текст до указанной длины
     */
    private function truncate(string $text, int $maxLength): string
    {
        // Убираем HTML теги и лишние пробелы
        $text = strip_tags($text);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }
        
        // Обрезаем по словам
        $truncated = mb_substr($text, 0, $maxLength);
        $lastSpace = mb_strrpos($truncated, ' ');
        
        if ($lastSpace !== false && $lastSpace > self::MIN_LENGTH) {
            $truncated = mb_substr($truncated, 0, $lastSpace);
        }
        
        return rtrim($truncated, '.,!?;:') . '...';
    }

    /**
     * Форматирует длительность в читаемый вид
     */
    private function formatDuration(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        
        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
        }
        
        return sprintf('%d:%02d', $minutes, $secs);
    }

    /**
     * Форматирует число с разделителями
     */
    private function formatNumber(int $number): string
    {
        if ($number >= 1000000) {
            return round($number / 1000000, 1) . 'M';
        }
        
        if ($number >= 1000) {
            return round($number / 1000, 1) . 'K';
        }
        
        return (string) $number;
    }
}
