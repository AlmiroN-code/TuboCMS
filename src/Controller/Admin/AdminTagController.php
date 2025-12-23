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

    private function handleSave(Request $request, Tag $tag): Response
    {
        $tag->setName($request->request->get('name'));
        
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
