<?php

declare(strict_types=1);

namespace App\Entity\MaterializedView;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'channel_stats_view')]
class ChannelStatsView
{
    #[ORM\Id]
    #[ORM\Column]
    private int $channelId;

    #[ORM\Column(length: 255)]
    private string $channelName;

    #[ORM\Column]
    private int $videosCount = 0;

    #[ORM\Column]
    private int $totalViews = 0;

    #[ORM\Column]
    private int $subscribersCount = 0;

    #[ORM\Column]
    private int $totalComments = 0;

    #[ORM\Column]
    private int $totalLikes = 0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $avgViewsPerVideo = '0.00';

    #[ORM\Column]
    private \DateTimeImmutable $lastUpdated;

    public function getChannelId(): int
    {
        return $this->channelId;
    }

    public function getChannelName(): string
    {
        return $this->channelName;
    }

    public function getVideosCount(): int
    {
        return $this->videosCount;
    }

    public function getTotalViews(): int
    {
        return $this->totalViews;
    }

    public function getSubscribersCount(): int
    {
        return $this->subscribersCount;
    }

    public function getTotalComments(): int
    {
        return $this->totalComments;
    }

    public function getTotalLikes(): int
    {
        return $this->totalLikes;
    }

    public function getAvgViewsPerVideo(): string
    {
        return $this->avgViewsPerVideo;
    }

    public function getLastUpdated(): \DateTimeImmutable
    {
        return $this->lastUpdated;
    }
}