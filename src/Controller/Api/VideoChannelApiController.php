<?php

namespace App\Controller\Api;

use App\Entity\Video;
use App\Entity\Channel;
use App\Repository\VideoRepository;
use App\Repository\ChannelRepository;
use App\Service\ChannelService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/videos')]
#[IsGranted('ROLE_USER')]
class VideoChannelApiController extends AbstractController
{
    public function __construct(
        private VideoRepository $videoRepository,
        private ChannelRepository $channelRepository,
        private EntityManagerInterface $em,
        private ChannelService $channelService
    ) {}

    #[Route('/{id}/move-to-channel', name: 'api_video_move_to_channel', methods: ['POST'])]
    public function moveToChannel(Video $video, Request $request): JsonResponse
    {
        // Проверяем права доступа к видео
        if ($video->getCreatedBy() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['success' => false, 'message' => 'Нет доступа к этому видео'], 403);
        }

        $data = json_decode($request->getContent(), true);
        $channelId = $data['channel_id'] ?? null;

        $oldChannel = $video->getChannel();

        if ($channelId) {
            $newChannel = $this->channelRepository->find($channelId);
            
            if (!$newChannel) {
                return $this->json(['success' => false, 'message' => 'Канал не найден']);
            }

            // Проверяем права доступа к каналу
            if ($newChannel->getOwner() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
                return $this->json(['success' => false, 'message' => 'Нет доступа к этому каналу'], 403);
            }

            $video->setChannel($newChannel);
        } else {
            // Убираем видео из канала
            $video->setChannel(null);
        }

        $this->em->flush();

        // Обновляем статистику старого канала
        if ($oldChannel) {
            $this->channelService->updateChannelStats($oldChannel);
        }

        // Обновляем статистику нового канала
        if ($video->getChannel()) {
            $this->channelService->updateChannelStats($video->getChannel());
        }

        return $this->json([
            'success' => true,
            'message' => $channelId ? 'Видео перемещено в канал' : 'Видео удалено из канала',
            'video' => [
                'id' => $video->getId(),
                'title' => $video->getTitle(),
                'channel' => $video->getChannel() ? [
                    'id' => $video->getChannel()->getId(),
                    'name' => $video->getChannel()->getName(),
                    'slug' => $video->getChannel()->getSlug()
                ] : null
            ]
        ]);
    }

    #[Route('/bulk-move-to-channel', name: 'api_videos_bulk_move_to_channel', methods: ['POST'])]
    public function bulkMoveToChannel(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $videoIds = $data['video_ids'] ?? [];
        $channelId = $data['channel_id'] ?? null;

        if (empty($videoIds)) {
            return $this->json(['success' => false, 'message' => 'Не выбрано ни одного видео']);
        }

        $videos = $this->videoRepository->findBy(['id' => $videoIds]);
        $newChannel = null;

        if ($channelId) {
            $newChannel = $this->channelRepository->find($channelId);
            
            if (!$newChannel) {
                return $this->json(['success' => false, 'message' => 'Канал не найден']);
            }

            // Проверяем права доступа к каналу
            if ($newChannel->getOwner() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
                return $this->json(['success' => false, 'message' => 'Нет доступа к этому каналу'], 403);
            }
        }

        $movedCount = 0;
        $updatedChannels = [];

        foreach ($videos as $video) {
            // Проверяем права на видео
            if ($video->getCreatedBy() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
                continue;
            }

            $oldChannel = $video->getChannel();
            
            // Сохраняем старый канал для обновления статистики
            if ($oldChannel && !in_array($oldChannel->getId(), $updatedChannels)) {
                $updatedChannels[] = $oldChannel->getId();
            }

            $video->setChannel($newChannel);
            $movedCount++;
        }

        // Добавляем новый канал для обновления статистики
        if ($newChannel && !in_array($newChannel->getId(), $updatedChannels)) {
            $updatedChannels[] = $newChannel->getId();
        }

        $this->em->flush();

        // Обновляем статистику всех затронутых каналов
        foreach ($updatedChannels as $channelId) {
            $channel = $this->channelRepository->find($channelId);
            if ($channel) {
                $this->channelService->updateChannelStats($channel);
            }
        }

        $message = $channelId 
            ? "Перемещено видео в канал: {$movedCount}" 
            : "Удалено видео из каналов: {$movedCount}";

        return $this->json([
            'success' => true,
            'message' => $message,
            'moved_count' => $movedCount,
            'target_channel' => $newChannel ? [
                'id' => $newChannel->getId(),
                'name' => $newChannel->getName(),
                'slug' => $newChannel->getSlug()
            ] : null
        ]);
    }

    #[Route('/user-channels', name: 'api_user_channels', methods: ['GET'])]
    public function getUserChannels(): JsonResponse
    {
        $channels = $this->channelRepository->findBy(
            ['owner' => $this->getUser(), 'isActive' => true],
            ['name' => 'ASC']
        );

        $channelsData = array_map(function($channel) {
            return [
                'id' => $channel->getId(),
                'name' => $channel->getName(),
                'slug' => $channel->getSlug(),
                'videosCount' => $channel->getVideosCount()
            ];
        }, $channels);

        return $this->json([
            'success' => true,
            'channels' => $channelsData
        ]);
    }
}