<?php

namespace App\Twig;

use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig расширение для SEO пагинации
 * - rel="prev" и rel="next" для страниц пагинации
 * - noindex для страниц с фильтрами
 */
class PaginationSeoExtension extends AbstractExtension
{
    public function __construct(
        private RequestStack $requestStack
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('pagination_prev_url', [$this, 'getPrevUrl']),
            new TwigFunction('pagination_next_url', [$this, 'getNextUrl']),
            new TwigFunction('should_noindex', [$this, 'shouldNoindex']),
        ];
    }

    /**
     * Возвращает URL предыдущей страницы для rel="prev"
     */
    public function getPrevUrl(int $currentPage, int $totalPages): ?string
    {
        if ($currentPage <= 1) {
            return null;
        }

        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return null;
        }

        $prevPage = $currentPage - 1;
        
        // Для первой страницы убираем параметр page
        if ($prevPage === 1) {
            return $this->buildUrlWithoutPage($request);
        }

        return $this->buildUrlWithPage($request, $prevPage);
    }

    /**
     * Возвращает URL следующей страницы для rel="next"
     */
    public function getNextUrl(int $currentPage, int $totalPages): ?string
    {
        if ($currentPage >= $totalPages) {
            return null;
        }

        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return null;
        }

        return $this->buildUrlWithPage($request, $currentPage + 1);
    }

    /**
     * Определяет, нужно ли добавить noindex
     * - Страницы с фильтрами (sort, filter, duration)
     * - Глубокие страницы пагинации (page > 10)
     */
    public function shouldNoindex(): bool
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return false;
        }

        // Параметры, которые требуют noindex
        $noindexParams = ['sort', 'filter', 'duration', 'quality', 'order'];
        
        foreach ($noindexParams as $param) {
            if ($request->query->has($param)) {
                return true;
            }
        }

        // Глубокие страницы пагинации (page > 10)
        $page = $request->query->getInt('page', 1);
        if ($page > 10) {
            return true;
        }

        return false;
    }

    private function buildUrlWithPage($request, int $page): string
    {
        $baseUrl = $request->getSchemeAndHttpHost() . $request->getPathInfo();
        $query = $request->query->all();
        $query['page'] = $page;
        
        // Убираем параметры фильтров из canonical/prev/next
        unset($query['sort'], $query['filter'], $query['duration']);
        
        return $baseUrl . '?' . http_build_query($query);
    }

    private function buildUrlWithoutPage($request): string
    {
        $baseUrl = $request->getSchemeAndHttpHost() . $request->getPathInfo();
        $query = $request->query->all();
        unset($query['page'], $query['sort'], $query['filter'], $query['duration']);
        
        if (empty($query)) {
            return $baseUrl;
        }
        
        return $baseUrl . '?' . http_build_query($query);
    }
}
