<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\LiveStreamRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LiveStreamRepository::class)]
#[ORM\Table(name: 'live_stream')]
#[ORM\Index(columns: ['status'], name: 'idx_status')]
#[ORM\Index(columns: ['started_at'], name: 'idx_started_at')]
class LiveStream
{
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_LIVE = 'live';
    public const STATUS_ENDED = 'ended';
    public const STATUS_CANCELLED = 'cancelled';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 200)]
    #[Assert\NotBlank(message: 'Название обязательно')]
    #[Assert\Length(max: 200, maxMessage: 'Название не может быть длиннее {{ limit }} символов')]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 250, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(length: 64, unique: true)]
    private ?string $streamKey = null;

    #[ORM\Column(length: 20)]
    private ?string $status = self::STATUS_SCHEDULED;

    #[ORM\Column]
    private ?int $viewersCount = 0;

    #[ORM\Column]
    private ?int $peakViewersCount = 0;

    #[ORM\Column]
    private ?int $totalViews = 0;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $thumbnail = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $scheduledAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $endedAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'liveStreams')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $streamer = null;

    #[ORM\ManyToOne(inversedBy: 'liveStreams')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Channel $channel = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = [];

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->streamKey = bin2hex(random_bytes(32));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;
        return $this;
    }

    public function getStreamKey(): ?string
    {
        return $this->streamKey;
    }

    public function regenerateStreamKey(): static
    {
        $this->streamKey = bin2hex(random_bytes(32));
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        $this->updatedAt = new \DateTimeImmutable();
        
        if ($status === self::STATUS_LIVE && !$this->startedAt) {
            $this->startedAt = new \DateTimeImmutable();
        }
        
        if ($status === self::STATUS_ENDED && !$this->endedAt) {
            $this->endedAt = new \DateTimeImmutable();
        }
        
        return $this;
    }

    public function isLive(): bool
    {
        return $this->status === self::STATUS_LIVE;
    }

    public function getViewersCount(): ?int
    {
        return $this->viewersCount;
    }

    public function setViewersCount(int $viewersCount): static
    {
        $this->viewersCount = $viewersCount;
        
        if ($viewersCount > $this->peakViewersCount) {
            $this->peakViewersCount = $viewersCount;
        }
        
        return $this;
    }

    public function incrementViewersCount(): static
    {
        $this->viewersCount++;
        
        if ($this->viewersCount > $this->peakViewersCount) {
            $this->peakViewersCount = $this->viewersCount;
        }
        
        return $this;
    }

    public function decrementViewersCount(): static
    {
        if ($this->viewersCount > 0) {
            $this->viewersCount--;
        }
        
        return $this;
    }

    public function getPeakViewersCount(): ?int
    {
        return $this->peakViewersCount;
    }

    public function getTotalViews(): ?int
    {
        return $this->totalViews;
    }

    public function incrementTotalViews(): static
    {
        $this->totalViews++;
        return $this;
    }

    public function getThumbnail(): ?string
    {
        return $this->thumbnail;
    }

    public function setThumbnail(?string $thumbnail): static
    {
        $this->thumbnail = $thumbnail;
        return $this;
    }

    public function getScheduledAt(): ?\DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function setScheduledAt(?\DateTimeImmutable $scheduledAt): static
    {
        $this->scheduledAt = $scheduledAt;
        return $this;
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getEndedAt(): ?\DateTimeImmutable
    {
        return $this->endedAt;
    }

    public function getDuration(): ?int
    {
        if (!$this->startedAt) {
            return null;
        }
        
        $endTime = $this->endedAt ?? new \DateTimeImmutable();
        return $endTime->getTimestamp() - $this->startedAt->getTimestamp();
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getStreamer(): ?User
    {
        return $this->streamer;
    }

    public function setStreamer(?User $streamer): static
    {
        $this->streamer = $streamer;
        return $this;
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

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata;
        return $this;
    }
}
