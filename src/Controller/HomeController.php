<?php

namespace App\Controller;

use App\Repository\CategoryRepository;
use App\Repository\VideoRepository;
use App\Service\SettingsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(
        VideoRepository $videoRepository,
        CategoryRepository $categoryRepository,
        SettingsService $settingsService
    ): Response
    {
        // Получаем настройки главной страницы
        $showFeatured = $settingsService->get('home_show_featured_videos', true);
        $showNew = $settingsService->get('home_show_new_videos', true);
        $showPopular = $settingsService->get('home_show_popular_videos', true);
        
        $featuredCount = $settingsService->get('home_featured_videos_count', 10);
        $newCount = $settingsService->get('home_new_videos_count', 12);
        $popularCount = $settingsService->get('home_popular_videos_count', 12);
        
        // Получаем видео в зависимости от настроек
        $featuredVideos = $showFeatured ? $videoRepository->findFeatured($featuredCount) : [];
        $newVideos = $showNew ? $videoRepository->findRecent($newCount) : [];
        $popularVideos = $showPopular ? $videoRepository->findPopular($popularCount) : [];
        
        $categories = $categoryRepository->findActive();

        return $this->render('home/index.html.twig', [
            'featured_videos' => $featuredVideos,
            'new_videos' => $newVideos,
            'popular_videos' => $popularVideos,
            'categories' => $categories,
            'show_featured' => $showFeatured,
            'show_new' => $showNew,
            'show_popular' => $showPopular,
        ]);
    }
}
