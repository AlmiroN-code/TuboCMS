<?php

namespace App\Entity;

use App\Repository\AdAbTestRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AdAbTestRepository::class)]
#[ORM\Table(name: 'ad_ab_test')]
class AdAbTest
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_RUNNING = 'running';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_COMPLETED = 'completed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 200)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 20)]
    private ?string $status = self::STATUS_DRAFT;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column]
    private ?int $trafficSplitA = 50;

    #[ORM\Column]
    private ?int $trafficSplitB = 50;

    #[ORM\Column(length: 50)]
    private ?string $winnerMetric = 'ctr';

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $winner = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4, nullable: true)]
    private ?string $statisticalSignificance = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $createdBy = null;

    #[ORM\OneToMany(targetEntity: Ad::class, mappedBy: 'abTest')]
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeInterface $startDate): static
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeInterface $endDate): static
    {
        $this->endDate = $endDate;
        return $this;
    }

    public function getTrafficSplitA(): ?int
    {
        return $this->trafficSplitA;
    }

    public function setTrafficSplitA(int $trafficSplitA): static
    {
        $this->trafficSplitA = $trafficSplitA;
        return $this;
    }

    public function getTrafficSplitB(): ?int
    {
        return $this->trafficSplitB;
    }

    public function setTrafficSplitB(int $trafficSplitB): static
    {
        $this->trafficSplitB = $trafficSplitB;
        return $this;
    }

    public function getWinnerMetric(): ?string
    {
        return $this->winnerMetric;
    }

    public function setWinnerMetric(string $winnerMetric): static
    {
        $this->winnerMetric = $winnerMetric;
        return $this;
    }

    public function getWinner(): ?string
    {
        return $this->winner;
    }

    public function setWinner(?string $winner): static
    {
        $this->winner = $winner;
        return $this;
    }

    public function getStatisticalSignificance(): ?string
    {
        return $this->statisticalSignificance;
    }

    public function setStatisticalSignificance(?string $statisticalSignificance): static
    {
        $this->statisticalSignificance = $statisticalSignificance;
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

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getAds(): Collection
    {
        return $this->ads;
    }

    public function getVariantA(): ?Ad
    {
        foreach ($this->ads as $ad) {
            if ($ad->getAbTestVariant() === 'A') {
                return $ad;
            }
        }
        return null;
    }

    public function getVariantB(): ?Ad
    {
        foreach ($this->ads as $ad) {
            if ($ad->getAbTestVariant() === 'B') {
                return $ad;
            }
        }
        return null;
    }

    public static function getStatuses(): array
    {
        return [
            'Черновик' => self::STATUS_DRAFT,
            'Запущен' => self::STATUS_RUNNING,
            'Приостановлен' => self::STATUS_PAUSED,
            'Завершён' => self::STATUS_COMPLETED,
        ];
    }

    public static function getWinnerMetrics(): array
    {
        return [
            'CTR (кликабельность)' => 'ctr',
            'Конверсии' => 'conversions',
            'Доход' => 'revenue',
            'Уникальные клики' => 'unique_clicks',
        ];
    }
}
