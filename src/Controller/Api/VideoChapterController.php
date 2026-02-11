<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Video;
use App\Entity\VideoChapter;
use App\Repository\VideoChapterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/video/{videoId}/chapters')]
class VideoChapterController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private VideoChapterRepository $chapterRepo
    ) {
    }

    #[Route('', name: 'api_video_chapters_list', methods: ['GET'])]
    public function list(int $videoId): JsonResponse
    {
        $video = $this->em->getRepository(Video::class)->find($videoId);
        
        if (!$video) {
            return $this->json(['error' => 'Video not found'], 404);
        }

        $chapters = $this->chapterRepo->findByVideoOrdered($video);

        return $this->json([
            'chapters' => array_map(fn($chapter) => [
                'id' => $chapter->getId(),
                'timestamp' => $chapter->getTimestamp(),
                'formatted_timestamp' => $chapter->getFormattedTimestamp(),
                'title' => $chapter->getTitle(),
                'description' => $chapter->getDescription(),
            ], $chapters)
        ]);
    }

    #[Route('', name: 'api_video_chapters_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(int $videoId, Request $request): JsonResponse
    {
        $video = $this->em->getRepository(Video::class)->find($videoId);
        
        if (!$video) {
            return $this->json(['error' => 'Video not found'], 404);
        }

        // Проверка прав: только автор видео или админ
        if ($video->getCreatedBy() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['error' => 'Access denied'], 403);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['timestamp']) || !isset($data['title'])) {
            return $this->json(['error' => 'Missing required fields'], 400);
        }

        $chapter = new VideoChapter();
        $chapter->setVideo($video);
        $chapter->setTimestamp((int)$data['timestamp']);
        $chapter->setTitle($data['title']);
        $chapter->setDescription($data['description'] ?? null);
        $chapter->setCreatedBy($this->getUser());

        $this->em->persist($chapter);
        $this->em->flush();

        return $this->json([
            'success' => true,
            'chapter' => [
                'id' => $chapter->getId(),
                'timestamp' => $chapter->getTimestamp(),
                'formatted_timestamp' => $chapter->getFormattedTimestamp(),
                'title' => $chapter->getTitle(),
                'description' => $chapter->getDescription(),
            ]
        ], 201);
    }

    #[Route('/{chapterId}', name: 'api_video_chapters_update', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function update(int $videoId, int $chapterId, Request $request): JsonResponse
    {
        $chapter = $this->chapterRepo->find($chapterId);
        
        if (!$chapter || $chapter->getVideo()->getId() !== $videoId) {
            return $this->json(['error' => 'Chapter not found'], 404);
        }

        // Проверка прав
        if ($chapter->getCreatedBy() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['error' => 'Access denied'], 403);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['timestamp'])) {
            $chapter->setTimestamp((int)$data['timestamp']);
        }
        if (isset($data['title'])) {
            $chapter->setTitle($data['title']);
        }
        if (array_key_exists('description', $data)) {
            $chapter->setDescription($data['description']);
        }

        $this->em->flush();

        return $this->json([
            'success' => true,
            'chapter' => [
                'id' => $chapter->getId(),
                'timestamp' => $chapter->getTimestamp(),
                'formatted_timestamp' => $chapter->getFormattedTimestamp(),
                'title' => $chapter->getTitle(),
                'description' => $chapter->getDescription(),
            ]
        ]);
    }

    #[Route('/{chapterId}', name: 'api_video_chapters_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function delete(int $videoId, int $chapterId): JsonResponse
    {
        $chapter = $this->chapterRepo->find($chapterId);
        
        if (!$chapter || $chapter->getVideo()->getId() !== $videoId) {
            return $this->json(['error' => 'Chapter not found'], 404);
        }

        // Проверка прав
        if ($chapter->getCreatedBy() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['error' => 'Access denied'], 403);
        }

        $this->em->remove($chapter);
        $this->em->flush();

        return $this->json(['success' => true]);
    }
}
