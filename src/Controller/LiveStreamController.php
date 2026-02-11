<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\LiveStream;
use App\Form\LiveStreamType;
use App\Repository\LiveStreamRepository;
use App\Service\LiveStreamService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/live')]
class LiveStreamController extends AbstractController
{
    public function __construct(
        private readonly LiveStreamService $liveStreamService,
        private readonly LiveStreamRepository $repository,
        private readonly \App\Service\SettingsService $settingsService,
    ) {
    }

    #[Route('', name: 'live_index', methods: ['GET'])]
    public function index(): Response
    {
        $liveStreams = $this->liveStreamService->getLiveStreams(20);
        $scheduledStreams = $this->liveStreamService->getScheduledStreams(10);
        $liveCount = $this->liveStreamService->countLiveStreams();

        return $this->render('live/index.html.twig', [
            'liveStreams' => $liveStreams,
            'scheduledStreams' => $scheduledStreams,
            'liveCount' => $liveCount,
            'seo_title' => $this->settingsService->get('seo_live_title', 'Live Стримы'),
            'seo_description' => $this->settingsService->get('seo_live_description'),
            'seo_keywords' => $this->settingsService->get('seo_live_keywords'),
        ]);
    }

    #[Route('/create', name: 'live_create', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(Request $request): Response
    {
        $stream = new LiveStream();
        $form = $this->createForm(LiveStreamType::class, $stream);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();
            $stream = $this->liveStreamService->createStream(
                $user,
                $stream->getTitle(),
                $stream->getDescription(),
                $stream->getChannel(),
                $stream->getScheduledAt()
            );

            $this->addFlash('success', 'Стрим успешно создан!');
            return $this->redirectToRoute('live_manage', ['id' => $stream->getId()]);
        }

        return $this->render('live/create.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/my-streams', name: 'live_my_streams', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function myStreams(): Response
    {
        $user = $this->getUser();
        $streams = $this->liveStreamService->getStreamerStreams($user, 50);

        return $this->render('live/my_streams.html.twig', [
            'streams' => $streams,
        ]);
    }

    #[Route('/manage/{id}', name: 'live_manage', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function manage(int $id): Response
    {
        $stream = $this->repository->find($id);
        
        if (!$stream) {
            throw $this->createNotFoundException('Стрим не найден');
        }
        
        $this->denyAccessUnlessGranted('edit', $stream);

        return $this->render('live/manage.html.twig', [
            'stream' => $stream,
        ]);
    }

    #[Route('/edit/{id}', name: 'live_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function edit(Request $request, int $id): Response
    {
        $stream = $this->repository->find($id);
        
        if (!$stream) {
            throw $this->createNotFoundException('Стрим не найден');
        }
        
        $this->denyAccessUnlessGranted('edit', $stream);

        $form = $this->createForm(LiveStreamType::class, $stream);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->repository->save($stream);
            $this->addFlash('success', 'Стрим обновлен!');
            return $this->redirectToRoute('live_manage', ['id' => $stream->getId()]);
        }

        return $this->render('live/edit.html.twig', [
            'form' => $form,
            'stream' => $stream,
        ]);
    }

    #[Route('/delete/{id}', name: 'live_delete', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function delete(Request $request, int $id): Response
    {
        $stream = $this->repository->find($id);
        
        if (!$stream) {
            throw $this->createNotFoundException('Стрим не найден');
        }
        
        $this->denyAccessUnlessGranted('delete', $stream);

        if ($this->isCsrfTokenValid('delete' . $stream->getId(), $request->request->get('_token'))) {
            $this->repository->remove($stream);
            $this->addFlash('success', 'Стрим удален!');
        }

        return $this->redirectToRoute('live_index');
    }

    #[Route('/{slug}', name: 'live_show', methods: ['GET'])]
    public function show(string $slug): Response
    {
        $stream = $this->repository->findOneBy(['slug' => $slug]);
        
        if (!$stream) {
            throw $this->createNotFoundException('Стрим не найден');
        }

        return $this->render('live/show.html.twig', [
            'stream' => $stream,
        ]);
    }
}
