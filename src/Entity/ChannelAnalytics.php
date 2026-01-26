<?php

namespace App\Entity;

use App\Repository\ChannelAnalyticsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ChannelAnalyticsRepository::class)]
#[ORM\Table(name: 'channel_analytics')]
#[ORM\Index(columns: ['channel_id', 'date'], name: 'idx_channel_analytics_date')]
class ChannelAnalytics
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Channel::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Channel $channel = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $views = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $uniqueViews = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $newSubscribers = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $unsubscribers = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $likes = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $comments = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $shares = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, options: ['default' => '0.00'])]
    private string $revenue = '0.00';

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $watchTimeMinutes = 0;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $demographicData = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $trafficSources = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getChannel(): ?Channel
    {
        return $this->channel;
    }

    public function setChannel(?Channel $channel): static
    {
        $this->channel = $channel;
        return $this;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): static
    {
        $this->date = $date;
        return $this;
    }

    public function getViews(): int
    {
        return $this->views;
    }

    public function setViews(int $views): static
    {
        $this->views = $views;
        return $this;
    }

    public function getUniqueViews(): int
    {
        return $this->uniqueViews;
    }

    public function setUniqueViews(int $uniqueViews): static
    {
        $this->uniqueViews = $uniqueViews;
        return $this;
    }

    public function getNewSubscribers(): int
    {
        return $this->newSubscribers;
    }

    public function setNewSubscribers(int $newSubscribers): static
    {
        $this->newSubscribers = $newSubscribers;
        return $this;
    }

    public function getUnsubscribers(): int
    {
        return $this->unsubscribers;
    }

    public function setUnsubscribers(int $unsubscribers): static
    {
        $this->unsubscribers = $unsubscribers;
        return $this;
    }

    public function getLikes(): int
    {
        return $this->likes;
    }

    public function setLikes(int $likes): static
    {
        $this->likes = $likes;
        return $this;
    }

    public function getComments(): int
    {
        return $this->comments;
    }

    public function setComments(int $comments): static
    {
        $this->comments = $comments;
        return $this;
    }

    public function getShares(): int
    {
        return $this->shares;
    }

    public function setShares(int $shares): static
    {
        $this->shares = $shares;
        return $this;
    }

    public function getRevenue(): string
    {
        return $this->revenue;
    }

    public function setRevenue(string $revenue): static
    {
        $this->revenue = $revenue;
        return $this;
    }

    public function getWatchTimeMinutes(): int
    {
        return $this->watchTimeMinutes;
    }

    public function setWatchTimeMinutes(int $watchTimeMinutes): static
    {
        $this->watchTimeMinutes = $watchTimeMinutes;
        return $this;
    }

    public function getDemographicData(): ?array
    {
        return $this->demographicData;
    }

    public function setDemographicData(?array $demographicData): static
    {
        $this->demographicData = $demographicData;
        return $this;
    }

    public function getTrafficSources(): ?array
    {
        return $this->trafficSources;
    }

    public function setTrafficSources(?array $trafficSources): static
    {
        $this->trafficSources = $trafficSources;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getNetSubscribers(): int
    {
        return $this->newSubscribers - $this->unsubscribers;
    }

    public function getEngagementRate(): float
    {
        if ($this->views === 0) {
            return 0.0;
        }
        
        $engagements = $this->likes + $this->comments + $this->shares;
        return ($engagements / $this->views) * 100;
    }

    public function getAverageWatchTime(): float
    {
        if ($this->views === 0) {
            return 0.0;
        }
        
        return $this->watchTimeMinutes / $this->views;
    }
}