<?php

namespace App\Controller;

use App\Entity\Channel;
use App\Entity\Video;
use App\Repository\VideoRepository;
use App\Repository\ChannelRepository;
use App\Service\ChannelService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/channel/{slug}/videos')]
#[IsGranted('ROLE_USER')]
class ChannelVideoController extends AbstractController
{
    public function __construct(
        private VideoRepository $videoRepository,
        private ChannelRepository $channelRepository,
        private EntityManagerInterface $em,
        private ChannelService $channelService
    ) {}

    #[Route('/manage', name: 'channel_videos_manage')]
    public function manage(string $slug): Response
    {
        $channel = $this->channelRepository->findOneBy(['slug' => $slug]);
        
        if (!$channel) {
            throw $this->createNotFoundException('Канал не найден');
        }

        // Проверяем права доступа
        if ($channel->getOwner() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Нет доступа к управлению этим каналом');
        }

        // Видео канала
        $channelVideos = $this->videoRepository->findBy(
            ['channel' => $channel, 'createdBy' => $this->getUser()],
            ['createdAt' => 'DESC']
        );

        // Видео пользователя без канала
        $userVideos = $this->videoRepository->findBy(
            ['channel' => null, 'createdBy' => $this->getUser()],
            ['createdAt' => 'DESC']
        );

        return $this->render('channel/videos/manage.html.twig', [
            'channel' => $channel,
            'channelVideos' => $channelVideos,
            'userVideos' => $userVideos,
        ]);
    }

    #[Route('/add', name: 'channel_videos_add', methods: ['POST'])]
    public function addVideo(string $slug, Request $request): JsonResponse
    {
        $channel = $this->channelRepository->findOneBy(['slug' => $slug]);
        
        if (!$channel) {
            return $this->json(['success' => false, 'message' => 'Канал не найден'], 404);
        }

        // Проверяем права доступа
        if ($channel->getOwner() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['success' => false, 'message' => 'Нет доступа'], 403);
        }

        $videoId = $request->request->get('video_id');
        $video = $this->videoRepository->find($videoId);

        if (!$video) {
            return $this->json(['success' => false, 'message' => 'Видео не найдено']);
        }

        // Проверяем что видео принадлежит пользователю
        if ($video->getCreatedBy() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['success' => false, 'message' => 'Нет доступа к этому видео']);
        }

        $video->setChannel($channel);
        $this->em->flush();

        // Обновляем статистику канала
        $this->channelService->updateChannelStats($channel);

        return $this->json([
            'success' => true,
            'message' => 'Видео добавлено в канал',
            'video' => [
                'id' => $video->getId(),
                'title' => $video->getTitle(),
                'slug' => $video->getSlug()
            ]
        ]);
    }

    #[Route('/remove', name: 'channel_videos_remove', methods: ['POST'])]
    public function removeVideo(string $slug, Request $request): JsonResponse
    {
        $channel = $this->channelRepository->findOneBy(['slug' => $slug]);
        
        if (!$channel) {
            return $this->json(['success' => false, 'message' => 'Канал не найден'], 404);
        }

        // Проверяем права доступа
        if ($channel->getOwner() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['success' => false, 'message' => 'Нет доступа'], 403);
        }

        $videoId = $request->request->get('video_id');
        $video = $this->videoRepository->find($videoId);

        if (!$video) {
            return $this->json(['success' => false, 'message' => 'Видео не найдено']);
        }

        // Проверяем что видео принадлежит каналу
        if ($video->getChannel() !== $channel) {
            return $this->json(['success' => false, 'message' => 'Видео не принадлежит этому каналу']);
        }

        // Проверяем права на видео
        if ($video->getCreatedBy() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['success' => false, 'message' => 'Нет доступа к этому видео']);
        }

        $video->setChannel(null);
        $this->em->flush();

        // Обновляем статистику канала
        $this->channelService->updateChannelStats($channel);

        return $this->json([
            'success' => true,
            'message' => 'Видео удалено из канала',
            'video' => [
                'id' => $video->getId(),
                'title' => $video->getTitle(),
                'slug' => $video->getSlug()
            ]
        ]);
    }

    #[Route('/bulk-add', name: 'channel_videos_bulk_add', methods: ['POST'])]
    public function bulkAddVideos(string $slug, Request $request): JsonResponse
    {
        $channel = $this->channelRepository->findOneBy(['slug' => $slug]);
        
        if (!$channel) {
            return $this->json(['success' => false, 'message' => 'Канал не найден'], 404);
        }

        // Проверяем права доступа
        if ($channel->getOwner() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['success' => false, 'message' => 'Нет доступа'], 403);
        }

        $videoIds = $request->request->all('video_ids');
        if (empty($videoIds)) {
            return $this->json(['success' => false, 'message' => 'Не выбрано ни одного видео']);
        }

        $videos = $this->videoRepository->findBy(['id' => $videoIds]);
        $addedCount = 0;

        foreach ($videos as $video) {
            // Проверяем права на видео
            if ($video->getCreatedBy() === $this->getUser() || $this->isGranted('ROLE_ADMIN')) {
                $video->setChannel($channel);
                $addedCount++;
            }
        }

        $this->em->flush();

        // Обновляем статистику канала
        $this->channelService->updateChannelStats($channel);

        return $this->json([
            'success' => true,
            'message' => "Добавлено видео в канал: {$addedCount}",
            'added_count' => $addedCount
        ]);
    }

    #[Route('/bulk-remove', name: 'channel_videos_bulk_remove', methods: ['POST'])]
    public function bulkRemoveVideos(string $slug, Request $request): JsonResponse
    {
        $channel = $this->channelRepository->findOneBy(['slug' => $slug]);
        
        if (!$channel) {
            return $this->json(['success' => false, 'message' => 'Канал не найден'], 404);
        }

        // Проверяем права доступа
        if ($channel->getOwner() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['success' => false, 'message' => 'Нет доступа'], 403);
        }

        $videoIds = $request->request->all('video_ids');
        if (empty($videoIds)) {
            return $this->json(['success' => false, 'message' => 'Не выбрано ни одного видео']);
        }

        $videos = $this->videoRepository->findBy(['id' => $videoIds, 'channel' => $channel]);
        $removedCount = 0;

        foreach ($videos as $video) {
            // Проверяем права на видео
            if ($video->getCreatedBy() === $this->getUser() || $this->isGranted('ROLE_ADMIN')) {
                $video->setChannel(null);
                $removedCount++;
            }
        }

        $this->em->flush();

        // Обновляем статистику канала
        $this->channelService->updateChannelStats($channel);

        return $this->json([
            'success' => true,
            'message' => "Удалено видео из канала: {$removedCount}",
            'removed_count' => $removedCount
        ]);
    }
}