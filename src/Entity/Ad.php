<?php

namespace App\Entity;

use App\Repository\AdRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AdRepository::class)]
#[ORM\Table(name: 'ad')]
#[ORM\Index(columns: ['status', 'start_date', 'end_date'], name: 'idx_ad_status_dates')]
#[ORM\Index(columns: ['is_active'], name: 'idx_ad_active')]
class Ad
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REJECTED = 'rejected';

    public const FORMAT_IMAGE = 'image';
    public const FORMAT_HTML = 'html';
    public const FORMAT_VIDEO = 'video';
    public const FORMAT_VAST = 'vast';
    public const FORMAT_SCRIPT = 'script';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 200)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 30)]
    private ?string $format = self::FORMAT_IMAGE;

    #[ORM\Column(length: 20)]
    private ?string $status = self::STATUS_DRAFT;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $imageUrl = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $videoUrl = null;

    #[ORM\Column(length: 1000, nullable: true)]
    private ?string $vastUrl = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $htmlContent = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $scriptCode = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $clickUrl = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $altText = null;

    #[ORM\Column]
    private ?bool $isActive = true;

    #[ORM\Column]
    private ?bool $openInNewTab = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column]
    private ?int $priority = 0;

    #[ORM\Column]
    private ?int $weight = 100;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $budget = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4, nullable: true)]
    private ?string $cpm = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4, nullable: true)]
    private ?string $cpc = null;

    #[ORM\Column(nullable: true)]
    private ?int $impressionLimit = null;

    #[ORM\Column(nullable: true)]
    private ?int $clickLimit = null;

    #[ORM\Column(nullable: true)]
    private ?int $dailyImpressionLimit = null;

    #[ORM\Column(nullable: true)]
    private ?int $dailyClickLimit = null;

    #[ORM\Column]
    private ?int $impressionsCount = 0;

    #[ORM\Column]
    private ?int $clicksCount = 0;

    #[ORM\Column]
    private ?int $uniqueImpressionsCount = 0;

    #[ORM\Column]
    private ?int $uniqueClicksCount = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $spentAmount = '0.00';

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: AdPlacement::class, inversedBy: 'ads')]
    #[ORM\JoinColumn(nullable: false)]
    private ?AdPlacement $placement = null;

    #[ORM\ManyToOne(targetEntity: AdCampaign::class, inversedBy: 'ads')]
    private ?AdCampaign $campaign = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $createdBy = null;

    #[ORM\OneToMany(targetEntity: AdStatistic::class, mappedBy: 'ad', cascade: ['remove'])]
    private Collection $statistics;

    #[ORM\ManyToMany(targetEntity: AdSegment::class, inversedBy: 'ads')]
    #[ORM\JoinTable(name: 'ad_segment_relation')]
    private Collection $segments;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $geoTargeting = [];

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $timeTargeting = [];

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $deviceTargeting = [];

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $categoryTargeting = [];

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $abTestVariant = null;

    #[ORM\ManyToOne(targetEntity: AdAbTest::class, inversedBy: 'ads')]
    private ?AdAbTest $abTest = null;

    public function __construct()
    {
        $this->statistics = new ArrayCollection();
        $this->segments = new ArrayCollection();
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

    public function getFormat(): ?string
    {
        return $this->format;
    }

    public function setFormat(string $format): static
    {
        $this->format = $format;
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

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(?string $imageUrl): static
    {
        $this->imageUrl = $imageUrl;
        return $this;
    }

    public function getVideoUrl(): ?string
    {
        return $this->videoUrl;
    }

    public function setVideoUrl(?string $videoUrl): static
    {
        $this->videoUrl = $videoUrl;
        return $this;
    }

    public function getVastUrl(): ?string
    {
        return $this->vastUrl;
    }

    public function setVastUrl(?string $vastUrl): static
    {
        $this->vastUrl = $vastUrl;
        return $this;
    }

    public function getHtmlContent(): ?string
    {
        return $this->htmlContent;
    }

    public function setHtmlContent(?string $htmlContent): static
    {
        $this->htmlContent = $htmlContent;
        return $this;
    }

    public function getScriptCode(): ?string
    {
        return $this->scriptCode;
    }

    public function setScriptCode(?string $scriptCode): static
    {
        $this->scriptCode = $scriptCode;
        return $this;
    }

    public function getClickUrl(): ?string
    {
        return $this->clickUrl;
    }

    public function setClickUrl(?string $clickUrl): static
    {
        $this->clickUrl = $clickUrl;
        return $this;
    }

    public function getAltText(): ?string
    {
        return $this->altText;
    }

    public function setAltText(?string $altText): static
    {
        $this->altText = $altText;
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

    public function isOpenInNewTab(): ?bool
    {
        return $this->openInNewTab;
    }

    public function setOpenInNewTab(bool $openInNewTab): static
    {
        $this->openInNewTab = $openInNewTab;
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

    public function getPriority(): ?int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): static
    {
        $this->priority = $priority;
        return $this;
    }

    public function getWeight(): ?int
    {
        return $this->weight;
    }

    public function setWeight(int $weight): static
    {
        $this->weight = $weight;
        return $this;
    }

    public function getBudget(): ?string
    {
        return $this->budget;
    }

    public function setBudget(?string $budget): static
    {
        $this->budget = $budget;
        return $this;
    }

    public function getCpm(): ?string
    {
        return $this->cpm;
    }

    public function setCpm(?string $cpm): static
    {
        $this->cpm = $cpm;
        return $this;
    }

    public function getCpc(): ?string
    {
        return $this->cpc;
    }

    public function setCpc(?string $cpc): static
    {
        $this->cpc = $cpc;
        return $this;
    }

    public function getImpressionLimit(): ?int
    {
        return $this->impressionLimit;
    }

    public function setImpressionLimit(?int $impressionLimit): static
    {
        $this->impressionLimit = $impressionLimit;
        return $this;
    }

    public function getClickLimit(): ?int
    {
        return $this->clickLimit;
    }

    public function setClickLimit(?int $clickLimit): static
    {
        $this->clickLimit = $clickLimit;
        return $this;
    }

    public function getDailyImpressionLimit(): ?int
    {
        return $this->dailyImpressionLimit;
    }

    public function setDailyImpressionLimit(?int $dailyImpressionLimit): static
    {
        $this->dailyImpressionLimit = $dailyImpressionLimit;
        return $this;
    }

    public function getDailyClickLimit(): ?int
    {
        return $this->dailyClickLimit;
    }

    public function setDailyClickLimit(?int $dailyClickLimit): static
    {
        $this->dailyClickLimit = $dailyClickLimit;
        return $this;
    }

    public function getImpressionsCount(): ?int
    {
        return $this->impressionsCount;
    }

    public function setImpressionsCount(int $impressionsCount): static
    {
        $this->impressionsCount = $impressionsCount;
        return $this;
    }

    public function incrementImpressions(): static
    {
        $this->impressionsCount++;
        return $this;
    }

    public function getClicksCount(): ?int
    {
        return $this->clicksCount;
    }

    public function setClicksCount(int $clicksCount): static
    {
        $this->clicksCount = $clicksCount;
        return $this;
    }

    public function incrementClicks(): static
    {
        $this->clicksCount++;
        return $this;
    }

    public function getUniqueImpressionsCount(): ?int
    {
        return $this->uniqueImpressionsCount;
    }

    public function setUniqueImpressionsCount(int $uniqueImpressionsCount): static
    {
        $this->uniqueImpressionsCount = $uniqueImpressionsCount;
        return $this;
    }

    public function getUniqueClicksCount(): ?int
    {
        return $this->uniqueClicksCount;
    }

    public function setUniqueClicksCount(int $uniqueClicksCount): static
    {
        $this->uniqueClicksCount = $uniqueClicksCount;
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

    public function getPlacement(): ?AdPlacement
    {
        return $this->placement;
    }

    public function setPlacement(?AdPlacement $placement): static
    {
        $this->placement = $placement;
        return $this;
    }

    public function getCampaign(): ?AdCampaign
    {
        return $this->campaign;
    }

    public function setCampaign(?AdCampaign $campaign): static
    {
        $this->campaign = $campaign;
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

    public function getStatistics(): Collection
    {
        return $this->statistics;
    }

    public function getSegments(): Collection
    {
        return $this->segments;
    }

    public function addSegment(AdSegment $segment): static
    {
        if (!$this->segments->contains($segment)) {
            $this->segments->add($segment);
        }
        return $this;
    }

    public function removeSegment(AdSegment $segment): static
    {
        $this->segments->removeElement($segment);
        return $this;
    }

    public function getGeoTargeting(): ?array
    {
        return $this->geoTargeting;
    }

    public function setGeoTargeting(?array $geoTargeting): static
    {
        $this->geoTargeting = $geoTargeting;
        return $this;
    }

    public function getTimeTargeting(): ?array
    {
        return $this->timeTargeting;
    }

    public function setTimeTargeting(?array $timeTargeting): static
    {
        $this->timeTargeting = $timeTargeting;
        return $this;
    }

    public function getDeviceTargeting(): ?array
    {
        return $this->deviceTargeting;
    }

    public function setDeviceTargeting(?array $deviceTargeting): static
    {
        $this->deviceTargeting = $deviceTargeting;
        return $this;
    }

    public function getCategoryTargeting(): ?array
    {
        return $this->categoryTargeting;
    }

    public function setCategoryTargeting(?array $categoryTargeting): static
    {
        $this->categoryTargeting = $categoryTargeting;
        return $this;
    }

    public function getAbTestVariant(): ?string
    {
        return $this->abTestVariant;
    }

    public function setAbTestVariant(?string $abTestVariant): static
    {
        $this->abTestVariant = $abTestVariant;
        return $this;
    }

    public function getAbTest(): ?AdAbTest
    {
        return $this->abTest;
    }

    public function setAbTest(?AdAbTest $abTest): static
    {
        $this->abTest = $abTest;
        return $this;
    }

    public function getCtr(): float
    {
        if ($this->impressionsCount === 0) {
            return 0.0;
        }
        return round(($this->clicksCount / $this->impressionsCount) * 100, 2);
    }

    public function isRunning(): bool
    {
        if (!$this->isActive || $this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        $now = new \DateTime();
        if ($this->startDate && $now < $this->startDate) {
            return false;
        }
        if ($this->endDate && $now > $this->endDate) {
            return false;
        }

        return true;
    }

    public static function getFormats(): array
    {
        return [
            'Изображение' => self::FORMAT_IMAGE,
            'HTML' => self::FORMAT_HTML,
            'Видео' => self::FORMAT_VIDEO,
            'VAST' => self::FORMAT_VAST,
            'Скрипт' => self::FORMAT_SCRIPT,
        ];
    }

    public static function getStatuses(): array
    {
        return [
            'Черновик' => self::STATUS_DRAFT,
            'На модерации' => self::STATUS_PENDING,
            'Активна' => self::STATUS_ACTIVE,
            'Приостановлена' => self::STATUS_PAUSED,
            'Завершена' => self::STATUS_COMPLETED,
            'Отклонена' => self::STATUS_REJECTED,
        ];
    }
}
