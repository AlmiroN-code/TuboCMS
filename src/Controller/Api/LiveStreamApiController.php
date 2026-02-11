<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\LiveStream;
use App\Service\LiveStreamService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/live')]
class LiveStreamApiController extends AbstractController
{
    public function __construct(
        private readonly LiveStreamService $liveStreamService,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/status/{id}', name: 'api_live_status', methods: ['GET'])]
    public function status(int $id): JsonResponse
    {
        $stream = $this->liveStreamService->findByStreamKey($id) 
            ?? $this->em->getRepository(LiveStream::class)->find($id);
            
        if (!$stream) {
            return $this->json(['error' => 'Stream not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id' => $stream->getId(),
            'status' => $stream->getStatus(),
            'isLive' => $stream->isLive(),
            'viewersCount' => $stream->getViewersCount(),
            'startedAt' => $stream->getStartedAt()?->format('c'),
            'duration' => $stream->getDuration(),
        ]);
    }

    #[Route('/start/{id}', name: 'api_live_start', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function start(int $id): JsonResponse
    {
        $stream = $this->em->getRepository(LiveStream::class)->find($id);
        
        if (!$stream) {
            return $this->json(['error' => 'Stream not found'], Response::HTTP_NOT_FOUND);
        }
        
        $this->denyAccessUnlessGranted('edit', $stream);

        try {
            $this->liveStreamService->startStream($stream);
            return $this->json([
                'success' => true,
                'message' => 'Stream started',
                'status' => $stream->getStatus(),
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/end/{id}', name: 'api_live_end', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function end(int $id): JsonResponse
    {
        $stream = $this->em->getRepository(LiveStream::class)->find($id);
        
        if (!$stream) {
            return $this->json(['error' => 'Stream not found'], Response::HTTP_NOT_FOUND);
        }
        
        $this->denyAccessUnlessGranted('edit', $stream);

        try {
            $this->liveStreamService->endStream($stream);
            return $this->json([
                'success' => true,
                'message' => 'Stream ended',
                'status' => $stream->getStatus(),
                'duration' => $stream->getDuration(),
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/regenerate-key/{id}', name: 'api_live_regenerate_key', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function regenerateKey(int $id): JsonResponse
    {
        $stream = $this->em->getRepository(LiveStream::class)->find($id);
        
        if (!$stream) {
            return $this->json(['error' => 'Stream not found'], Response::HTTP_NOT_FOUND);
        }
        
        $this->denyAccessUnlessGranted('edit', $stream);

        $this->liveStreamService->regenerateStreamKey($stream);

        return $this->json([
            'success' => true,
            'message' => 'Stream key regenerated',
            'streamKey' => $stream->getStreamKey(),
        ]);
    }

    #[Route('/viewer/join/{id}', name: 'api_live_viewer_join', methods: ['POST'])]
    public function viewerJoin(int $id): JsonResponse
    {
        $stream = $this->em->getRepository(LiveStream::class)->find($id);
        
        if (!$stream) {
            return $this->json(['error' => 'Stream not found'], Response::HTTP_NOT_FOUND);
        }
        
        if (!$stream->isLive()) {
            return $this->json([
                'success' => false,
                'message' => 'Stream is not live',
            ], Response::HTTP_BAD_REQUEST);
        }

        $this->liveStreamService->incrementViewer($stream);

        return $this->json([
            'success' => true,
            'viewersCount' => $stream->getViewersCount(),
        ]);
    }

    #[Route('/viewer/leave/{id}', name: 'api_live_viewer_leave', methods: ['POST'])]
    public function viewerLeave(int $id): JsonResponse
    {
        $stream = $this->em->getRepository(LiveStream::class)->find($id);
        
        if (!$stream) {
            return $this->json(['error' => 'Stream not found'], Response::HTTP_NOT_FOUND);
        }
        
        $this->liveStreamService->decrementViewer($stream);

        return $this->json([
            'success' => true,
            'viewersCount' => $stream->getViewersCount(),
        ]);
    }

    #[Route('/webhook/publish', name: 'api_live_webhook_publish', methods: ['POST'])]
    public function webhookPublish(Request $request): JsonResponse
    {
        $streamKey = $request->request->get('name');
        
        if (!$streamKey) {
            return $this->json(['error' => 'Missing stream key'], Response::HTTP_BAD_REQUEST);
        }

        $stream = $this->liveStreamService->findByStreamKey($streamKey);
        
        if (!$stream) {
            return $this->json(['error' => 'Invalid stream key'], Response::HTTP_FORBIDDEN);
        }

        if ($stream->getStatus() !== LiveStream::STATUS_SCHEDULED) {
            return $this->json(['error' => 'Stream not scheduled'], Response::HTTP_FORBIDDEN);
        }

        $this->liveStreamService->startStream($stream);

        return $this->json(['success' => true]);
    }

    #[Route('/webhook/publish_done', name: 'api_live_webhook_publish_done', methods: ['POST'])]
    public function webhookPublishDone(Request $request): JsonResponse
    {
        $streamKey = $request->request->get('name');
        
        if (!$streamKey) {
            return $this->json(['error' => 'Missing stream key'], Response::HTTP_BAD_REQUEST);
        }

        $stream = $this->liveStreamService->findByStreamKey($streamKey);
        
        if (!$stream) {
            return $this->json(['error' => 'Invalid stream key'], Response::HTTP_NOT_FOUND);
        }

        if ($stream->isLive()) {
            $this->liveStreamService->endStream($stream);
        }

        return $this->json(['success' => true]);
    }
}
