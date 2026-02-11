<?php

namespace App\Controller;

use App\Repository\PostRepository;
use App\Repository\PostCategoryRepository;
use App\Service\SettingsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PostController extends AbstractController
{
    public function __construct(
        private SettingsService $settingsService
    ) {
    }

    #[Route('/posts', name: 'app_posts')]
    public function index(
        Request $request,
        PostRepository $postRepository
    ): Response {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = $this->settingsService->getVideosPerPage();
        $offset = ($page - 1) * $limit;

        $posts = $postRepository->findPublished($limit, $offset);
        $total = $postRepository->countPublished();

        return $this->render('post/index.html.twig', [
            'posts' => $posts,
            'page' => $page,
            'total_pages' => ceil($total / $limit),
            'seo_title' => $this->settingsService->get('seo_posts_title', 'Все посты'),
            'seo_description' => $this->settingsService->get('seo_posts_description'),
            'seo_keywords' => $this->settingsService->get('seo_posts_keywords'),
        ]);
    }

    #[Route('/post_category/{slug}', name: 'app_post_category')]
    public function category(
        string $slug,
        Request $request,
        PostCategoryRepository $categoryRepository,
        PostRepository $postRepository
    ): Response {
        $category = $categoryRepository->findBySlug($slug);
        
        if (!$category) {
            throw $this->createNotFoundException('Категория не найдена');
        }

        $page = max(1, $request->query->getInt('page', 1));
        $limit = $this->settingsService->getVideosPerPage();
        $offset = ($page - 1) * $limit;

        $posts = $postRepository->findByCategory($category, $limit, $offset);
        $total = $postRepository->countByCategory($category);

        return $this->render('post/category.html.twig', [
            'category' => $category,
            'posts' => $posts,
            'page' => $page,
            'total_pages' => ceil($total / $limit),
        ]);
    }

    #[Route('/{slug}', name: 'app_post_show', requirements: ['slug' => '^(?!videos|admin|api|members|channels|categories|tags|models|playlists|bookmarks|history|like|comments|secure-media|sitemap).*$'], priority: -10)]
    public function show(
        string $slug,
        PostRepository $postRepository
    ): Response {
        $post = $postRepository->findByFullSlug($slug);
        
        if (!$post) {
            throw $this->createNotFoundException('Страница не найдена');
        }

        // Получаем дочерние посты если есть
        $children = $postRepository->findChildren($post);

        return $this->render('post/show.html.twig', [
            'post' => $post,
            'children' => $children,
        ]);
    }
}