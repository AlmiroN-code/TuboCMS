<?php

namespace App\Controller\Admin;

use App\Entity\Tag;
use App\Repository\TagRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[Route('/admin/tags')]
#[IsGranted('ROLE_ADMIN')]
class AdminTagController extends AbstractController
{
    public function __construct(
        private TagRepository $tagRepository,
        private EntityManagerInterface $em
    ) {
    }

    #[Route('', name: 'admin_tags')]
    public function index(): Response
    {
        return $this->render('admin/tags/index.html.twig', [
            'tags' => $this->tagRepository->findBy([], ['usageCount' => 'DESC']),
        ]);
    }

    #[Route('/new', name: 'admin_tags_new')]
    public function new(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            return $this->handleSave($request, new Tag());
        }

        return $this->render('admin/tags/form.html.twig', [
            'tag' => new Tag(),
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_tags_edit')]
    public function edit(Request $request, Tag $tag): Response
    {
        if ($request->isMethod('POST')) {
            return $this->handleSave($request, $tag);
        }

        return $this->render('admin/tags/form.html.twig', [
            'tag' => $tag,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_tags_delete', methods: ['POST'])]
    public function delete(Tag $tag): Response
    {
        $this->em->remove($tag);
        $this->em->flush();
        
        $this->addFlash('success', 'Тег удален');
        return $this->redirectToRoute('admin_tags');
    }

    #[Route('/create-ajax', name: 'admin_tags_create_ajax', methods: ['POST'])]
    public function createAjax(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);
        $tagName = trim($data['name'] ?? '');
        
        if (empty($tagName)) {
            return $this->json(['success' => false, 'error' => 'Название тега не может быть пустым']);
        }
        
        // Проверяем, существует ли уже такой тег
        $existingTag = $this->tagRepository->findOneBy(['name' => $tagName]);
        if ($existingTag) {
            return $this->json([
                'success' => true,
                'tag' => [
                    'id' => $existingTag->getId(),
                    'name' => $existingTag->getName()
                ]
            ]);
        }
        
        // Создаем новый тег
        $tag = new Tag();
        $tag->setName($tagName);
        
        // Генерация уникального slug
        $slugger = new AsciiSlugger();
        $baseSlug = $slugger->slug($tagName)->lower()->toString();
        $slug = $baseSlug;
        $counter = 1;
        
        // Проверяем уникальность slug
        while ($this->slugExists($slug)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
        
        $tag->setSlug($slug);
        
        $this->em->persist($tag);
        $this->em->flush();
        
        return $this->json([
            'success' => true,
            'tag' => [
                'id' => $tag->getId(),
                'name' => $tag->getName()
            ]
        ]);
    }

    /**
     * Проверяет существование slug в БД
     */
    private function slugExists(string $slug): bool
    {
        return $this->tagRepository->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    private function handleSave(Request $request, Tag $tag): Response
    {
        $tag->setName($request->request->get('name'));
        $tag->setDescription($request->request->get('description'));
        $tag->setMetaTitle($request->request->get('meta_title'));
        $tag->setMetaDescription($request->request->get('meta_description'));
        $tag->setMetaKeywords($request->request->get('meta_keywords'));
        
        if (!$tag->getSlug()) {
            $slugger = new AsciiSlugger();
            $slug = $slugger->slug($tag->getName())->lower();
            $tag->setSlug($slug);
        }
        
        $this->em->persist($tag);
        $this->em->flush();
        
        $this->addFlash('success', 'Тег сохранен');
        return $this->redirectToRoute('admin_tags');
    }
}
