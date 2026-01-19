<?php

namespace App\Entity;

use App\Repository\AdPlacementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: AdPlacementRepository::class)]
#[ORM\Table(name: 'ad_placement')]
#[ORM\Index(columns: ['slug'], name: 'idx_ad_placement_slug')]
#[ORM\Index(columns: ['is_active'], name: 'idx_ad_placement_active')]
#[UniqueEntity(fields: ['slug'], message: 'Место размещения с таким slug уже существует')]
class AdPlacement
{
    public const TYPE_BANNER = 'banner';
    public const TYPE_VIDEO = 'video';
    public const TYPE_VAST = 'vast';
    public const TYPE_NATIVE = 'native';
    public const TYPE_TEXT = 'text';
    public const TYPE_POPUP = 'popup';

    public const POSITION_HEADER = 'header';
    public const POSITION_SIDEBAR = 'sidebar';
    public const POSITION_CONTENT = 'content';
    public const POSITION_FOOTER = 'footer';
    public const POSITION_VIDEO_PREROLL = 'video_preroll';
    public const POSITION_VIDEO_MIDROLL = 'video_midroll';
    public const POSITION_VIDEO_POSTROLL = 'video_postroll';
    public const POSITION_VIDEO_OVERLAY = 'video_overlay';
    public const POSITION_BETWEEN_VIDEOS = 'between_videos';

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
    private ?string $type = self::TYPE_BANNER;

    #[ORM\Column(length: 30)]
    private ?string $position = self::POSITION_SIDEBAR;

    #[ORM\Column(nullable: true)]
    private ?int $width = null;

    #[ORM\Column(nullable: true)]
    private ?int $height = null;

    #[ORM\Column]
    private ?bool $isActive = true;

    #[ORM\Column]
    private ?int $orderPosition = 0;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $allowedPages = [];

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\OneToMany(targetEntity: Ad::class, mappedBy: 'placement')]
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

    public function getPosition(): ?string
    {
        return $this->position;
    }

    public function setPosition(string $position): static
    {
        $this->position = $position;
        return $this;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function setWidth(?int $width): static
    {
        $this->width = $width;
        return $this;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function setHeight(?int $height): static
    {
        $this->height = $height;
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

    public function getOrderPosition(): ?int
    {
        return $this->orderPosition;
    }

    public function setOrderPosition(int $orderPosition): static
    {
        $this->orderPosition = $orderPosition;
        return $this;
    }

    public function getAllowedPages(): ?array
    {
        return $this->allowedPages;
    }

    public function setAllowedPages(?array $allowedPages): static
    {
        $this->allowedPages = $allowedPages;
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

    public function addAd(Ad $ad): static
    {
        if (!$this->ads->contains($ad)) {
            $this->ads->add($ad);
            $ad->setPlacement($this);
        }
        return $this;
    }

    public function removeAd(Ad $ad): static
    {
        if ($this->ads->removeElement($ad)) {
            if ($ad->getPlacement() === $this) {
                $ad->setPlacement(null);
            }
        }
        return $this;
    }

    public static function getTypes(): array
    {
        return [
            'Баннер' => self::TYPE_BANNER,
            'Видео' => self::TYPE_VIDEO,
            'VAST' => self::TYPE_VAST,
            'Нативная' => self::TYPE_NATIVE,
            'Текст' => self::TYPE_TEXT,
            'Popup' => self::TYPE_POPUP,
        ];
    }

    public static function getPositions(): array
    {
        return [
            'Шапка' => self::POSITION_HEADER,
            'Сайдбар' => self::POSITION_SIDEBAR,
            'Контент' => self::POSITION_CONTENT,
            'Подвал' => self::POSITION_FOOTER,
            'Preroll (до видео)' => self::POSITION_VIDEO_PREROLL,
            'Midroll (в середине)' => self::POSITION_VIDEO_MIDROLL,
            'Postroll (после видео)' => self::POSITION_VIDEO_POSTROLL,
            'Overlay (поверх видео)' => self::POSITION_VIDEO_OVERLAY,
            'Между видео' => self::POSITION_BETWEEN_VIDEOS,
        ];
    }
}
