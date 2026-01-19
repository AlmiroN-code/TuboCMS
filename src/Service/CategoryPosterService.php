<?php

namespace App\Service;

use App\Entity\Category;
use App\Repository\VideoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Сервис для автоматической генерации постеров категорий
 * на основе постеров видео в этих категориях.
 */
class CategoryPosterService
{
    public const CRITERIA_MOST_VIEWED = 'most_viewed';
    public const CRITERIA_MOST_RECENT = 'most_recent';
    public const CRITERIA_MOST_LIKED = 'most_liked';
    public const CRITERIA_RANDOM = 'random';

    public function __construct(
        private VideoRepository $videoRepository,
        private EntityManagerInterface $em,
        private SettingsService $settingsService,
        private LoggerInterface $logger,
        private string $projectDir
    ) {
    }

    /**
     * Проверяет, включена ли автогенерация постеров категорий.
     */
    public function isAutoGenerationEnabled(): bool
    {
        return $this->settingsService->get('category_poster_auto_generate', false);
    }

    /**
     * Возвращает критерий выбора видео для постера.
     */
    public function getSelectionCriteria(): string
    {
        return $this->settingsService->get('category_poster_criteria', self::CRITERIA_MOST_VIEWED);
    }

    /**
     * Генерирует постер для категории на основе видео.
     * 
     * @param Category $category Категория
     * @param bool $force Принудительно перезаписать существующий постер
     * @return bool Успешность генерации
     */
    public function generatePoster(Category $category, bool $force = false): bool
    {
        // Если постер уже есть и не принудительная генерация - пропускаем
        if (!$force && $category->getPoster()) {
            return false;
        }

        $criteria = $this->getSelectionCriteria();
        $video = $this->findVideoForPoster($category, $criteria);

        if (!$video || !$video->getPoster()) {
            $this->logger->info('No suitable video found for category poster', [
                'category_id' => $category->getId(),
                'category_name' => $category->getName(),
                'criteria' => $criteria
            ]);
            return false;
        }

        // Копируем постер видео в папку категорий
        $sourcePath = $this->resolveMediaPath($video->getPoster());
        
        if (!file_exists($sourcePath)) {
            $this->logger->warning('Video poster file not found', [
                'category_id' => $category->getId(),
                'video_id' => $video->getId(),
                'poster_path' => $video->getPoster()
            ]);
            return false;
        }

        // Создаём директорию для постеров категорий
        $categoryPosterDir = $this->projectDir . '/public/media/categories';
        if (!is_dir($categoryPosterDir)) {
            mkdir($categoryPosterDir, 0755, true);
        }

        // Удаляем старый постер если есть
        if ($category->getPoster()) {
            $oldPosterPath = $categoryPosterDir . '/' . $category->getPoster();
            if (file_exists($oldPosterPath)) {
                unlink($oldPosterPath);
            }
        }

        // Генерируем новое имя файла
        $extension = pathinfo($sourcePath, PATHINFO_EXTENSION);
        $newFilename = 'cat_' . $category->getId() . '_' . uniqid() . '.' . $extension;
        $destPath = $categoryPosterDir . '/' . $newFilename;

        // Копируем файл
        if (!copy($sourcePath, $destPath)) {
            $this->logger->error('Failed to copy poster file', [
                'source' => $sourcePath,
                'destination' => $destPath
            ]);
            return false;
        }

        // Обновляем категорию
        $category->setPoster($newFilename);
        $this->em->flush();

        $this->logger->info('Category poster generated successfully', [
            'category_id' => $category->getId(),
            'category_name' => $category->getName(),
            'video_id' => $video->getId(),
            'poster' => $newFilename
        ]);

        return true;
    }

    /**
     * Генерирует постеры для всех категорий без постеров.
     * 
     * @param bool $force Принудительно перезаписать существующие постеры
     * @return array Статистика генерации ['generated' => int, 'skipped' => int, 'failed' => int]
     */
    public function generateAllPosters(bool $force = false): array
    {
        $categoryRepo = $this->em->getRepository(Category::class);
        $categories = $categoryRepo->findAll();

        $stats = ['generated' => 0, 'skipped' => 0, 'failed' => 0];

        foreach ($categories as $category) {
            if (!$force && $category->getPoster()) {
                $stats['skipped']++;
                continue;
            }

            if ($this->generatePoster($category, $force)) {
                $stats['generated']++;
            } else {
                $stats['failed']++;
            }
        }

        return $stats;
    }

    /**
     * Находит видео для использования его постера.
     */
    private function findVideoForPoster(Category $category, string $criteria): ?\App\Entity\Video
    {
        $qb = $this->videoRepository->createQueryBuilder('v')
            ->join('v.categories', 'c')
            ->where('c.id = :categoryId')
            ->andWhere('v.status = :status')
            ->andWhere('v.poster IS NOT NULL')
            ->andWhere('v.poster != :empty')
            ->setParameter('categoryId', $category->getId())
            ->setParameter('status', 'published')
            ->setParameter('empty', '')
            ->setMaxResults(1);

        switch ($criteria) {
            case self::CRITERIA_MOST_VIEWED:
                $qb->orderBy('v.viewsCount', 'DESC');
                break;
            case self::CRITERIA_MOST_RECENT:
                $qb->orderBy('v.createdAt', 'DESC');
                break;
            case self::CRITERIA_MOST_LIKED:
                $qb->orderBy('v.likesCount', 'DESC');
                break;
            case self::CRITERIA_RANDOM:
                $qb->orderBy('RAND()');
                break;
            default:
                $qb->orderBy('v.viewsCount', 'DESC');
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Преобразует путь к медиафайлу в абсолютный путь.
     */
    private function resolveMediaPath(string $path): string
    {
        // Если путь уже абсолютный или начинается с /media/
        if (str_starts_with($path, '/')) {
            return $this->projectDir . '/public' . $path;
        }
        
        // Если путь относительный
        return $this->projectDir . '/public/media/' . $path;
    }

    /**
     * Возвращает доступные критерии выбора видео.
     */
    public static function getAvailableCriteria(): array
    {
        return [
            self::CRITERIA_MOST_VIEWED => 'Самое просматриваемое',
            self::CRITERIA_MOST_RECENT => 'Самое новое',
            self::CRITERIA_MOST_LIKED => 'Самое популярное (лайки)',
            self::CRITERIA_RANDOM => 'Случайное',
        ];
    }
}
