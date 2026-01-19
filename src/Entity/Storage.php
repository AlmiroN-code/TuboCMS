<?php

namespace App\Entity;

use App\Repository\StorageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: StorageRepository::class)]
#[ORM\Table(name: 'storage')]
#[ORM\HasLifecycleCallbacks]
class Storage
{
    public const TYPE_LOCAL = 'local';
    public const TYPE_FTP = 'ftp';
    public const TYPE_SFTP = 'sftp';
    public const TYPE_HTTP = 'http';
    public const TYPE_S3 = 's3';

    public const VALID_TYPES = [
        self::TYPE_LOCAL,
        self::TYPE_FTP,
        self::TYPE_SFTP,
        self::TYPE_HTTP,
        self::TYPE_S3,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Название хранилища обязательно', normalizer: 'trim')]
    #[Assert\Length(max: 100, maxMessage: 'Название не должно превышать {{ limit }} символов')]
    private ?string $name = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'Тип хранилища обязателен')]
    #[Assert\Choice(choices: self::VALID_TYPES, message: 'Недопустимый тип хранилища')]
    private ?string $type = null;

    #[ORM\Column(type: Types::JSON)]
    private array $config = [];

    #[ORM\Column]
    private bool $isDefault = false;

    #[ORM\Column]
    private bool $isEnabled = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function setConfig(array $config): static
    {
        $this->config = $config;
        return $this;
    }

    public function getConfigValue(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    public function setConfigValue(string $key, mixed $value): static
    {
        $this->config[$key] = $value;
        return $this;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): static
    {
        $this->isDefault = $isDefault;
        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function setIsEnabled(bool $isEnabled): static
    {
        $this->isEnabled = $isEnabled;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * Проверяет, является ли хранилище локальным
     */
    public function isLocal(): bool
    {
        return $this->type === self::TYPE_LOCAL;
    }

    /**
     * Проверяет, является ли хранилище удалённым
     */
    public function isRemote(): bool
    {
        return !$this->isLocal();
    }
}
