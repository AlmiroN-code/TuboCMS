<?php

namespace App\Twig;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig расширение для генерации canonical URL
 * Убирает query параметры (кроме page) и нормализует URL
 */
class CanonicalUrlExtension extends AbstractExtension
{
    public function __construct(
        private RequestStack $requestStack,
        private UrlGeneratorInterface $urlGenerator
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('canonical_url', [$this, 'getCanonicalUrl']),
        ];
    }

    /**
     * Генерирует canonical URL для текущей страницы
     * - Убирает лишние query параметры (sort, filter и т.д.)
     * - Для page=1 убирает параметр page
     * - Возвращает абсолютный URL
     */
    public function getCanonicalUrl(?string $customUrl = null): string
    {
        if ($customUrl) {
            return $customUrl;
        }

        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return '';
        }

        $route = $request->attributes->get('_route');
        $routeParams = $request->attributes->get('_route_params', []);

        // Если нет маршрута, возвращаем fallback URL
        if (!$route) {
            return $request->getSchemeAndHttpHost() . $request->getPathInfo();
        }

        // Параметры которые допустимы в canonical URL
        $allowedParams = ['slug', 'id', 'username', 'page'];
        
        // Фильтруем параметры роута
        $canonicalParams = [];
        foreach ($routeParams as $key => $value) {
            if (in_array($key, $allowedParams)) {
                // Убираем page=1 (это дефолтное значение)
                if ($key === 'page' && $value == 1) {
                    continue;
                }
                $canonicalParams[$key] = $value;
            }
        }

        // Проверяем query параметр page
        $page = $request->query->get('page');
        if ($page && $page > 1 && !isset($canonicalParams['page'])) {
            $canonicalParams['page'] = $page;
        }

        try {
            return $this->urlGenerator->generate($route, $canonicalParams, UrlGeneratorInterface::ABSOLUTE_URL);
        } catch (\Exception $e) {
            // Fallback: возвращаем текущий URL без query параметров
            return $request->getSchemeAndHttpHost() . $request->getPathInfo();
        }
    }
}
