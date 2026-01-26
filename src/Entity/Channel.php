<?php

namespace App\Entity;

use App\Repository\ChannelRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\String\Slugger\SluggerInterface;

#[ORM\Entity(repositoryClass: ChannelRepository::class)]
#[ORM\Table(name: 'channels')]
#[ORM\Index(columns: ['slug'], name: 'idx_channel_slug')]
#[ORM\Index(columns: ['type'], name: 'idx_channel_type')]
#[ORM\Index(columns: ['is_verified'], name: 'idx_channel_verified')]
#[ORM\Index(columns: ['is_active'], name: 'idx_channel_active')]
class Channel
{
    public const TYPE_PERSONAL = 'personal';
    public const TYPE_STUDIO = 'studio';
    public const TYPE_COMPANY = 'company';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 20, options: ['default' => 'personal'])]
    private string $type = self::TYPE_PERSONAL;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatar = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $banner = null;

    #[ORM\Column(length: 7, nullable: true)]
    private ?string $primaryColor = null;

    #[ORM\Column(length: 7, nullable: true)]
    private ?string $secondaryColor = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $website = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $twitter = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $instagram = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $onlyfans = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isVerified = false;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isPremium = false;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $subscriptionPrice = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $subscribersCount = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $videosCount = 0;

    #[ORM\Column(type: Types::BIGINT, options: ['default' => 0])]
    private int $totalViews = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $owner = null;

    #[ORM\OneToMany(mappedBy: 'channel', targetEntity: Video::class)]
    private Collection $videos;

    #[ORM\OneToMany(mappedBy: 'channel', targetEntity: ChannelSubscription::class, cascade: ['persist', 'remove'])]
    private Collection $subscriptions;

    #[ORM\OneToMany(mappedBy: 'channel', targetEntity: ChannelMember::class, cascade: ['persist', 'remove'])]
    private Collection $members;

    public function __construct()
    {
        $this->videos = new ArrayCollection();
        $this->subscriptions = new ArrayCollection();
        $this->members = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
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

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): static
    {
        $this->avatar = $avatar;
        return $this;
    }

    public function getBanner(): ?string
    {
        return $this->banner;
    }

    public function setBanner(?string $banner): static
    {
        $this->banner = $banner;
        return $this;
    }

    public function getPrimaryColor(): ?string
    {
        return $this->primaryColor;
    }

    public function setPrimaryColor(?string $primaryColor): static
    {
        $this->primaryColor = $primaryColor;
        return $this;
    }

    public function getSecondaryColor(): ?string
    {
        return $this->secondaryColor;
    }

    public function setSecondaryColor(?string $secondaryColor): static
    {
        $this->secondaryColor = $secondaryColor;
        return $this;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function setWebsite(?string $website): static
    {
        $this->website = $website;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getTwitter(): ?string
    {
        return $this->twitter;
    }

    public function setTwitter(?string $twitter): static
    {
        $this->twitter = $twitter;
        return $this;
    }

    public function getInstagram(): ?string
    {
        return $this->instagram;
    }

    public function setInstagram(?string $instagram): static
    {
        $this->instagram = $instagram;
        return $this;
    }

    public function getOnlyfans(): ?string
    {
        return $this->onlyfans;
    }

    public function setOnlyfans(?string $onlyfans): static
    {
        $this->onlyfans = $onlyfans;
        return $this;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function isPremium(): bool
    {
        return $this->isPremium;
    }

    public function setIsPremium(bool $isPremium): static
    {
        $this->isPremium = $isPremium;
        return $this;
    }

    public function getSubscriptionPrice(): ?string
    {
        return $this->subscriptionPrice;
    }

    public function setSubscriptionPrice(?string $subscriptionPrice): static
    {
        $this->subscriptionPrice = $subscriptionPrice;
        return $this;
    }

    public function getSubscribersCount(): int
    {
        return $this->subscribersCount;
    }

    public function setSubscribersCount(int $subscribersCount): static
    {
        $this->subscribersCount = $subscribersCount;
        return $this;
    }

    public function getVideosCount(): int
    {
        return $this->videosCount;
    }

    public function setVideosCount(int $videosCount): static
    {
        $this->videosCount = $videosCount;
        return $this;
    }

    public function getTotalViews(): int
    {
        return $this->totalViews;
    }

    public function setTotalViews(int $totalViews): static
    {
        $this->totalViews = $totalViews;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;
        return $this;
    }

    /**
     * @return Collection<int, Video>
     */
    public function getVideos(): Collection
    {
        return $this->videos;
    }

    public function addVideo(Video $video): static
    {
        if (!$this->videos->contains($video)) {
            $this->videos->add($video);
            $video->setChannel($this);
        }

        return $this;
    }

    public function removeVideo(Video $video): static
    {
        if ($this->videos->removeElement($video)) {
            if ($video->getChannel() === $this) {
                $video->setChannel(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ChannelSubscription>
     */
    public function getSubscriptions(): Collection
    {
        return $this->subscriptions;
    }

    public function addSubscription(ChannelSubscription $subscription): static
    {
        if (!$this->subscriptions->contains($subscription)) {
            $this->subscriptions->add($subscription);
            $subscription->setChannel($this);
        }

        return $this;
    }

    public function removeSubscription(ChannelSubscription $subscription): static
    {
        if ($this->subscriptions->removeElement($subscription)) {
            if ($subscription->getChannel() === $this) {
                $subscription->setChannel(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ChannelMember>
     */
    public function getMembers(): Collection
    {
        return $this->members;
    }

    public function addMember(ChannelMember $member): static
    {
        if (!$this->members->contains($member)) {
            $this->members->add($member);
            $member->setChannel($this);
        }

        return $this;
    }

    public function removeMember(ChannelMember $member): static
    {
        if ($this->members->removeElement($member)) {
            if ($member->getChannel() === $this) {
                $member->setChannel(null);
            }
        }

        return $this;
    }

    public function generateSlug(SluggerInterface $slugger): void
    {
        if (!$this->slug && $this->name) {
            $this->slug = $slugger->slug($this->name)->lower();
        }
    }

    public function getAvatarUrl(): ?string
    {
        return $this->avatar ? '/media/channels/avatars/' . $this->avatar : null;
    }

    public function getBannerUrl(): ?string
    {
        return $this->banner ? '/media/channels/banners/' . $this->banner : null;
    }

    public function isStudio(): bool
    {
        return $this->type === self::TYPE_STUDIO;
    }

    public function isCompany(): bool
    {
        return $this->type === self::TYPE_COMPANY;
    }

    public function isPersonal(): bool
    {
        return $this->type === self::TYPE_PERSONAL;
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }
}