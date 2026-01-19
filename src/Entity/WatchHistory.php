<?php

namespace App\Entity;

use App\Repository\WatchHistoryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WatchHistoryRepository::class)]
#[ORM\Table(name: 'watch_history')]
#[ORM\UniqueConstraint(name: 'unique_user_video', columns: ['user_id', 'video_id'])]
#[ORM\Index(name: 'idx_user_watched', columns: ['user_id', 'watched_at'])]
class WatchHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Video::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Video $video = null;

    #[ORM\Column]
    private int $watchedSeconds = 0;

    #[ORM\Column]
    private int $watchProgress = 0;

    #[ORM\Column]
    private ?\DateTimeImmutable $watchedAt = null;

    public function __construct()
    {
        $this->watchedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getVideo(): ?Video
    {
        return $this->video;
    }

    public function setVideo(?Video $video): static
    {
        $this->video = $video;
        return $this;
    }

    public function getWatchedSeconds(): int
    {
        return $this->watchedSeconds;
    }

    public function setWatchedSeconds(int $watchedSeconds): static
    {
        $this->watchedSeconds = $watchedSeconds;
        return $this;
    }

    public function getWatchProgress(): int
    {
        return $this->watchProgress;
    }

    public function setWatchProgress(int $watchProgress): static
    {
        $this->watchProgress = $watchProgress;
        return $this;
    }

    public function getWatchedAt(): ?\DateTimeImmutable
    {
        return $this->watchedAt;
    }

    public function setWatchedAt(\DateTimeImmutable $watchedAt): static
    {
        $this->watchedAt = $watchedAt;
        return $this;
    }

    public function updateWatchedAt(): static
    {
        $this->watchedAt = new \DateTimeImmutable();
        return $this;
    }
}
