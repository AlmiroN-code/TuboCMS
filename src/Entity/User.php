<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'email.already_used')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'email.not_blank')]
    #[Assert\Email(message: 'email.invalid')]
    #[Assert\Length(max: 180, maxMessage: 'email.max_length')]
    private ?string $email = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank(message: 'username.not_blank')]
    #[Assert\Length(
        min: 3,
        max: 180,
        minMessage: 'username.min_length',
        maxMessage: 'username.max_length'
    )]
    #[Assert\Regex(
        pattern: '/^[a-zA-Z0-9_-]+$/',
        message: 'username.invalid_characters'
    )]
    private ?string $username = null;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatar = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $coverImage = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $bio = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $birthDate = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $country = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $gender = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $orientation = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $maritalStatus = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $education = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $website = null;

    #[ORM\Column]
    private ?bool $isVerified = false;

    #[ORM\Column]
    private ?bool $isPremium = false;

    #[ORM\Column]
    private ?int $processingPriority = 5;

    #[ORM\Column]
    private ?int $subscribersCount = 0;

    #[ORM\Column]
    private ?int $videosCount = 0;

    #[ORM\Column]
    private ?int $totalViews = 0;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $lastIpAddress = null;

    #[ORM\Column(length: 2, nullable: true)]
    private ?string $countryCode = null;

    #[ORM\Column]
    private ?bool $countryManuallySet = false;

    #[ORM\OneToMany(targetEntity: Video::class, mappedBy: 'createdBy')]
    private Collection $videos;

    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'user')]
    private Collection $comments;

    #[ORM\OneToMany(targetEntity: Subscription::class, mappedBy: 'subscriber')]
    private Collection $subscriptions;

    #[ORM\OneToMany(targetEntity: Subscription::class, mappedBy: 'channel')]
    private Collection $subscribers;

    #[ORM\ManyToMany(targetEntity: Role::class, inversedBy: 'users')]
    #[ORM\JoinTable(name: 'user_role')]
    private ?Collection $userRoles = null;

    #[ORM\OneToMany(targetEntity: LiveStream::class, mappedBy: 'streamer')]
    private Collection $liveStreams;

    public function __construct()
    {
        $this->videos = new ArrayCollection();
        $this->comments = new ArrayCollection();
        $this->subscriptions = new ArrayCollection();
        $this->subscribers = new ArrayCollection();
        $this->userRoles = new ArrayCollection();
        $this->liveStreams = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }



    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        
        // Добавляем роли из связанных Role entities
        foreach ($this->getUserRoles() as $role) {
            if ($role->isActive()) {
                // Роли уже содержат префикс ROLE_, не дублируем
                $roleName = $role->getName();
                if (!str_starts_with($roleName, 'ROLE_')) {
                    $roleName = 'ROLE_' . strtoupper($roleName);
                }
                $roles[] = $roleName;
            }
        }
        
        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    /**
     * @return Collection<int, Role>
     */
    public function getUserRoles(): Collection
    {
        if ($this->userRoles === null) {
            $this->userRoles = new ArrayCollection();
        }
        return $this->userRoles;
    }

    public function addUserRole(Role $role): static
    {
        if (!$this->getUserRoles()->contains($role)) {
            $this->getUserRoles()->add($role);
        }
        return $this;
    }

    public function removeUserRole(Role $role): static
    {
        $this->getUserRoles()->removeElement($role);
        return $this;
    }

    public function hasRole(string $roleName): bool
    {
        foreach ($this->getUserRoles() as $role) {
            if ($role->getName() === $roleName && $role->isActive()) {
                return true;
            }
        }
        return false;
    }

    public function hasPermission(string $permissionName): bool
    {
        foreach ($this->getUserRoles() as $role) {
            if ($role->isActive() && $role->hasPermission($permissionName)) {
                return true;
            }
        }
        return false;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function eraseCredentials(): void
    {
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

    public function getCoverImage(): ?string
    {
        return $this->coverImage;
    }

    public function setCoverImage(?string $coverImage): static
    {
        $this->coverImage = $coverImage;
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

    public function getBirthDate(): ?\DateTimeInterface
    {
        return $this->birthDate;
    }

    public function setBirthDate(?\DateTimeInterface $birthDate): static
    {
        $this->birthDate = $birthDate;
        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): static
    {
        $this->location = $location;
        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = $city;
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

    public function getGender(): ?string
    {
        return $this->gender;
    }

    public function setGender(?string $gender): static
    {
        $this->gender = $gender;
        return $this;
    }

    public function getOrientation(): ?string
    {
        return $this->orientation;
    }

    public function setOrientation(?string $orientation): static
    {
        $this->orientation = $orientation;
        return $this;
    }

    public function getMaritalStatus(): ?string
    {
        return $this->maritalStatus;
    }

    public function setMaritalStatus(?string $maritalStatus): static
    {
        $this->maritalStatus = $maritalStatus;
        return $this;
    }

    public function getEducation(): ?string
    {
        return $this->education;
    }

    public function setEducation(?string $education): static
    {
        $this->education = $education;
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

    public function isVerified(): ?bool
    {
        return $this->isVerified;
    }

    public function setVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;
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

    public function getProcessingPriority(): ?int
    {
        return $this->processingPriority;
    }

    public function setProcessingPriority(int $processingPriority): static
    {
        $this->processingPriority = $processingPriority;
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

    public function getTotalViews(): ?int
    {
        return $this->totalViews;
    }

    public function setTotalViews(int $totalViews): static
    {
        $this->totalViews = $totalViews;
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
            $video->setCreatedBy($this);
        }
        return $this;
    }

    public function removeVideo(Video $video): static
    {
        if ($this->videos->removeElement($video)) {
            if ($video->getCreatedBy() === $this) {
                $video->setCreatedBy(null);
            }
        }
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
     * @return Collection<int, Subscription>
     */
    public function getSubscriptions(): Collection
    {
        return $this->subscriptions;
    }

    /**
     * @return Collection<int, Subscription>
     */
    public function getSubscribers(): Collection
    {
        return $this->subscribers;
    }

    public function getAge(): ?int
    {
        if (!$this->birthDate) {
            return null;
        }
        $now = new \DateTime();
        return $now->diff($this->birthDate)->y;
    }

    public function getProcessingPriorityLevel(): int
    {
        if ($this->isPremium) {
            return max(7, $this->processingPriority);
        }
        
        if ($this->videosCount > 50) {
            return min(6, $this->processingPriority + 1);
        }
        
        if ($this->videosCount < 5) {
            return 3;
        }
        
        return $this->processingPriority;
    }

    /**
     * @return Collection<int, LiveStream>
     */
    public function getLiveStreams(): Collection
    {
        return $this->liveStreams;
    }

    public function __toString(): string
    {
        return $this->username ?? '';
    }

    public function getLastIpAddress(): ?string
    {
        return $this->lastIpAddress;
    }

    public function setLastIpAddress(?string $lastIpAddress): static
    {
        $this->lastIpAddress = $lastIpAddress;
        return $this;
    }

    public function getCountryCode(): ?string
    {
        return $this->countryCode;
    }

    public function setCountryCode(?string $countryCode): static
    {
        $this->countryCode = $countryCode;
        return $this;
    }

    public function isCountryManuallySet(): bool
    {
        return $this->countryManuallySet ?? false;
    }

    public function setCountryManuallySet(bool $countryManuallySet): static
    {
        $this->countryManuallySet = $countryManuallySet;
        return $this;
    }
}
