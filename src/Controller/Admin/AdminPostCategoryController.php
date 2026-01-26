<?php

namespace App\Controller\Admin;

use App\Entity\PostCategory;
use App\Repository\PostCategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/post-categories')]
#[IsGranted('ROLE_ADMIN')]
class AdminPostCategoryController extends AbstractController
{
    #[Route('/', name: 'admin_post_categories')]
    public function index(PostCategoryRepository $categoryRepository): Response
    {
        $categories = $categoryRepository->findForAdmin();

        return $this->render('admin/post_categories/index.html.twig', [
            'categories' => $categories,
        ]);
    }

    #[Route('/new', name: 'admin_post_category_new')]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ): Response {
        $category = new PostCategory();

        if ($request->isMethod('POST')) {
            $category->setName($request->request->get('name'));
            $category->setSlug($request->request->get('slug') ?: $slugger->slug($category->getName())->lower());
            $category->setDescription($request->request->get('description'));
            $category->setMetaTitle($request->request->get('meta_title'));
            $category->setMetaDescription($request->request->get('meta_description'));
            $category->setMetaKeywords($request->request->get('meta_keywords'));
            $category->setSortOrder((int) $request->request->get('sort_order', 0));
            $category->setIsActive($request->request->getBoolean('is_active', true));

            // Загрузка изображения
            /** @var UploadedFile $imageFile */
            $imageFile = $request->files->get('image');
            if ($imageFile) {
                $newFilename = $this->handleImageUpload($imageFile, $slugger);
                if ($newFilename) {
                    $category->setImage($newFilename);
                }
            }

            $em->persist($category);
            $em->flush();

            $this->addFlash('success', 'Категория успешно создана');
            return $this->redirectToRoute('admin_post_categories');
        }

        return $this->render('admin/post_categories/form.html.twig', [
            'category' => $category,
            'is_edit' => false,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_post_category_edit')]
    public function edit(
        PostCategory $category,
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ): Response {
        if ($request->isMethod('POST')) {
            $category->setName($request->request->get('name'));
            $category->setSlug($request->request->get('slug'));
            $category->setDescription($request->request->get('description'));
            $category->setMetaTitle($request->request->get('meta_title'));
            $category->setMetaDescription($request->request->get('meta_description'));
            $category->setMetaKeywords($request->request->get('meta_keywords'));
            $category->setSortOrder((int) $request->request->get('sort_order', 0));
            $category->setIsActive($request->request->getBoolean('is_active', true));

            // Загрузка изображения
            /** @var UploadedFile $imageFile */
            $imageFile = $request->files->get('image');
            if ($imageFile) {
                $newFilename = $this->handleImageUpload($imageFile, $slugger);
                if ($newFilename) {
                    $category->setImage($newFilename);
                }
            }

            $em->flush();

            $this->addFlash('success', 'Категория успешно обновлена');
            return $this->redirectToRoute('admin_post_categories');
        }

        return $this->render('admin/post_categories/form.html.twig', [
            'category' => $category,
            'is_edit' => true,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_post_category_delete', methods: ['POST'])]
    public function delete(
        PostCategory $category,
        EntityManagerInterface $em
    ): Response {
        $em->remove($category);
        $em->flush();

        $this->addFlash('success', 'Категория успешно удалена');
        return $this->redirectToRoute('admin_post_categories');
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