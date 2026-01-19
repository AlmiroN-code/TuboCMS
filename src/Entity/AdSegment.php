<?php

namespace App\Entity;

use App\Repository\AdSegmentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: AdSegmentRepository::class)]
#[ORM\Table(name: 'ad_segment')]
#[UniqueEntity(fields: ['slug'], message: 'Сегмент с таким slug уже существует')]
class AdSegment
{
    public const TYPE_BEHAVIOR = 'behavior';
    public const TYPE_DEMOGRAPHIC = 'demographic';
    public const TYPE_INTEREST = 'interest';
    public const TYPE_CUSTOM = 'custom';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\Column(length: 100, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 30)]
    private ?string $type = self::TYPE_CUSTOM;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $rules = [];

    #[ORM\Column]
    private ?bool $isActive = true;

    #[ORM\Column]
    private ?int $usersCount = 0;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToMany(targetEntity: Ad::class, mappedBy: 'segments')]
    private Collection $ads;

    public function __construct()
    {
        $this->ads = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
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

    public function getRules(): ?array
    {
        return $this->rules;
    }

    public function setRules(?array $rules): static
    {
        $this->rules = $rules;
        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getUsersCount(): ?int
    {
        return $this->usersCount;
    }

    public function setUsersCount(int $usersCount): static
    {
        $this->usersCount = $usersCount;
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

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getAds(): Collection
    {
        return $this->ads;
    }

    public static function getTypes(): array
    {
        return [
            'Поведение' => self::TYPE_BEHAVIOR,
            'Демография' => self::TYPE_DEMOGRAPHIC,
            'Интересы' => self::TYPE_INTEREST,
            'Пользовательский' => self::TYPE_CUSTOM,
        ];
    }
}
