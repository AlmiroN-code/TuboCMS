<?php

namespace App\Controller\Admin;

use App\Entity\ModelProfile;
use App\Repository\ModelProfileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[Route('/admin/models')]
#[IsGranted('ROLE_ADMIN')]
class AdminModelController extends AbstractController
{
    public function __construct(
        private ModelProfileRepository $modelRepository,
        private EntityManagerInterface $em
    ) {
    }

    #[Route('', name: 'admin_models')]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 30;
        
        $qb = $this->modelRepository->createQueryBuilder('m')
            ->orderBy('m.videosCount', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);
        
        $models = $qb->getQuery()->getResult();
        $total = $this->modelRepository->count([]);
        
        return $this->render('admin/models/index.html.twig', [
            'models' => $models,
            'page' => $page,
            'total' => $total,
            'pages' => ceil($total / $limit),
        ]);
    }

    #[Route('/new', name: 'admin_models_new')]
    public function new(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            return $this->handleSave($request, new ModelProfile());
        }

        return $this->render('admin/models/form.html.twig', [
            'model' => new ModelProfile(),
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_models_edit')]
    public function edit(Request $request, ModelProfile $model): Response
    {
        if ($request->isMethod('POST')) {
            return $this->handleSave($request, $model);
        }

        return $this->render('admin/models/form.html.twig', [
            'model' => $model,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_models_delete', methods: ['POST'])]
    public function delete(ModelProfile $model): Response
    {
        $this->em->remove($model);
        $this->em->flush();
        
        $this->addFlash('success', 'Модель удалена');
        return $this->redirectToRoute('admin_models');
    }

    private function handleSave(Request $request, ModelProfile $model): Response
    {
        $displayName = $request->request->get('display_name');
        $model->setDisplayName($displayName);
        $model->setBio($request->request->get('bio'));
        
        if (!$model->getSlug()) {
            $slugger = new AsciiSlugger();
            $slug = $slugger->slug($displayName)->lower();
            $model->setSlug($slug);
        }
        
        $model->setUpdatedAt(new \DateTimeImmutable());
        
        $this->em->persist($model);
        $this->em->flush();
        
        $this->addFlash('success', 'Модель сохранена');
        return $this->redirectToRoute('admin_models');
    }
}
