<?php

namespace App\Entity;

use App\Repository\ModelLikeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ModelLikeRepository::class)]
#[ORM\Table(name: 'model_like')]
#[ORM\UniqueConstraint(name: 'unique_model_like', columns: ['user_id', 'model_id'])]
class ModelLike
{
    public const TYPE_LIKE = 'like';
    public const TYPE_DISLIKE = 'dislike';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: ModelProfile::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ModelProfile $model = null;

    #[ORM\Column(length: 10)]
    private ?string $type = self::TYPE_LIKE;

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

    public function getModel(): ?ModelProfile
    {
        return $this->model;
    }

    public function setModel(?ModelProfile $model): static
    {
        $this->model = $model;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function isLike(): bool
    {
        return $this->type === self::TYPE_LIKE;
    }

    public function isDislike(): bool
    {
        return $this->type === self::TYPE_DISLIKE;
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
