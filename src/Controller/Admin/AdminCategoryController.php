<?php

namespace App\Controller\Admin;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use App\Service\CategoryPosterService;
use App\Service\ImageService;
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
        private EntityManagerInterface $em,
        private CategoryPosterService $categoryPosterService,
        private ImageService $imageService
    ) {
    }

    #[Route('', name: 'admin_categories')]
    public function index(): Response
    {
        // Сортировка: сначала по orderPosition (если указан > 0), затем по алфавиту
        $categories = $this->categoryRepository->createQueryBuilder('c')
            ->orderBy('CASE WHEN c.orderPosition > 0 THEN 0 ELSE 1 END', 'ASC')
            ->addOrderBy('c.orderPosition', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('admin/categories/index.html.twig', [
            'categories' => $categories,
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

    #[Route('/create-ajax', name: 'admin_categories_create_ajax', methods: ['POST'])]
    public function createAjax(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);
        $categoryName = trim($data['name'] ?? '');
        
        if (empty($categoryName)) {
            return $this->json(['success' => false, 'error' => 'Название категории не может быть пустым']);
        }
        
        // Проверяем, существует ли уже такая категория
        $existingCategory = $this->categoryRepository->findOneBy(['name' => $categoryName]);
        if ($existingCategory) {
            return $this->json([
                'success' => true,
                'category' => [
                    'id' => $existingCategory->getId(),
                    'name' => $existingCategory->getName()
                ]
            ]);
        }
        
        // Создаем новую категорию
        $category = new Category();
        $category->setName($categoryName);
        $category->setActive(true);
        $category->setOrderPosition(0);
        
        // Генерация уникального slug
        $slugger = new AsciiSlugger();
        $baseSlug = $slugger->slug($categoryName)->lower()->toString();
        $slug = $baseSlug;
        $counter = 1;
        
        while ($this->slugExists($slug)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
        
        $category->setSlug($slug);
        
        $this->em->persist($category);
        $this->em->flush();
        
        return $this->json([
            'success' => true,
            'category' => [
                'id' => $category->getId(),
                'name' => $category->getName()
            ]
        ]);
    }

    private function handleSave(Request $request, Category $category): Response
    {
        $category->setName($request->request->get('name'));
        $category->setDescription($request->request->get('description'));
        $category->setActive($request->request->get('is_active') === '1');
        $category->setOrderPosition((int) $request->request->get('order_position', 0));
        
        // SEO метатеги
        $category->setMetaTitle($request->request->get('meta_title'));
        $category->setMetaDescription($request->request->get('meta_description'));
        $category->setMetaKeywords($request->request->get('meta_keywords'));
        
        // Обработка загрузки постера
        $posterFile = $request->files->get('poster');
        if ($posterFile) {
            try {
                // Удаляем старый постер если есть
                if ($category->getPoster()) {
                    $this->imageService->deleteImage($category->getPoster(), $this->getParameter('categories_directory'));
                }
                
                $newFilename = $this->imageService->processCategoryPoster($posterFile);
                $category->setPoster($newFilename);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Ошибка при загрузке постера: ' . $e->getMessage());
            }
        }
        
        // Генерация уникального slug
        if (!$category->getSlug()) {
            $slugger = new AsciiSlugger();
            $baseSlug = $slugger->slug($category->getName())->lower()->toString();
            $slug = $baseSlug;
            $counter = 1;
            
            // Проверяем уникальность slug
            while ($this->slugExists($slug, $category->getId())) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }
            
            $category->setSlug($slug);
        }
        
        $this->em->persist($category);
        $this->em->flush();
        
        // Автогенерация постера если включена и постер не задан
        if (!$category->getPoster() && $this->categoryPosterService->isAutoGenerationEnabled()) {
            if ($this->categoryPosterService->generatePoster($category)) {
                $this->addFlash('info', 'Постер категории сгенерирован автоматически');
            }
        }
        
        $this->addFlash('success', 'Категория сохранена');
        return $this->redirectToRoute('admin_categories');
    }

    /**
     * Проверяет существование slug в БД (исключая текущую категорию при редактировании)
     */
    private function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $qb = $this->categoryRepository->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.slug = :slug')
            ->setParameter('slug', $slug);
        
        if ($excludeId !== null) {
            $qb->andWhere('c.id != :id')
               ->setParameter('id', $excludeId);
        }
        
        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }
}
