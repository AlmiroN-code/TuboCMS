<?php

namespace App\Controller\Admin;

use App\Entity\Category;
use App\Entity\ModelProfile;
use App\Entity\Tag;
use App\Entity\Video;
use App\Repository\CategoryRepository;
use App\Repository\ModelProfileRepository;
use App\Repository\TagRepository;
use App\Repository\VideoRepository;
use App\Service\SettingsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * API контроллер для генерации SEO мета-тегов в админке
 */
#[Route('/admin/api/seo')]
#[IsGranted('ROLE_ADMIN')]
class AdminSeoGeneratorController extends AbstractController
{
    private const MAX_DESCRIPTION_LENGTH = 160;
    private const MAX_TITLE_LENGTH = 70;

    public function __construct(
        private VideoRepository $videoRepository,
        private CategoryRepository $categoryRepository,
        private ModelProfileRepository $modelRepository,
        private TagRepository $tagRepository,
        private SettingsService $settingsService,
        private TranslatorInterface $translator
    ) {
    }

    #[Route('/generate', name: 'admin_seo_generate', methods: ['POST'])]
    public function generate(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        $type = $data['type'] ?? null;
        $id = $data['id'] ?? null;
        
        // Для новых сущностей используем переданные данные
        $entityData = $data['data'] ?? [];
        
        if (!$type) {
            return new JsonResponse(['error' => 'Type is required'], 400);
        }
        
        $result = match ($type) {
            'video' => $this->generateVideoSeo($id, $entityData),
            'category' => $this->generateCategorySeo($id, $entityData),
            'model' => $this->generateModelSeo($id, $entityData),
            'tag' => $this->generateTagSeo($id, $entityData),
            default => ['error' => 'Unknown type']
        };
        
        if (isset($result['error'])) {
            return new JsonResponse($result, 400);
        }
        
        return new JsonResponse($result);
    }

    private function generateVideoSeo(?int $id, array $data): array
    {
        $title = $data['title'] ?? '';
        $description = $data['description'] ?? '';
        $categories = [];
        $duration = 0;
        $viewsCount = 0;
        
        if ($id) {
            $video = $this->videoRepository->find($id);
            if ($video) {
                $title = $title ?: $video->getTitle();
                $description = $description ?: $video->getDescription();
                $duration = $video->getDuration();
                $viewsCount = $video->getViewsCount();
                foreach ($video->getCategories()->slice(0, 3) as $cat) {
                    $categories[] = $cat->getName();
                }
            }
        }
        
        if (!$title) {
            return ['error' => 'Title is required'];
        }
        
        // Генерируем meta title
        $prefix = $this->settingsService->get('seo_video_title_prefix', '');
        $suffix = $this->settingsService->get('seo_video_title_suffix', '');
        $metaTitle = trim($prefix . ' ' . $title . ' ' . $suffix);
        $metaTitle = $this->truncate($metaTitle, self::MAX_TITLE_LENGTH);
        
        // Генерируем meta description
        if ($description) {
            $metaDescription = $this->truncate($description, self::MAX_DESCRIPTION_LENGTH);
        } else {
            $metaDescription = $this->translator->trans('seo.video.auto_description', [
                '%title%' => $title,
                '%duration%' => $duration > 0 ? $this->formatDuration($duration) : '',
                '%categories%' => implode(', ', $categories),
                '%views%' => $this->formatNumber($viewsCount),
            ]);
            $metaDescription = $this->truncate($metaDescription, self::MAX_DESCRIPTION_LENGTH);
        }
        
        // Генерируем keywords из названия и категорий
        $keywords = array_merge(
            $this->extractKeywords($title),
            $categories
        );
        $metaKeywords = implode(', ', array_unique($keywords));
        
        return [
            'metaTitle' => $metaTitle,
            'metaDescription' => $metaDescription,
            'metaKeywords' => $metaKeywords,
        ];
    }

    private function generateCategorySeo(?int $id, array $data): array
    {
        $name = $data['name'] ?? '';
        $description = $data['description'] ?? '';
        $videosCount = 0;
        
        if ($id) {
            $category = $this->categoryRepository->find($id);
            if ($category) {
                $name = $name ?: $category->getName();
                $description = $description ?: $category->getDescription();
                $videosCount = $category->getVideosCount();
            }
        }
        
        if (!$name) {
            return ['error' => 'Name is required'];
        }
        
        // Генерируем meta title
        $metaTitle = $this->translator->trans('seo.category.auto_title', [
            '%count%' => $videosCount,
            '%name%' => $name,
        ]);
        $metaTitle = $this->truncate($metaTitle, self::MAX_TITLE_LENGTH);
        
        // Генерируем meta description
        if ($description) {
            $metaDescription = $this->truncate($description, self::MAX_DESCRIPTION_LENGTH);
        } else {
            $metaDescription = $this->translator->trans('seo.category.auto_description', [
                '%count%' => $videosCount,
                '%name%' => $name,
            ]);
        }
        $metaDescription = $this->truncate($metaDescription, self::MAX_DESCRIPTION_LENGTH);
        
        // Keywords
        $keywords = $this->extractKeywords($name);
        $keywords[] = $this->translator->trans('seo.keywords.video');
        $keywords[] = $this->translator->trans('seo.keywords.watch');
        $metaKeywords = implode(', ', array_unique($keywords));
        
        return [
            'metaTitle' => $metaTitle,
            'metaDescription' => $metaDescription,
            'metaKeywords' => $metaKeywords,
        ];
    }

