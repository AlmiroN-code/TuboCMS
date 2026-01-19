<?php

namespace App\Entity;

use App\Repository\SeasonRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SeasonRepository::class)]
#[ORM\Table(name: 'season')]
class Season
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Series::class, inversedBy: 'seasons')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Series $series = null;

    #[ORM\Column]
    private int $number = 1;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $title = null;

    /** @var Collection<int, Video> */
    #[ORM\OneToMany(targetEntity: Video::class, mappedBy: 'season')]
    #[ORM\OrderBy(['episodeNumber' => 'ASC'])]
    private Collection $episodes;

    public function __construct()
    {
        $this->episodes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSeries(): ?Series
    {
        return $this->series;
    }

    public function setSeries(?Series $series): static
    {
        $this->series = $series;
        return $this;
    }

    public function getNumber(): int
    {
        return $this->number;
    }

    public function setNumber(int $number): static
    {
        $this->number = $number;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return Collection<int, Video>
     */
    public function getEpisodes(): Collection
    {
        return $this->episodes;
    }

    public function addEpisode(Video $video): static
    {
        if (!$this->episodes->contains($video)) {
            $this->episodes->add($video);
            $video->setSeason($this);
        }
        return $this;
    }

    public function removeEpisode(Video $video): static
    {
        if ($this->episodes->removeElement($video)) {
            if ($video->getSeason() === $this) {
                $video->setSeason(null);
            }
        }
        return $this;
    }

    public function getDisplayTitle(): string
    {
        if ($this->title) {
            return $this->title;
        }
        return 'Сезон ' . $this->number;
    }
}
