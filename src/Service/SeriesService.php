<?php

namespace App\Service;

use App\Entity\Season;
use App\Entity\Series;
use App\Entity\User;
use App\Entity\Video;
use App\Repository\SeasonRepository;
use App\Repository\SeriesRepository;
use App\Repository\VideoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

class SeriesService
{
    public function __construct(
        private EntityManagerInterface $em,
        private SeriesRepository $seriesRepository,
        private SeasonRepository $seasonRepository,
        private VideoRepository $videoRepository,
        private SluggerInterface $slugger,
    ) {
    }

    public function create(User $author, string $title, ?string $description = null): Series
    {
        $series = new Series();
        $series->setAuthor($author);
        $series->setTitle($title);
        $series->setDescription($description);
        $series->setSlug($this->generateSlug($title));

        $this->em->persist($series);
        $this->em->flush();

        return $series;
    }

    public function update(Series $series, string $title, ?string $description = null): Series
    {
        $series->setTitle($title);
        $series->setDescription($description);
        
        $this->em->flush();

        return $series;
    }

    public function delete(Series $series): void
    {
        // Remove season references from videos
        foreach ($series->getSeasons() as $season) {
            foreach ($season->getEpisodes() as $video) {
                $video->setSeason(null);
                $video->setEpisodeNumber(null);
            }
        }

        $this->em->remove($series);
        $this->em->flush();
    }

    public function addSeason(Series $series, ?string $title = null): Season
    {
        $number = $this->seasonRepository->getMaxNumber($series) + 1;

        $season = new Season();
        $season->setSeries($series);
        $season->setNumber($number);
        $season->setTitle($title);

        $this->em->persist($season);
        $this->em->flush();

        return $season;
    }

    public function addEpisode(Season $season, Video $video): void
    {
        $maxEpisode = $this->videoRepository->createQueryBuilder('v')
            ->select('MAX(v.episodeNumber)')
            ->where('v.season = :season')
            ->setParameter('season', $season)
            ->getQuery()
            ->getSingleScalarResult();

        $episodeNumber = ($maxEpisode ?? 0) + 1;

        $video->setSeason($season);
        $video->setEpisodeNumber($episodeNumber);

        $series = $season->getSeries();
        $series->incrementVideosCount();

        $this->em->flush();
    }

    public function removeEpisode(Video $video): void
    {
        $season = $video->getSeason();
        if ($season === null) {
            return;
        }

        $series = $season->getSeries();
        $series->decrementVideosCount();

        $video->setSeason(null);
        $video->setEpisodeNumber(null);

        $this->em->flush();
    }

    public function getPrevEpisode(Video $video): ?Video
    {
        $season = $video->getSeason();
        if ($season === null || $video->getEpisodeNumber() === null) {
            return null;
        }

        // First try same season
        $prev = $this->videoRepository->createQueryBuilder('v')
            ->where('v.season = :season')
            ->andWhere('v.episodeNumber < :episodeNumber')
            ->andWhere('v.status = :status')
            ->setParameter('season', $season)
            ->setParameter('episodeNumber', $video->getEpisodeNumber())
            ->setParameter('status', Video::STATUS_PUBLISHED)
            ->orderBy('v.episodeNumber', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($prev !== null) {
            return $prev;
        }

        // Try previous season
        $series = $season->getSeries();
        $prevSeason = $this->seasonRepository->createQueryBuilder('s')
            ->where('s.series = :series')
            ->andWhere('s.number < :number')
            ->setParameter('series', $series)
            ->setParameter('number', $season->getNumber())
            ->orderBy('s.number', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($prevSeason === null) {
            return null;
        }

        return $this->videoRepository->createQueryBuilder('v')
            ->where('v.season = :season')
            ->andWhere('v.status = :status')
            ->setParameter('season', $prevSeason)
            ->setParameter('status', Video::STATUS_PUBLISHED)
            ->orderBy('v.episodeNumber', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getNextEpisode(Video $video): ?Video
    {
        $season = $video->getSeason();
        if ($season === null || $video->getEpisodeNumber() === null) {
            return null;
        }

        // First try same season
        $next = $this->videoRepository->createQueryBuilder('v')
            ->where('v.season = :season')
            ->andWhere('v.episodeNumber > :episodeNumber')
            ->andWhere('v.status = :status')
            ->setParameter('season', $season)
            ->setParameter('episodeNumber', $video->getEpisodeNumber())
            ->setParameter('status', Video::STATUS_PUBLISHED)
            ->orderBy('v.episodeNumber', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($next !== null) {
            return $next;
        }

        // Try next season
        $series = $season->getSeries();
        $nextSeason = $this->seasonRepository->createQueryBuilder('s')
            ->where('s.series = :series')
            ->andWhere('s.number > :number')
            ->setParameter('series', $series)
            ->setParameter('number', $season->getNumber())
            ->orderBy('s.number', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($nextSeason === null) {
            return null;
        }

        return $this->videoRepository->createQueryBuilder('v')
            ->where('v.season = :season')
            ->andWhere('v.status = :status')
            ->setParameter('season', $nextSeason)
            ->setParameter('status', Video::STATUS_PUBLISHED)
            ->orderBy('v.episodeNumber', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function generateSlug(string $title): string
    {
        $baseSlug = strtolower($this->slugger->slug($title)->toString());
        $slug = $baseSlug;
        $counter = 1;

        while ($this->seriesRepository->findBySlug($slug) !== null) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
