<?php

namespace App\Entity;

use App\Repository\PlaylistCollaboratorRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlaylistCollaboratorRepository::class)]
#[ORM\Table(name: 'playlist_collaborators')]
#[ORM\UniqueConstraint(name: 'unique_playlist_user', columns: ['playlist_id', 'user_id'])]
#[ORM\Index(columns: ['playlist_id'], name: 'idx_collaborator_playlist')]
#[ORM\Index(columns: ['user_id'], name: 'idx_collaborator_user')]
class PlaylistCollaborator
{
    public const PERMISSION_VIEW = 'view';
    public const PERMISSION_ADD = 'add';
    public const PERMISSION_EDIT = 'edit';
    public const PERMISSION_MANAGE = 'manage';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ChannelPlaylist::class, inversedBy: 'collaborators')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ChannelPlaylist $playlist = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 20, options: ['default' => 'add'])]
    private string $permission = self::PERMISSION_ADD;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $addedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $addedBy = null;

    public function __construct()
    {
        $this->addedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPlaylist(): ?ChannelPlaylist
    {
        return $this->playlist;
    }

    public function setPlaylist(?ChannelPlaylist $playlist): static
    {
        $this->playlist = $playlist;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getPermission(): string
    {
        return $this->permission;
    }

    public function setPermission(string $permission): static
    {
        $this->permission = $permission;
        return $this;
    }

    public function getAddedAt(): ?\DateTimeInterface
    {
        return $this->addedAt;
    }

    public function setAddedAt(\DateTimeInterface $addedAt): static
    {
        $this->addedAt = $addedAt;
        return $this;
    }

    public function getAddedBy(): ?User
    {
        return $this->addedBy;
    }

    public function setAddedBy(?User $addedBy): static
    {
        $this->addedBy = $addedBy;
        return $this;
    }

    public function canView(): bool
    {
        return in_array($this->permission, [
            self::PERMISSION_VIEW,
            self::PERMISSION_ADD,
            self::PERMISSION_EDIT,
            self::PERMISSION_MANAGE
        ]);
    }

    public function canAdd(): bool
    {
        return in_array($this->permission, [
            self::PERMISSION_ADD,
            self::PERMISSION_EDIT,
            self::PERMISSION_MANAGE
        ]);
    }

    public function canEdit(): bool
    {
        return in_array($this->permission, [
            self::PERMISSION_EDIT,
            self::PERMISSION_MANAGE
        ]);
    }

    public function canManage(): bool
    {
        return $this->permission === self::PERMISSION_MANAGE;
    }
}
