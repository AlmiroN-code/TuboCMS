<?php

namespace App\Twig\Components;

use App\Service\SearchService;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class VideoSearch
{
    use DefaultActionTrait;

    #[LiveProp(writable: true, url: false)]
    public string $query = '';

    #[LiveProp]
    public int $limit = 10;

    private ?array $cachedResults = null;

    public function __construct(
        private SearchService $searchService,
    ) {
    }

    /**
     * Получить все результаты поиска (видео, теги, категории, модели)
     */
    public function getResults(): array
    {
        if ($this->cachedResults !== null) {
            return $this->cachedResults;
        }

        if (mb_strlen($this->query) < 2) {
            $this->cachedResults = [
                'videos' => [],
                'tags' => [],
                'categories' => [],
                'models' => [],
                'total' => 0,
            ];
            return $this->cachedResults;
        }

        $this->cachedResults = $this->searchService->autocomplete($this->query, $this->limit);
        return $this->cachedResults;
    }

    /**
     * Получить только видео результаты
     */
    public function getVideos(): array
    {
        return $this->getResults()['videos'];
    }

    /**
     * Получить теги
     */
    public function getTags(): array
    {
        return $this->getResults()['tags'];
    }

    /**
     * Получить категории
     */
    public function getCategories(): array
    {
        return $this->getResults()['categories'];
    }

    /**
     * Получить модели
     */
    public function getModels(): array
    {
        return $this->getResults()['models'];
    }

    public function hasResults(): bool
    {
        return $this->getResults()['total'] > 0;
    }

    public function hasVideos(): bool
    {
        return count($this->getResults()['videos']) > 0;
    }

    public function hasTags(): bool
    {
        return count($this->getResults()['tags']) > 0;
    }

    public function hasCategories(): bool
    {
        return count($this->getResults()['categories']) > 0;
    }

    public function hasModels(): bool
    {
        return count($this->getResults()['models']) > 0;
    }

    public function isSearching(): bool
    {
        return mb_strlen($this->query) >= 2;
    }
}
