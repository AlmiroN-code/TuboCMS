<?php

namespace App\Entity;

use App\Repository\PlaylistSubscriptionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlaylistSubscriptionRepository::class)]
#[ORM\Table(name: 'playlist_subscriptions')]
#[ORM\UniqueConstraint(name: 'unique_playlist_subscription', columns: ['user_id', 'playlist_id'])]
#[ORM\Index(columns: ['user_id'], name: 'idx_playlist_subscription_user')]
#[ORM\Index(columns: ['playlist_id'], name: 'idx_playlist_subscription_playlist')]
class PlaylistSubscription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: ChannelPlaylist::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ChannelPlaylist $playlist = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
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

    public function getPlaylist(): ?ChannelPlaylist
    {
        return $this->playlist;
    }

    public function setPlaylist(?ChannelPlaylist $playlist): static
    {
        $this->playlist = $playlist;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}
