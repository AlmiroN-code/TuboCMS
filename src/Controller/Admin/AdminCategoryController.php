<?php

namespace App\Controller\Admin;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[Route('/admin/categories')]
#[IsGranted('ROLE_ADMIN')]
class AdminCategoryController extends AbstractController
{
    public function __construct(
        private CategoryRepository $categoryRepository,
        private EntityManagerInterface $em
    ) {
    }

    #[Route('', name: 'admin_categories')]
    public function index(): Response
    {
        return $this->render('admin/categories/index.html.twig', [
            'categories' => $this->categoryRepository->findBy([], ['orderPosition' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'admin_categories_new')]
    public function new(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            return $this->handleSave($request, new Category());
        }

        return $this->render('admin/categories/form.html.twig', [
            'category' => new Category(),
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_categories_edit')]
    public function edit(Request $request, Category $category): Response
    {
        if ($request->isMethod('POST')) {
            return $this->handleSave($request, $category);
        }

        return $this->render('admin/categories/form.html.twig', [
            'category' => $category,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_categories_delete', methods: ['POST'])]
    public function delete(Category $category): Response
    {
        $this->em->remove($category);
        $this->em->flush();
        
        $this->addFlash('success', 'Категория удалена');
        return $this->redirectToRoute('admin_categories');
    }

    private function handleSave(Request $request, Category $category): Response
    {
        $category->setName($request->request->get('name'));
        $category->setDescription($request->request->get('description'));
        $category->setActive($request->request->get('is_active') === '1');
        $category->setOrderPosition((int) $request->request->get('order_position', 0));
        
        if (!$category->getSlug()) {
            $slugger = new AsciiSlugger();
            $slug = $slugger->slug($category->getName())->lower();
            $category->setSlug($slug);
        }
        
        $this->em->persist($category);
        $this->em->flush();
        
        $this->addFlash('success', 'Категория сохранена');
        return $this->redirectToRoute('admin_categories');
    }
}