    private function generateModelSeo(?int $id, array $data): array
    {
        $name = $data['display_name'] ?? $data['name'] ?? '';
        $bio = $data['bio'] ?? '';
        $videosCount = 0;
        $viewsCount = 0;
        
        if ($id) {
            $model = $this->modelRepository->find($id);
            if ($model) {
                $name = $name ?: $model->getDisplayName();
                $bio = $bio ?: $model->getBio();
                $videosCount = $model->getVideosCount();
                $viewsCount = $model->getViewsCount();
            }
        }
        
        if (!$name) {
            return ['error' => 'Name is required'];
        }
        
        // Генерируем meta title
        $metaTitle = $this->translator->trans('seo.model.auto_title', [
            '%name%' => $name,
            '%count%' => $videosCount,
        ]);
        $metaTitle = $this->truncate($metaTitle, self::MAX_TITLE_LENGTH);
        
        // Генерируем meta description
        if ($bio) {
            $metaDescription = $this->truncate($bio, self::MAX_DESCRIPTION_LENGTH);
        } else {
            $metaDescription = $this->translator->trans('seo.model.auto_description', [
                '%name%' => $name,
                '%videos%' => $videosCount,
                '%views%' => $this->formatNumber($viewsCount),
            ]);
        }
        $metaDescription = $this->truncate($metaDescription, self::MAX_DESCRIPTION_LENGTH);
        
        // Keywords
        $keywords = $this->extractKeywords($name);
        $keywords[] = $this->translator->trans('seo.keywords.model');
        $keywords[] = $this->translator->trans('seo.keywords.video');
        $metaKeywords = implode(', ', array_unique($keywords));
        
        return [
            'metaTitle' => $metaTitle,
            'metaDescription' => $metaDescription,
            'metaKeywords' => $metaKeywords,
        ];
    }

    private function generateTagSeo(?int $id, array $data): array
    {
        $name = $data['name'] ?? '';
        $description = $data['description'] ?? '';
        $usageCount = 0;
        
        if ($id) {
            $tag = $this->tagRepository->find($id);
            if ($tag) {
                $name = $name ?: $tag->getName();
                $description = $description ?: $tag->getDescription();
                $usageCount = $tag->getUsageCount();
            }
        }
        
        if (!$name) {
            return ['error' => 'Name is required'];
        }
        
        // Генерируем meta title
        $metaTitle = $this->translator->trans('seo.tag.auto_title', [
            '%name%' => $name,
            '%count%' => $usageCount,
        ]);
        $metaTitle = $this->truncate($metaTitle, self::MAX_TITLE_LENGTH);
        
        // Генерируем meta description
        if ($description) {
            $metaDescription = $this->truncate($description, self::MAX_DESCRIPTION_LENGTH);
        } else {
            $metaDescription = $this->translator->trans('seo.tag.auto_description', [
                '%count%' => $usageCount,
                '%name%' => $name,
            ]);
        }
        $metaDescription = $this->truncate($metaDescription, self::MAX_DESCRIPTION_LENGTH);
        
        // Keywords
        $keywords = [$name];
        $keywords[] = $this->translator->trans('seo.keywords.tag');
        $keywords[] = $this->translator->trans('seo.keywords.video');
        $metaKeywords = implode(', ', array_unique($keywords));
        
        return [
            'metaTitle' => $metaTitle,
            'metaDescription' => $metaDescription,
            'metaKeywords' => $metaKeywords,
        ];
    }

    private function truncate(string $text, int $maxLength): string
    {
        $text = strip_tags($text);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }
        
        $truncated = mb_substr($text, 0, $maxLength);
        $lastSpace = mb_strrpos($truncated, ' ');
        
        if ($lastSpace !== false && $lastSpace > 50) {
            $truncated = mb_substr($truncated, 0, $lastSpace);
        }
        
        return rtrim($truncated, '.,!?;:') . '...';
    }

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

    private function extractKeywords(string $text): array
    {
        // Убираем спецсимволы и разбиваем на слова
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        $words = preg_split('/\s+/', mb_strtolower($text));
        
        // Фильтруем короткие слова и стоп-слова
        $stopWords = ['и', 'в', 'на', 'с', 'по', 'для', 'от', 'до', 'the', 'a', 'an', 'in', 'on', 'at', 'for'];
        
        return array_filter($words, function($word) use ($stopWords) {
            return mb_strlen($word) > 2 && !in_array($word, $stopWords);
        });
    }
}
