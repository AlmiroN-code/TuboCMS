<?php

namespace App\Entity;

use App\Repository\ChannelSubscriptionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ChannelSubscriptionRepository::class)]
#[ORM\Table(name: 'channel_subscriptions')]
#[ORM\UniqueConstraint(name: 'unique_channel_subscription', columns: ['user_id', 'channel_id'])]
#[ORM\Index(columns: ['user_id'], name: 'idx_subscription_user')]
#[ORM\Index(columns: ['channel_id'], name: 'idx_subscription_channel')]
class ChannelSubscription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Channel::class, inversedBy: 'subscriptions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Channel $channel = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isPaid = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $paidUntil = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $notificationsEnabled = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $subscribedAt = null;

    public function __construct()
    {
        $this->subscribedAt = new \DateTime();
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

    public function getChannel(): ?Channel
    {
        return $this->channel;
    }

    public function setChannel(?Channel $channel): static
    {
        $this->channel = $channel;
        return $this;
    }

    public function isPaid(): bool
    {
        return $this->isPaid;
    }

    public function setIsPaid(bool $isPaid): static
    {
        $this->isPaid = $isPaid;
        return $this;
    }

    public function getPaidUntil(): ?\DateTimeInterface
    {
        return $this->paidUntil;
    }

    public function setPaidUntil(?\DateTimeInterface $paidUntil): static
    {
        $this->paidUntil = $paidUntil;
        return $this;
    }

    public function isNotificationsEnabled(): bool
    {
        return $this->notificationsEnabled;
    }

    public function setNotificationsEnabled(bool $notificationsEnabled): static
    {
        $this->notificationsEnabled = $notificationsEnabled;
        return $this;
    }

    public function getSubscribedAt(): ?\DateTimeInterface
    {
        return $this->subscribedAt;
    }

    public function setSubscribedAt(\DateTimeInterface $subscribedAt): static
    {
        $this->subscribedAt = $subscribedAt;
        return $this;
    }

    public function isActive(): bool
    {
        if (!$this->isPaid) {
            return true; // Бесплатная подписка всегда активна
        }

        return $this->paidUntil && $this->paidUntil > new \DateTime();
    }
}