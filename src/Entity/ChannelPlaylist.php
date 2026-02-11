<?php

namespace App\Entity;

use App\Repository\ChannelPlaylistRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\String\Slugger\SluggerInterface;

#[ORM\Entity(repositoryClass: ChannelPlaylistRepository::class)]
#[ORM\Table(name: 'channel_playlists')]
#[ORM\Index(columns: ['channel_id'], name: 'idx_playlist_channel')]
#[ORM\Index(columns: ['slug'], name: 'idx_playlist_slug')]
class ChannelPlaylist
{
    public const VISIBILITY_PUBLIC = 'public';
    public const VISIBILITY_UNLISTED = 'unlisted';
    public const VISIBILITY_PRIVATE = 'private';
    public const VISIBILITY_PREMIUM = 'premium';
    public const VISIBILITY_USER_SUBSCRIBERS = 'user_subscribers'; // Подписчики пользователя
    public const VISIBILITY_CHANNEL_SUBSCRIBERS = 'channel_subscribers'; // Подписчики канала

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Channel::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Channel $channel = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $thumbnail = null;

    #[ORM\Column(length: 20, options: ['default' => 'public'])]
    private string $visibility = self::VISIBILITY_PUBLIC;

    #[ORM\Column(length: 64, nullable: true, unique: true)]
    private ?string $shareToken = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isCollaborative = false;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $videosCount = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $viewsCount = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $sortOrder = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\OneToMany(mappedBy: 'playlist', targetEntity: PlaylistVideo::class, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['sortOrder' => 'ASC'])]
    private Collection $playlistVideos;

    #[ORM\OneToMany(mappedBy: 'playlist', targetEntity: PlaylistCollaborator::class, cascade: ['persist', 'remove'])]
    private Collection $collaborators;

    public function __construct()
    {
        $this->playlistVideos = new ArrayCollection();
        $this->collaborators = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getChannel(): ?Channel
    {
        return $this->channel;
    }

    public function setChannel(?Channel $channel): static
    {
        $this->channel = $channel;
        return $this;
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

    public function getThumbnail(): ?string
    {
        return $this->thumbnail;
    }

    public function setThumbnail(?string $thumbnail): static
    {
        $this->thumbnail = $thumbnail;
        return $this;
    }

    public function getVisibility(): string
    {
        return $this->visibility;
    }

    public function setVisibility(string $visibility): static
    {
        $this->visibility = $visibility;
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

    public function isCollaborative(): bool
    {
        return $this->isCollaborative;
    }

    public function setIsCollaborative(bool $isCollaborative): static
    {
        $this->isCollaborative = $isCollaborative;
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

    public function getViewsCount(): int
    {
        return $this->viewsCount;
    }

    public function setViewsCount(int $viewsCount): static
    {
        $this->viewsCount = $viewsCount;
        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;
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

    /**
     * @return Collection<int, PlaylistVideo>
     */
    public function getPlaylistVideos(): Collection
    {
        return $this->playlistVideos;
    }

    public function addPlaylistVideo(PlaylistVideo $playlistVideo): static
    {
        if (!$this->playlistVideos->contains($playlistVideo)) {
            $this->playlistVideos->add($playlistVideo);
            $playlistVideo->setPlaylist($this);
        }

        return $this;
    }

    public function removePlaylistVideo(PlaylistVideo $playlistVideo): static
    {
        if ($this->playlistVideos->removeElement($playlistVideo)) {
            if ($playlistVideo->getPlaylist() === $this) {
                $playlistVideo->setPlaylist(null);
            }
        }

        return $this;
    }

    public function generateSlug(SluggerInterface $slugger): void
    {
        if (!$this->slug && $this->title) {
            $this->slug = $slugger->slug($this->title)->lower();
        }
    }

    public function getThumbnailUrl(): ?string
    {
        return $this->thumbnail ? '/media/playlists/thumbnails/' . $this->thumbnail : null;
    }

    public function isPublic(): bool
    {
        return $this->visibility === self::VISIBILITY_PUBLIC;
    }

    public function isPrivate(): bool
    {
        return $this->visibility === self::VISIBILITY_PRIVATE;
    }

    public function isPremium(): bool
    {
        return $this->visibility === self::VISIBILITY_PREMIUM;
    }

    public function isUnlisted(): bool
    {
        return $this->visibility === self::VISIBILITY_UNLISTED;
    }

    public function getVideos(): Collection
    {
        return $this->playlistVideos->map(fn(PlaylistVideo $pv) => $pv->getVideo());
    }

    /**
     * @return Collection<int, PlaylistCollaborator>
     */
    public function getCollaborators(): Collection
    {
        return $this->collaborators;
    }

    public function addCollaborator(PlaylistCollaborator $collaborator): static
    {
        if (!$this->collaborators->contains($collaborator)) {
            $this->collaborators->add($collaborator);
            $collaborator->setPlaylist($this);
        }

        return $this;
    }

    public function removeCollaborator(PlaylistCollaborator $collaborator): static
    {
        if ($this->collaborators->removeElement($collaborator)) {
            if ($collaborator->getPlaylist() === $this) {
                $collaborator->setPlaylist(null);
            }
        }

        return $this;
    }

    public function isUserCollaborator(User $user): bool
    {
        foreach ($this->collaborators as $collaborator) {
            if ($collaborator->getUser() === $user) {
                return true;
            }
        }
        return false;
    }

    public function getUserPermission(User $user): ?string
    {
        foreach ($this->collaborators as $collaborator) {
            if ($collaborator->getUser() === $user) {
                return $collaborator->getPermission();
            }
        }
        return null;
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function __toString(): string
    {
        return $this->title ?? '';
    }

    public function getShareToken(): ?string
    {
        return $this->shareToken;
    }

    public function setShareToken(?string $shareToken): static
    {
        $this->shareToken = $shareToken;
        return $this;
    }

    public function generateShareToken(): static
    {
        $this->shareToken = bin2hex(random_bytes(32));
        return $this;
    }

    public function getShareUrl(): ?string
    {
        if ($this->visibility === self::VISIBILITY_UNLISTED && $this->shareToken) {
            return '/playlist/share/' . $this->shareToken;
        }
        return null;
    }
}