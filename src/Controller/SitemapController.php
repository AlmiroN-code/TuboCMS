<?php

namespace App\Controller;

use App\Repository\CategoryRepository;
use App\Repository\ModelProfileRepository;
use App\Repository\TagRepository;
use App\Repository\VideoRepository;
use App\Service\SettingsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SitemapController extends AbstractController
{
    public function __construct(
        private VideoRepository $videoRepository,
        private CategoryRepository $categoryRepository,
        private ModelProfileRepository $modelRepository,
        private TagRepository $tagRepository,
        private SettingsService $settingsService
    ) {
    }

    /**
     * Главный sitemap index - ссылается на все под-sitemaps
     */
    #[Route('/sitemap.xml', name: 'sitemap_index', defaults: ['_format' => 'xml'])]
    public function index(): Response
    {
        $sitemaps = [
            [
                'loc' => $this->generateUrl('sitemap_main', [], UrlGeneratorInterface::ABSOLUTE_URL),
                'lastmod' => date('Y-m-d'),
            ],
            [
                'loc' => $this->generateUrl('sitemap_videos', [], UrlGeneratorInterface::ABSOLUTE_URL),
                'lastmod' => date('Y-m-d'),
            ],
            [
                'loc' => $this->generateUrl('sitemap_categories', [], UrlGeneratorInterface::ABSOLUTE_URL),
                'lastmod' => date('Y-m-d'),
            ],
            [
                'loc' => $this->generateUrl('sitemap_models', [], UrlGeneratorInterface::ABSOLUTE_URL),
                'lastmod' => date('Y-m-d'),
            ],
            [
                'loc' => $this->generateUrl('sitemap_tags', [], UrlGeneratorInterface::ABSOLUTE_URL),
                'lastmod' => date('Y-m-d'),
            ],
        ];

        $response = $this->render('sitemap/index.xml.twig', [
            'sitemaps' => $sitemaps,
        ]);
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');

        return $response;
    }

    /**
     * Sitemap для основных страниц сайта
     */
    #[Route('/sitemap-main.xml', name: 'sitemap_main', defaults: ['_format' => 'xml'])]
    public function main(): Response
    {
        $urls = [
            [
                'loc' => $this->generateUrl('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL),
                'changefreq' => 'daily',
                'priority' => '1.0',
            ],
            [
                'loc' => $this->generateUrl('app_videos', [], UrlGeneratorInterface::ABSOLUTE_URL),
                'changefreq' => 'daily',
                'priority' => '0.9',
            ],
            [
                'loc' => $this->generateUrl('app_categories', [], UrlGeneratorInterface::ABSOLUTE_URL),
                'changefreq' => 'weekly',
                'priority' => '0.8',
            ],
            [
                'loc' => $this->generateUrl('app_models', [], UrlGeneratorInterface::ABSOLUTE_URL),
                'changefreq' => 'weekly',
                'priority' => '0.8',
            ],
            [
                'loc' => $this->generateUrl('app_tags', [], UrlGeneratorInterface::ABSOLUTE_URL),
                'changefreq' => 'weekly',
                'priority' => '0.7',
            ],
        ];

        $response = $this->render('sitemap/urlset.xml.twig', ['urls' => $urls]);
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');

        return $response;
    }

    /**
     * Video Sitemap с расширенной информацией
     */
    #[Route('/sitemap-videos.xml', name: 'sitemap_videos', defaults: ['_format' => 'xml'])]
    public function videos(): Response
    {
        $videos = $this->videoRepository->findBy(
            ['status' => 'published'],
            ['createdAt' => 'DESC'],
            50000 // Лимит для sitemap
        );

        $siteUrl = $this->generateUrl('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $siteUrl = rtrim($siteUrl, '/');

        $videoUrls = [];
        foreach ($videos as $video) {
            $videoUrl = [
                'loc' => $this->generateUrl('video_detail', ['slug' => $video->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
                'lastmod' => $video->getUpdatedAt()?->format('Y-m-d') ?? $video->getCreatedAt()->format('Y-m-d'),
                'changefreq' => 'weekly',
                'priority' => '0.8',
                'video' => [
                    'thumbnail_loc' => $video->getPoster() ? $siteUrl . '/media/' . $video->getPoster() : null,
                    'title' => $video->getTitle(),
                    'description' => $video->getDescription() ?? $video->getTitle(),
                    'duration' => $video->getDuration(),
                    'view_count' => $video->getViewsCount(),
                    'publication_date' => $video->getCreatedAt()->format('Y-m-d\TH:i:sP'),
                    'family_friendly' => 'no',
                    'category' => $video->getCategories()->count() > 0 ? $video->getCategories()->first()->getName() : null,
                ],
            ];
            $videoUrls[] = $videoUrl;
        }

        $response = $this->render('sitemap/videos.xml.twig', ['videos' => $videoUrls]);
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');

        return $response;
    }

    /**
     * Sitemap для категорий
     */
    #[Route('/sitemap-categories.xml', name: 'sitemap_categories', defaults: ['_format' => 'xml'])]
    public function categories(): Response
    {
        $categories = $this->categoryRepository->findBy(['isActive' => true]);

        $urls = [];
        foreach ($categories as $category) {
            $urls[] = [
                'loc' => $this->generateUrl('app_category_show', ['slug' => $category->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
                'changefreq' => 'weekly',
                'priority' => '0.7',
            ];
        }

        $response = $this->render('sitemap/urlset.xml.twig', ['urls' => $urls]);
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');

        return $response;
    }

    /**
     * Sitemap для моделей
     */
    #[Route('/sitemap-models.xml', name: 'sitemap_models', defaults: ['_format' => 'xml'])]
    public function models(): Response
    {
        $models = $this->modelRepository->findBy(['isActive' => true]);

        $urls = [];
        foreach ($models as $model) {
            $urls[] = [
                'loc' => $this->generateUrl('app_model_show', ['slug' => $model->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
                'lastmod' => $model->getUpdatedAt()?->format('Y-m-d') ?? date('Y-m-d'),
                'changefreq' => 'weekly',
                'priority' => '0.7',
            ];
        }

        $response = $this->render('sitemap/urlset.xml.twig', ['urls' => $urls]);
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');

        return $response;
    }

    /**
     * Sitemap для тегов
     */
    #[Route('/sitemap-tags.xml', name: 'sitemap_tags', defaults: ['_format' => 'xml'])]
    public function tags(): Response
    {
        $tags = $this->tagRepository->findAll();

        $urls = [];
        foreach ($tags as $tag) {
            $urls[] = [
                'loc' => $this->generateUrl('app_tag_show', ['slug' => $tag->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
                'changefreq' => 'weekly',
                'priority' => '0.6',
            ];
        }

        $response = $this->render('sitemap/urlset.xml.twig', ['urls' => $urls]);
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');

        return $response;
    }
}
