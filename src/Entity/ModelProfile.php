<?php

namespace App\Entity;

use App\Repository\ModelProfileRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ModelProfileRepository::class)]
#[ORM\Table(name: 'model_profile')]
class ModelProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $displayName = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $aliases = [];

    #[ORM\Column(length: 200, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $bio = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatar = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $coverPhoto = null;

    #[ORM\Column(length: 10)]
    private ?string $gender = 'female';

    #[ORM\Column(nullable: true)]
    private ?int $age = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $birthDate = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $country = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $ethnicity = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $careerStart = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $hairColor = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $eyeColor = null;

    #[ORM\Column]
    private ?bool $hasTattoos = false;

    #[ORM\Column]
    private ?bool $hasPiercings = false;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $breastSize = null;

    #[ORM\Column(nullable: true)]
    private ?int $height = null;

    #[ORM\Column(nullable: true)]
    private ?int $weight = null;

    #[ORM\Column]
    private ?int $viewsCount = 0;

    #[ORM\Column]
    private ?int $subscribersCount = 0;

    #[ORM\Column]
    private ?int $videosCount = 0;

    #[ORM\Column]
    private ?int $likesCount = 0;

    #[ORM\Column]
    private ?int $dislikesCount = 0;

    #[ORM\Column]
    private ?bool $isVerified = false;

    #[ORM\Column]
    private ?bool $isActive = true;

    #[ORM\Column]
    private ?bool $isPremium = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $metaTitle = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $metaDescription = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $metaKeywords = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToMany(targetEntity: Video::class, mappedBy: 'performers')]
    private Collection $videos;

    public function __construct()
    {
        $this->videos = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): static
    {
        $this->displayName = $displayName;
        return $this;
    }

    public function getAliases(): ?array
    {
        return $this->aliases;
    }

    public function setAliases(?array $aliases): static
    {
        $this->aliases = $aliases;
        return $this;
    }

    public function getAliasesAsString(): string
    {
        return $this->aliases ? implode(', ', $this->aliases) : '';
    }

    public function setAliasesFromString(string $aliasesString): static
    {
        if (empty(trim($aliasesString))) {
            $this->aliases = [];
        } else {
            $this->aliases = array_map('trim', explode(',', $aliasesString));
        }
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

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): static
    {
        $this->bio = $bio;
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

    public function getCoverPhoto(): ?string
    {
        return $this->coverPhoto;
    }

    public function setCoverPhoto(?string $coverPhoto): static
    {
        $this->coverPhoto = $coverPhoto;
        return $this;
    }

    public function getGender(): ?string
    {
        return $this->gender;
    }

    public function setGender(string $gender): static
    {
        $this->gender = $gender;
        return $this;
    }

    public function getAge(): ?int
    {
        return $this->age;
    }

    public function setAge(?int $age): static
    {
        $this->age = $age;
        return $this;
    }

    public function getBirthDate(): ?\DateTimeInterface
    {
        return $this->birthDate;
    }

    public function setBirthDate(?\DateTimeInterface $birthDate): static
    {
        $this->birthDate = $birthDate;
        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): static
    {
        $this->country = $country;
        return $this;
    }

    public function getEthnicity(): ?string
    {
        return $this->ethnicity;
    }

    public function setEthnicity(?string $ethnicity): static
    {
        $this->ethnicity = $ethnicity;
        return $this;
    }

    public function getCareerStart(): ?\DateTimeInterface
    {
        return $this->careerStart;
    }

    public function setCareerStart(?\DateTimeInterface $careerStart): static
    {
        $this->careerStart = $careerStart;
        return $this;
    }

    public function getHairColor(): ?string
    {
        return $this->hairColor;
    }

    public function setHairColor(?string $hairColor): static
    {
        $this->hairColor = $hairColor;
        return $this;
    }

    public function getEyeColor(): ?string
    {
        return $this->eyeColor;
    }

    public function setEyeColor(?string $eyeColor): static
    {
        $this->eyeColor = $eyeColor;
        return $this;
    }

    public function hasTattoos(): ?bool
    {
        return $this->hasTattoos;
    }

    public function setHasTattoos(bool $hasTattoos): static
    {
        $this->hasTattoos = $hasTattoos;
        return $this;
    }

    public function hasPiercings(): ?bool
    {
        return $this->hasPiercings;
    }

    public function setHasPiercings(bool $hasPiercings): static
    {
        $this->hasPiercings = $hasPiercings;
        return $this;
    }

    public function getBreastSize(): ?string
    {
        return $this->breastSize;
    }

    public function setBreastSize(?string $breastSize): static
    {
        $this->breastSize = $breastSize;
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

    public function getWeight(): ?int
    {
        return $this->weight;
    }

    public function setWeight(?int $weight): static
    {
        $this->weight = $weight;
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

    public function getSubscribersCount(): ?int
    {
        return $this->subscribersCount;
    }

    public function setSubscribersCount(int $subscribersCount): static
    {
        $this->subscribersCount = $subscribersCount;
        return $this;
    }

    public function getVideosCount(): ?int
    {
        return $this->videosCount;
    }

    public function setVideosCount(int $videosCount): static
    {
        $this->videosCount = $videosCount;
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

    public function isVerified(): ?bool
    {
        return $this->isVerified;
    }

    public function setVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;
        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function isPremium(): ?bool
    {
        return $this->isPremium;
    }

    public function setPremium(bool $isPremium): static
    {
        $this->isPremium = $isPremium;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return Collection<int, Video>
     */
    public function getVideos(): Collection
    {
        return $this->videos;
    }

    public function getMetaTitle(): ?string
    {
        return $this->metaTitle;
    }

    public function setMetaTitle(?string $metaTitle): static
    {
        $this->metaTitle = $metaTitle;
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

    public function getMetaKeywords(): ?string
    {
        return $this->metaKeywords;
    }

    public function setMetaKeywords(?string $metaKeywords): static
    {
        $this->metaKeywords = $metaKeywords;
        return $this;
    }
}
