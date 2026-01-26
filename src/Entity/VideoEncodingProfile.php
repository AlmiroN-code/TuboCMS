<?php

namespace App\Entity;

use App\Repository\VideoEncodingProfileRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VideoEncodingProfileRepository::class)]
#[ORM\Table(name: 'video_encoding_profile')]
class VideoEncodingProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $name = null;

    #[ORM\Column(length: 20)]
    private ?string $resolution = null;

    #[ORM\Column]
    private ?int $bitrate = null;

    #[ORM\Column(length: 10)]
    private ?string $codec = 'h264';

    #[ORM\Column(length: 10)]
    private ?string $format = 'mp4';

    #[ORM\Column]
    private ?bool $isActive = true;

    #[ORM\Column]
    private ?int $orderPosition = 0;

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

    public function getResolution(): ?string
    {
        return $this->resolution;
    }

    public function setResolution(string $resolution): static
    {
        $this->resolution = $resolution;
        return $this;
    }

    public function getBitrate(): ?int
    {
        return $this->bitrate;
    }

    public function setBitrate(int $bitrate): static
    {
        $this->bitrate = $bitrate;
        return $this;
    }

    public function getCodec(): ?string
    {
        return $this->codec;
    }

    public function setCodec(string $codec): static
    {
        $this->codec = $codec;
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

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function setActive(bool $isActive): static
    {
        return $this->setIsActive($isActive);
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
}
