<?php

namespace App\Entity;

use App\Repository\PlaylistRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PlaylistRepository::class)]
#[ORM\Table(name: 'playlist')]
class Playlist
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 200)]
    #[Assert\NotBlank(message: 'playlist.title.not_blank')]
    #[Assert\Length(max: 200, maxMessage: 'playlist.title.max_length')]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $thumbnail = null;

    #[ORM\Column]
    private bool $isPublic = true;

    #[ORM\Column]
    private int $videosCount = 0;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $owner = null;

    /** @var Collection<int, PlaylistVideo> */
    #[ORM\OneToMany(targetEntity: PlaylistVideo::class, mappedBy: 'playlist', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $videos;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

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

    public function getThumbnail(): ?string
    {
        return $this->thumbnail;
    }

    public function setThumbnail(?string $thumbnail): static
    {
        $this->thumbnail = $thumbnail;
        return $this;
    }

    public function isPublic(): bool
    {
        return $this->isPublic;
    }

    public function setIsPublic(bool $isPublic): static
    {
        $this->isPublic = $isPublic;
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

    public function incrementVideosCount(): static
    {
        $this->videosCount++;
        return $this;
    }

    public function decrementVideosCount(): static
    {
        if ($this->videosCount > 0) {
            $this->videosCount--;
        }
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
     * @return Collection<int, PlaylistVideo>
     */
    public function getVideos(): Collection
    {
        return $this->videos;
    }

    public function addVideo(PlaylistVideo $playlistVideo): static
    {
        if (!$this->videos->contains($playlistVideo)) {
            $this->videos->add($playlistVideo);
            $playlistVideo->setPlaylist($this);
        }
        return $this;
    }

    public function removeVideo(PlaylistVideo $playlistVideo): static
    {
        if ($this->videos->removeElement($playlistVideo)) {
            if ($playlistVideo->getPlaylist() === $this) {
                $playlistVideo->setPlaylist(null);
            }
        }
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

    public function updateTimestamp(): static
    {
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }
}
