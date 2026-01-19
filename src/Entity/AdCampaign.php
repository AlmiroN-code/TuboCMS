<?php

namespace App\Entity;

use App\Repository\AdCampaignRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AdCampaignRepository::class)]
#[ORM\Table(name: 'ad_campaign')]
#[ORM\Index(columns: ['status'], name: 'idx_ad_campaign_status')]
class AdCampaign
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_ARCHIVED = 'archived';

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

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $totalBudget = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $dailyBudget = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private ?string $spentAmount = '0.00';

    #[ORM\Column]
    private ?int $totalImpressions = 0;

    #[ORM\Column]
    private ?int $totalClicks = 0;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $createdBy = null;

    #[ORM\OneToMany(targetEntity: Ad::class, mappedBy: 'campaign')]
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

    public function getTotalBudget(): ?string
    {
        return $this->totalBudget;
    }

    public function setTotalBudget(?string $totalBudget): static
    {
        $this->totalBudget = $totalBudget;
        return $this;
    }

    public function getDailyBudget(): ?string
    {
        return $this->dailyBudget;
    }

    public function setDailyBudget(?string $dailyBudget): static
    {
        $this->dailyBudget = $dailyBudget;
        return $this;
    }

    public function getSpentAmount(): ?string
    {
        return $this->spentAmount;
    }

    public function setSpentAmount(string $spentAmount): static
    {
        $this->spentAmount = $spentAmount;
        return $this;
    }

    public function getTotalImpressions(): ?int
    {
        return $this->totalImpressions;
    }

    public function setTotalImpressions(int $totalImpressions): static
    {
        $this->totalImpressions = $totalImpressions;
        return $this;
    }

    public function getTotalClicks(): ?int
    {
        return $this->totalClicks;
    }

    public function setTotalClicks(int $totalClicks): static
    {
        $this->totalClicks = $totalClicks;
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

    public function addAd(Ad $ad): static
    {
        if (!$this->ads->contains($ad)) {
            $this->ads->add($ad);
            $ad->setCampaign($this);
        }
        return $this;
    }

    public function removeAd(Ad $ad): static
    {
        if ($this->ads->removeElement($ad)) {
            if ($ad->getCampaign() === $this) {
                $ad->setCampaign(null);
            }
        }
        return $this;
    }

    public function getCtr(): float
    {
        if ($this->totalImpressions === 0) {
            return 0.0;
        }
        return round(($this->totalClicks / $this->totalImpressions) * 100, 2);
    }

    public function getBudgetUsagePercent(): float
    {
        if (!$this->totalBudget || (float)$this->totalBudget === 0.0) {
            return 0.0;
        }
        return round(((float)$this->spentAmount / (float)$this->totalBudget) * 100, 1);
    }

    public static function getStatuses(): array
    {
        return [
            'Черновик' => self::STATUS_DRAFT,
            'Активна' => self::STATUS_ACTIVE,
            'Приостановлена' => self::STATUS_PAUSED,
            'Завершена' => self::STATUS_COMPLETED,
            'В архиве' => self::STATUS_ARCHIVED,
        ];
    }
}
