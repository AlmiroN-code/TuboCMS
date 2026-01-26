<?php

namespace App\Service;

use App\Entity\Channel;
use App\Entity\ChannelSubscription;
use App\Entity\User;
use App\Repository\ChannelRepository;
use App\Repository\ChannelSubscriptionRepository;
use App\Repository\VideoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

class ChannelService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ChannelRepository $channelRepository,
        private ChannelSubscriptionRepository $subscriptionRepository,
        private VideoRepository $videoRepository,
        private SluggerInterface $slugger
    ) {}

    /**
     * Создать новый канал
     */
    public function createChannel(User $owner, string $name, string $description = null, string $type = Channel::TYPE_PERSONAL): Channel
    {
        $channel = new Channel();
        $channel->setName($name);
        $channel->setDescription($description);
        $channel->setType($type);
        $channel->setOwner($owner);
        $channel->generateSlug($this->slugger);

        // Проверка уникальности slug
        $originalSlug = $channel->getSlug();
        $counter = 1;
        while (!$this->channelRepository->isSlugUnique($channel->getSlug())) {
            $channel->setSlug($originalSlug . '-' . $counter);
            $counter++;
        }

        $this->entityManager->persist($channel);
        $this->entityManager->flush();

        return $channel;
    }

    /**
     * Подписаться на канал
     */
    public function subscribeToChannel(User $user, Channel $channel): ChannelSubscription
    {
        // Проверить, не подписан ли уже
        $existingSubscription = $this->subscriptionRepository->findSubscription($user, $channel);
        if ($existingSubscription) {
            throw new \InvalidArgumentException('Пользователь уже подписан на этот канал');
        }

        $subscription = new ChannelSubscription();
        $subscription->setUser($user);
        $subscription->setChannel($channel);

        $this->entityManager->persist($subscription);

        // Обновить счетчик подписчиков
        $channel->setSubscribersCount($channel->getSubscribersCount() + 1);

        $this->entityManager->flush();

        return $subscription;
    }

    /**
     * Отписаться от канала
     */
    public function unsubscribeFromChannel(User $user, Channel $channel): void
    {
        $subscription = $this->subscriptionRepository->findSubscription($user, $channel);
        if (!$subscription) {
            throw new \InvalidArgumentException('Пользователь не подписан на этот канал');
        }

        $this->entityManager->remove($subscription);

        // Обновить счетчик подписчиков
        $channel->setSubscribersCount(max(0, $channel->getSubscribersCount() - 1));

        $this->entityManager->flush();
    }

    /**
     * Проверить подписку пользователя на канал
     */
    public function isUserSubscribed(User $user, Channel $channel): bool
    {
        return $this->subscriptionRepository->isSubscribed($user, $channel);
    }

    /**
     * Обновить статистику канала
     */
    public function updateChannelStats(Channel $channel): void
    {
        // Подсчитать количество видео
        $videosCount = $this->videoRepository->countByChannel($channel);
        $channel->setVideosCount($videosCount);

        // Подсчитать общее количество просмотров
        $totalViews = $this->videoRepository->createQueryBuilder('v')
            ->select('SUM(v.viewsCount)')
            ->where('v.channel = :channel')
            ->andWhere('v.status = :status')
            ->setParameter('channel', $channel)
            ->setParameter('status', 'published')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        $channel->setTotalViews($totalViews);

        // Подсчитать количество подписчиков
        $subscribersCount = $this->subscriptionRepository->countChannelSubscribers($channel);
        $channel->setSubscribersCount($subscribersCount);

        $this->entityManager->flush();
    }

    /**
     * Получить рекомендуемые каналы для пользователя
     */
    public function getRecommendedChannels(User $user = null, int $limit = 10): array
    {
        // Простая логика рекомендаций - популярные каналы
        return $this->channelRepository->findPopular($limit);
    }

    /**
     * Поиск каналов
     */
    public function searchChannels(string $query, array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $searchFilters = array_merge($filters, ['search' => $query]);
        return $this->channelRepository->findWithFilters($searchFilters, $limit, $offset);
    }

    /**
     * Получить каналы пользователя (где он владелец или участник)
     */
    public function getUserChannels(User $user): array
    {
        return $this->channelRepository->findByOwner($user);
    }

    /**
     * Проверить права пользователя на канал
     */
    public function canUserManageChannel(User $user, Channel $channel): bool
    {
        // Владелец может управлять каналом
        if ($channel->getOwner() === $user) {
            return true;
        }

        // Админы могут управлять любыми каналами
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return true;
        }

        return false;
    }

    /**
     * Получить статистику каналов
     */
    public function getChannelsStats(): array
    {
        $totalChannels = $this->channelRepository->countActive();
        $totalStudios = $this->channelRepository->countStudios();
        $verifiedChannels = $this->channelRepository->countWithFilters(['verified' => true]);

        return [
            'total' => $totalChannels,
            'studios' => $totalStudios,
            'verified' => $verifiedChannels,
            'personal' => $totalChannels - $totalStudios,
        ];
    }

    /**
     * Обновить slug канала
     */
    public function updateChannelSlug(Channel $channel, string $newName): void
    {
        $newSlug = $this->slugger->slug($newName)->lower();
        
        // Проверить уникальность
        $originalSlug = $newSlug;
        $counter = 1;
        while (!$this->channelRepository->isSlugUnique($newSlug, $channel->getId())) {
            $newSlug = $originalSlug . '-' . $counter;
            $counter++;
        }

        $channel->setSlug($newSlug);
    }
}