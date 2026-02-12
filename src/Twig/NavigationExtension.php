<?php

declare(strict_types=1);

namespace App\Twig;

use App\Repository\CategoryRepository;
use App\Repository\ChannelRepository;
use App\Repository\ModelProfileRepository;
use App\Service\GeoIpService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class NavigationExtension extends AbstractExtension
{
    private const CACHE_TTL = 3600; // 1 час

    public function __construct(
        private readonly CategoryRepository $categoryRepository,
        private readonly ModelProfileRepository $modelProfileRepository,
        private readonly ChannelRepository $channelRepository,
        private readonly CacheInterface $cache,
        private readonly Security $security,
        private readonly GeoIpService $geoIpService,
        private readonly RequestStack $requestStack,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('popular_categories', [$this, 'getPopularCategories']),
            new TwigFunction('popular_models', [$this, 'getPopularModels']),
            new TwigFunction('popular_channels', [$this, 'getPopularChannels']),
            new TwigFunction('user_country_info', [$this, 'getUserCountryInfo']),
        ];
    }

    public function getPopularCategories(int $limit = 10): array
    {
        return $this->cache->get('nav_popular_categories_' . $limit, function (ItemInterface $item) use ($limit) {
            $item->expiresAfter(self::CACHE_TTL);
            
            return $this->categoryRepository->createQueryBuilder('c')
                ->where('c.isActive = :active')
                ->setParameter('active', true)
                ->orderBy('c.videosCount', 'DESC')
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();
        });
    }

    public function getPopularModels(int $limit = 10): array
    {
        return $this->cache->get('nav_popular_models_' . $limit, function (ItemInterface $item) use ($limit) {
            $item->expiresAfter(self::CACHE_TTL);
            
            return $this->modelProfileRepository->findPopular($limit);
        });
    }

    public function getPopularChannels(int $limit = 10): array
    {
        return $this->cache->get('nav_popular_channels_' . $limit, function (ItemInterface $item) use ($limit) {
            $item->expiresAfter(self::CACHE_TTL);
            
            return $this->channelRepository->findPopular($limit);
        });
    }

    /**
     * Получить информацию о стране пользователя
     * Приоритет:
     * 1. Для авторизованных: countryCode из профиля (если установлен вручную) или country
     * 2. Для всех: определение по GeoIP
     * 3. Для незарегистрированных: null (не показывать страну)
     * 
     * @return array{code: string, name: string, flag_url: string}|null
     */
    public function getUserCountryInfo(): ?array
    {
        $user = $this->security->getUser();
        
        // Если пользователь авторизован
        if ($user && method_exists($user, 'getCountryCode')) {
            // Проверяем, установлена ли страна вручную
            $countryManuallySet = method_exists($user, 'isCountryManuallySet') && $user->isCountryManuallySet();
            
            // Если страна установлена вручную, используем её
            if ($countryManuallySet && $user->getCountryCode()) {
                $countryCode = strtolower($user->getCountryCode());
                $countryName = $this->getCountryName($countryCode);
                
                return [
                    'code' => $countryCode,
                    'name' => $countryName,
                    'flag_url' => "https://flagcdn.com/w20/{$countryCode}.png",
                ];
            }
            
            // Если есть country (название страны), используем его
            if (method_exists($user, 'getCountry') && $user->getCountry()) {
                $countryCode = strtolower($user->getCountry());
                $countryName = $this->getCountryName($countryCode);
                
                return [
                    'code' => $countryCode,
                    'name' => $countryName,
                    'flag_url' => "https://flagcdn.com/w20/{$countryCode}.png",
                ];
            }
        }
        
        // Определяем страну по GeoIP для всех пользователей
        $request = $this->requestStack->getCurrentRequest();
        if ($request) {
            $ipAddress = $request->getClientIp();
            if ($ipAddress) {
                $countryCode = $this->geoIpService->getCountryCode($ipAddress);
                if ($countryCode) {
                    $countryName = $this->getCountryName($countryCode);
                    
                    return [
                        'code' => $countryCode,
                        'name' => $countryName,
                        'flag_url' => "https://flagcdn.com/w20/{$countryCode}.png",
                    ];
                }
            }
        }
        
        // Для незарегистрированных пользователей без GeoIP - не показываем страну
        return null;
    }

    /**
     * Получить название страны по коду
     */
    private function getCountryName(string $code): string
    {
        $countries = [
            'ru' => 'России',
            'us' => 'США',
            'ua' => 'Украины',
            'by' => 'Беларуси',
            'kz' => 'Казахстана',
            'de' => 'Германии',
            'fr' => 'Франции',
            'gb' => 'Великобритании',
            'it' => 'Италии',
            'es' => 'Испании',
            'pl' => 'Польши',
            'tr' => 'Турции',
            'cn' => 'Китая',
            'jp' => 'Японии',
            'br' => 'Бразилии',
            'ca' => 'Канады',
            'au' => 'Австралии',
            'in' => 'Индии',
            'mx' => 'Мексики',
            'ar' => 'Аргентины',
        ];
        
        return $countries[$code] ?? strtoupper($code);
    }
}
