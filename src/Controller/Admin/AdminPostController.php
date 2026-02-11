<?php

namespace App\Controller\Admin;

use App\Entity\Post;
use App\Repository\PostRepository;
use App\Repository\PostCategoryRepository;
use App\Service\SettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/posts')]
#[IsGranted('ROLE_ADMIN')]
class AdminPostController extends AbstractController
{
    public function __construct(
        private SettingsService $settingsService
    ) {
    }

    #[Route('/', name: 'admin_posts')]
    public function index(
        Request $request,
        PostRepository $postRepository
    ): Response {
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = $request->query->getInt('per_page', $this->settingsService->getVideosPerPage());
        
        $allowedPerPage = [15, 25, 50, 100];
        if (!in_array($perPage, $allowedPerPage)) {
            $perPage = $this->settingsService->getVideosPerPage();
        }
        
        $limit = $perPage;
        $offset = ($page - 1) * $limit;
        $status = $request->query->get('status');

        $posts = $postRepository->findForAdmin($limit, $offset, $status);
        $total = $postRepository->countForAdmin($status);

        return $this->render('admin/posts/index.html.twig', [
            'posts' => $posts,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'total_pages' => ceil($total / $limit),
            'current_status' => $status,
        ]);
    }

    #[Route('/new', name: 'admin_post_new')]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        PostCategoryRepository $categoryRepository,
        PostRepository $postRepository,
        SluggerInterface $slugger
    ): Response {
        $post = new Post();
        $categories = $categoryRepository->findActive();
        $parentPosts = $postRepository->findRootPosts();

        if ($request->isMethod('POST')) {
            $post->setTitle($request->request->get('title'));
            $post->setSlug($request->request->get('slug') ?: $slugger->slug($post->getTitle())->lower());
            $post->setContent($request->request->get('content'));
            $post->setExcerpt($request->request->get('excerpt'));
            $post->setStatus($request->request->get('status', 'draft'));
            $post->setMetaTitle($request->request->get('meta_title'));
            $post->setMetaDescription($request->request->get('meta_description'));
            $post->setMetaKeywords($request->request->get('meta_keywords'));
            $post->setSortOrder((int) $request->request->get('sort_order', 0));
            $post->setIsActive($request->request->getBoolean('is_active', true));
            $post->setAuthor($this->getUser());

            // Родительский пост
            $parentId = $request->request->get('parent_id');
            if ($parentId && $parentId !== '') {
                $parentId = (int) $parentId;
                $parent = $postRepository->find($parentId);
                if ($parent) {
                    $post->setParent($parent);
                }
            } else {
                $post->setParent(null);
            }

            // Категории
            $categoryIds = $request->request->all('categories') ?? [];
            foreach ($categoryIds as $categoryId) {
                $category = $categoryRepository->find($categoryId);
                if ($category) {
                    $post->addCategory($category);
                }
            }

            // Загрузка изображения
            /** @var UploadedFile $imageFile */
            $imageFile = $request->files->get('featured_image');
            if ($imageFile) {
                $newFilename = $this->handleImageUpload($imageFile, $slugger);
                if ($newFilename) {
                    $post->setFeaturedImage($newFilename);
                }
            }

            // Установка даты публикации
            if ($post->getStatus() === 'published' && !$post->getPublishedAt()) {
                $post->setPublishedAt(new \DateTime());
            }

            $em->persist($post);
            $em->flush();

            $this->addFlash('success', 'Пост успешно создан');
            return $this->redirectToRoute('admin_posts');
        }

        return $this->render('admin/posts/form.html.twig', [
            'post' => $post,
            'categories' => $categories,
            'parent_posts' => $parentPosts,
            'is_edit' => false,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_post_edit')]
    public function edit(
        Post $post,
        Request $request,
        EntityManagerInterface $em,
        PostCategoryRepository $categoryRepository,
        PostRepository $postRepository,
        SluggerInterface $slugger
    ): Response {
        $categories = $categoryRepository->findActive();
        $parentPosts = $postRepository->findRootPosts();

        if ($request->isMethod('POST')) {
            $post->setTitle($request->request->get('title'));
            $post->setSlug($request->request->get('slug'));
            $post->setContent($request->request->get('content'));
            $post->setExcerpt($request->request->get('excerpt'));
            $post->setStatus($request->request->get('status', 'draft'));
            $post->setMetaTitle($request->request->get('meta_title'));
            $post->setMetaDescription($request->request->get('meta_description'));
            $post->setMetaKeywords($request->request->get('meta_keywords'));
            $post->setSortOrder((int) $request->request->get('sort_order', 0));
            $post->setIsActive($request->request->getBoolean('is_active', true));

            // Родительский пост
            $parentId = $request->request->get('parent_id');
            if ($parentId && $parentId !== '') {
                $parentId = (int) $parentId;
                if ($parentId !== $post->getId()) {
                    $parent = $postRepository->find($parentId);
                    if ($parent) {
                        $post->setParent($parent);
                    }
                }
            } else {
                $post->setParent(null);
            }

            // Очистка и установка категорий
            $post->getCategories()->clear();
            $categoryIds = $request->request->all('categories') ?? [];
            foreach ($categoryIds as $categoryId) {
                $category = $categoryRepository->find($categoryId);
                if ($category) {
                    $post->addCategory($category);
                }
            }

            // Загрузка изображения
            /** @var UploadedFile $imageFile */
            $imageFile = $request->files->get('featured_image');
            if ($imageFile) {
                $newFilename = $this->handleImageUpload($imageFile, $slugger);
                if ($newFilename) {
                    $post->setFeaturedImage($newFilename);
                }
            }

            // Установка даты публикации
            if ($post->getStatus() === 'published' && !$post->getPublishedAt()) {
                $post->setPublishedAt(new \DateTime());
            }

            $em->flush();

            $this->addFlash('success', 'Пост успешно обновлен');
            return $this->redirectToRoute('admin_posts');
        }

        return $this->render('admin/posts/form.html.twig', [
            'post' => $post,
            'categories' => $categories,
            'parent_posts' => $parentPosts,
            'is_edit' => true,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_post_delete', methods: ['POST'])]
    public function delete(
        Post $post,
        EntityManagerInterface $em
    ): Response {
        $em->remove($post);
        $em->flush();

        $this->addFlash('success', 'Пост успешно удален');
        return $this->redirectToRoute('admin_posts');
    }

    private function handleImageUpload(UploadedFile $file, SluggerInterface $slugger): ?string
    {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        try {
            $file->move(
                $this->getParameter('kernel.project_dir') . '/public/media/posts',
                $newFilename
            );
            return $newFilename;
        } catch (FileException $e) {
            $this->addFlash('error', 'Ошибка загрузки изображения');
            return null;
        }
    }
}