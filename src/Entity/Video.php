<?php

namespace App\Entity;

use App\Repository\VideoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VideoRepository::class)]
#[ORM\Table(name: 'video')]
#[ORM\Index(columns: ['status', 'created_at'], name: 'idx_status_created')]
#[ORM\Index(columns: ['slug'], name: 'idx_slug')]
#[ORM\Index(columns: ['views_count'], name: 'idx_views')]
class Video
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_PRIVATE = 'private';
    public const STATUS_REJECTED = 'rejected';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 200)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 250, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $tempVideoFile = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $convertedFiles = [];

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $preview = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $poster = null;

    #[ORM\Column]
    private ?int $duration = 0;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $resolution = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $format = null;

    #[ORM\Column(length: 20)]
    private ?string $status = self::STATUS_DRAFT;

    #[ORM\Column]
    private ?bool $isFeatured = false;

    #[ORM\Column]
    private ?int $viewsCount = 0;

    #[ORM\Column]
    private ?int $impressionsCount = 0;

    #[ORM\Column]
    private ?int $commentsCount = 0;

    #[ORM\Column]
    private ?int $likesCount = 0;

    #[ORM\Column]
    private ?int $dislikesCount = 0;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $metaDescription = null;

    #[ORM\Column(length: 20)]
    private ?string $processingStatus = 'pending';

    #[ORM\Column]
    private ?int $processingProgress = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $processingError = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'videos')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\ManyToMany(targetEntity: Category::class, inversedBy: 'videos')]
    #[ORM\JoinTable(name: 'video_category')]
    private Collection $categories;

    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'videos')]
    #[ORM\JoinTable(name: 'video_tag')]
    private Collection $tags;

    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'video', cascade: ['remove'])]
    private Collection $comments;

    #[ORM\OneToMany(targetEntity: VideoFile::class, mappedBy: 'video', cascade: ['persist', 'remove'])]
    private Collection $encodedFiles;

    #[ORM\ManyToMany(targetEntity: ModelProfile::class, inversedBy: 'videos')]
    #[ORM\JoinTable(name: 'video_model')]
    private Collection $performers;

    #[ORM\ManyToOne(targetEntity: Season::class, inversedBy: 'episodes')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Season $season = null;

    #[ORM\Column(nullable: true)]
    private ?int $episodeNumber = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $animatedPreview = null;

    public function __construct()
    {
        $this->categories = new ArrayCollection();
        $this->tags = new ArrayCollection();
        $this->comments = new ArrayCollection();
        $this->encodedFiles = new ArrayCollection();
        $this->performers = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
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

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;
        return $this;
    }

    public function getTempVideoFile(): ?string
    {
        return $this->tempVideoFile;
    }

    public function setTempVideoFile(?string $tempVideoFile): static
    {
        $this->tempVideoFile = $tempVideoFile;
        return $this;
    }

    public function getConvertedFiles(): ?array
    {
        return $this->convertedFiles;
    }

    public function setConvertedFiles(?array $convertedFiles): static
    {
        $this->convertedFiles = $convertedFiles;
        return $this;
    }

    public function getPreview(): ?string
    {
        return $this->preview;
    }

    public function setPreview(?string $preview): static
    {
        $this->preview = $preview;
        return $this;
    }

    public function getPoster(): ?string
    {
        return $this->poster;
    }

    public function setPoster(?string $poster): static
    {
        $this->poster = $poster;
        return $this;
    }

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(int $duration): static
    {
        $this->duration = $duration;
        return $this;
    }

    public function getResolution(): ?string
    {
        return $this->resolution;
    }

    public function setResolution(?string $resolution): static
    {
        $this->resolution = $resolution;
        return $this;
    }

    public function getFormat(): ?string
    {
        return $this->format;
    }

    public function setFormat(?string $format): static
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

    public function isFeatured(): ?bool
    {
        return $this->isFeatured;
    }

    public function setFeatured(bool $isFeatured): static
    {
        $this->isFeatured = $isFeatured;
        return $this;
    }

    public function getViewsCount(): ?int
    {
        return $this->viewsCount;
    }

    public function setViewsCount(int $viewsCount): static
    {
        $this->viewsCount = $viewsCount;
        return $this;
    }

    public function incrementViews(): static
    {
        $this->viewsCount++;
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

    /**
     * Рассчитывает CTR (Click-Through Rate) в процентах
     * CTR = (просмотры / показы) * 100
     */
    public function getCtr(): float
    {
        if ($this->impressionsCount === 0 || $this->impressionsCount === null) {
            return 0.0;
        }
        return round(($this->viewsCount / $this->impressionsCount) * 100, 2);
    }

    public function getCommentsCount(): ?int
    {
        return $this->commentsCount;
    }

    public function setCommentsCount(int $commentsCount): static
    {
        $this->commentsCount = $commentsCount;
        return $this;
    }

    public function getLikesCount(): ?int
    {
        return $this->likesCount;
    }

    public function setLikesCount(int $likesCount): static
    {
        $this->likesCount = $likesCount;
        return $this;
    }

    public function getDislikesCount(): ?int
    {
        return $this->dislikesCount;
    }

    public function setDislikesCount(int $dislikesCount): static
    {
        $this->dislikesCount = $dislikesCount;
        return $this;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function setMetaDescription(?string $metaDescription): static
    {
        $this->metaDescription = $metaDescription;
        return $this;
    }

    public function getProcessingStatus(): ?string
    {
        return $this->processingStatus;
    }

    public function setProcessingStatus(string $processingStatus): static
    {
        $this->processingStatus = $processingStatus;
        return $this;
    }

    public function getProcessingProgress(): ?int
    {
        return $this->processingProgress;
    }

    public function setProcessingProgress(int $processingProgress): static
    {
        $this->processingProgress = $processingProgress;
        return $this;
    }

    public function getProcessingError(): ?string
    {
        return $this->processingError;
    }

    public function setProcessingError(?string $processingError): static
    {
        $this->processingError = $processingError;
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

    public function getCategory(): ?Category
    {
        return $this->categories->first() ?: null;
    }

    public function setCategory(?Category $category): static
    {
        $this->categories->clear();
        if ($category) {
            $this->categories->add($category);
        }
        return $this;
    }

    /**
     * @return Collection<int, Category>
     */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    public function addCategory(Category $category): static
    {
        if (!$this->categories->contains($category)) {
            $this->categories->add($category);
        }
        return $this;
    }

    public function removeCategory(Category $category): static
    {
        $this->categories->removeElement($category);
        return $this;
    }

    /**
     * @return Collection<int, Tag>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): static
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }
        return $this;
    }

    public function removeTag(Tag $tag): static
    {
        $this->tags->removeElement($tag);
        return $this;
    }

    /**
     * @return Collection<int, Comment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    /**
     * @return Collection<int, VideoFile>
     */
    public function getEncodedFiles(): Collection
    {
        return $this->encodedFiles;
    }

    public function addEncodedFile(VideoFile $encodedFile): static
    {
        if (!$this->encodedFiles->contains($encodedFile)) {
            $this->encodedFiles->add($encodedFile);
            $encodedFile->setVideo($this);
        }
        return $this;
    }

    /**
     * @return Collection<int, ModelProfile>
     */
    public function getPerformers(): Collection
    {
        return $this->performers;
    }

    public function addPerformer(ModelProfile $performer): static
    {
        if (!$this->performers->contains($performer)) {
            $this->performers->add($performer);
        }
        return $this;
    }

    public function removePerformer(ModelProfile $performer): static
    {
        $this->performers->removeElement($performer);
        return $this;
    }

    public function getDurationFormatted(): string
    {
        $hours = floor($this->duration / 3600);
        $minutes = floor(($this->duration % 3600) / 60);
        $seconds = $this->duration % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        }
        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    public function getPrimaryVideoFile(): ?VideoFile
    {
        foreach ($this->encodedFiles as $file) {
            if ($file->isPrimary()) {
                return $file;
            }
        }
        return $this->encodedFiles->first() ?: null;
    }

    public function getSeason(): ?Season
    {
        return $this->season;
    }

    public function setSeason(?Season $season): static
    {
        $this->season = $season;
        return $this;
    }

    public function getEpisodeNumber(): ?int
    {
        return $this->episodeNumber;
    }

    public function setEpisodeNumber(?int $episodeNumber): static
    {
        $this->episodeNumber = $episodeNumber;
        return $this;
    }

    public function getAnimatedPreview(): ?string
    {
        return $this->animatedPreview;
    }

    public function setAnimatedPreview(?string $animatedPreview): static
    {
        $this->animatedPreview = $animatedPreview;
        return $this;
    }

    public function isPartOfSeries(): bool
    {
        return $this->season !== null;
    }

    public function getSeries(): ?Series
    {
        return $this->season?->getSeries();
    }
}
