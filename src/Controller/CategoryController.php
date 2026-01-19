<?php

namespace App\Controller;

use App\Repository\CategoryRepository;
use App\Repository\VideoRepository;
use App\Service\SeeAlsoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/categories')]
class CategoryController extends AbstractController
{
    #[Route('/', name: 'app_categories')]
    public function index(CategoryRepository $categoryRepository): Response
    {
        $categories = $categoryRepository->findActive();

        return $this->render('category/index.html.twig', [
            'categories' => $categories,
        ]);
    }

    #[Route('/{slug}', name: 'app_category_show')]
    public function show(
        string $slug,
        Request $request,
        CategoryRepository $categoryRepository,
        VideoRepository $videoRepository,
        SeeAlsoService $seeAlsoService
    ): Response
    {
        $category = $categoryRepository->findOneBy(['slug' => $slug]);

        if (!$category) {
            throw $this->createNotFoundException('Категория не найдена');
        }

        $page = max(1, $request->query->getInt('page', 1));
        $limit = 24;
        $offset = ($page - 1) * $limit;

        $videos = $videoRepository->findByCategory($category->getId(), $limit, $offset);

        // Блок "Смотрите также"
        $seeAlso = [
            'popular_videos' => $seeAlsoService->getPopularVideosForCategory($category, 6),
            'popular_tags' => $seeAlsoService->getPopularTagsForCategory($category, 10),
            'popular_models' => $seeAlsoService->getPopularModelsForCategory($category, 8),
        ];

        return $this->render('category/show.html.twig', [
            'category' => $category,
            'videos' => $videos,
            'videos_count' => $category->getVideosCount(),
            'page' => $page,
            'see_also' => $seeAlso,
        ]);
    }
}
