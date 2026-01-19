<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\VideoRepository;
use App\Service\SettingsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\CacheInterface;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(
        VideoRepository $videoRepository,
        CategoryRepository $categoryRepository,
        SettingsService $settingsService,
        CacheInterface $cache
    ): Response
    {
        // Получаем все настройки главной страницы одним вызовом
        $homeSettings = [
            'show_featured' => $settingsService->get('home_show_featured_videos', true),
            'show_new' => $settingsService->get('home_show_new_videos', true),
            'show_popular' => $settingsService->get('home_show_popular_videos', true),
            'show_recently_watched' => $settingsService->get('home_show_recently_watched', true),
            'featured_count' => $settingsService->get('home_featured_videos_count', 10),
            'new_count' => $settingsService->get('home_new_videos_count', 12),
            'popular_count' => $settingsService->get('home_popular_videos_count', 12),
            'recently_watched_count' => $settingsService->get('home_recently_watched_count', 8),
        ];
        
        $user = $this->getUser();
        
        // Используем отдельные оптимизированные запросы с кешированием
        $featuredVideos = [];
        $newVideos = [];
        $popularVideos = [];
        $recentlyWatchedVideos = [];
        
        if ($homeSettings['show_featured']) {
            $featuredVideos = $cache->get(
                'home_featured_videos_' . $homeSettings['featured_count'],
                fn() => $videoRepository->findFeaturedForHome($homeSettings['featured_count']),
                300 // 5 минут
            );
        }
        
        if ($homeSettings['show_new']) {
            $newVideos = $cache->get(
                'home_new_videos_' . $homeSettings['new_count'],
                fn() => $videoRepository->findNewestForHome($homeSettings['new_count']),
                120 // 2 минуты для новых видео
            );
        }
        
        if ($homeSettings['show_popular']) {
            $popularVideos = $cache->get(
                'home_popular_videos_' . $homeSettings['popular_count'],
                fn() => $videoRepository->findPopularForHome($homeSettings['popular_count']),
                300 // 5 минут
            );
        }
        
        if ($homeSettings['show_recently_watched'] && $user instanceof User) {
            $recentlyWatchedVideos = $cache->get(
                'home_recently_watched_' . $user->getId() . '_' . $homeSettings['recently_watched_count'],
                fn() => $videoRepository->findRecentlyWatchedForUser($user, $homeSettings['recently_watched_count']),
                300 // 5 минут
            );
        }

        // Кешируем категории
        $categories = $cache->get(
            'home_categories',
            fn() => $categoryRepository->findBy(['isActive' => true], ['name' => 'ASC'], 20),
            600 // 10 минут
        );

        $response = $this->render('home/index.html.twig', [
            'featured_videos' => $featuredVideos,
            'new_videos' => $newVideos,
            'popular_videos' => $popularVideos,
            'recently_watched_videos' => $recentlyWatchedVideos,
            'categories' => $categories,
            'show_featured' => $homeSettings['show_featured'],
            'show_new' => $homeSettings['show_new'],
            'show_popular' => $homeSettings['show_popular'],
            'show_recently_watched' => $homeSettings['show_recently_watched'],
        ]);

        // HTTP кеширование для анонимных пользователей
        if (!$user) {
            $response->setSharedMaxAge(120); // 2 минуты
            $response->headers->addCacheControlDirective('must-revalidate');
        }

        return $response;
    }
}
