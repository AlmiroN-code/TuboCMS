<?php

namespace App\Service;

use App\Entity\Ad;
use App\Entity\AdPlacement;
use App\Entity\AdStatistic;
use App\Repository\AdRepository;
use App\Repository\AdPlacementRepository;
use App\Repository\AdStatisticRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class AdService
{
    // Кэш на уровне запроса — один запрос к БД на весь HTTP request
    private ?array $placementsCache = null;
    private array $adsCache = [];
    private bool $dataLoaded = false;

    public function __construct(
        private AdRepository $adRepository,
        private AdPlacementRepository $placementRepository,
        private AdStatisticRepository $statisticRepository,
        private EntityManagerInterface $em,
        private RequestStack $requestStack
    ) {
    }

    /**
     * Предзагрузка всех активных placements и ads одним запросом
     * Вызывается один раз за HTTP request
     */
    private function preloadAdsData(): void
    {
        if ($this->dataLoaded) {
            return;
        }

        $this->dataLoaded = true;
        $this->placementsCache = [];
        $this->adsCache = [];

        // Один запрос загружает все placements с их ads
        $placements = $this->placementRepository->findAllActiveWithAds();
        
        foreach ($placements as $placement) {
            $slug = $placement->getSlug();
            $this->placementsCache[$slug] = $placement;
            
            // Фильтруем активные ads в памяти
            $this->adsCache[$slug] = $placement->getAds()->filter(
                fn(Ad $ad) => $ad->isRunning()
            )->toArray();
        }
    }

    public function getAdForPlacement(string $placementSlug, array $context = []): ?Ad
    {
        $this->preloadAdsData();

        $placement = $this->placementsCache[$placementSlug] ?? null;
        if (!$placement || !$placement->isActive()) {
            return null;
        }

        $ads = $this->adsCache[$placementSlug] ?? [];
        if (empty($ads)) {
            return null;
        }

        // Фильтрация по таргетингу
        $filteredAds = $this->filterByTargeting($ads, $context);
        if (empty($filteredAds)) {
            return null;
        }

        // Выбор рекламы с учётом веса
        return $this->selectByWeight($filteredAds);
    }

    public function getAdsForPlacement(string $placementSlug, int $limit = 1, array $context = []): array
    {
        $this->preloadAdsData();

        $placement = $this->placementsCache[$placementSlug] ?? null;
        if (!$placement || !$placement->isActive()) {
            return [];
        }

        $ads = $this->adsCache[$placementSlug] ?? [];
        if (empty($ads)) {
            return [];
        }

        $filteredAds = $this->filterByTargeting($ads, $context);
        
        return array_slice($filteredAds, 0, $limit);
    }

    public function recordImpression(Ad $ad, ?string $userIp = null, ?string $userAgent = null): void
    {
        $ad->incrementImpressions();
        
        $stat = $this->statisticRepository->findOrCreateForToday($ad);
        $stat->incrementImpressions();
        
        // Проверка уникальности по IP
        $sessionKey = 'ad_impression_' . $ad->getId();
        $session = $this->requestStack->getSession();
        
        if (!$session->has($sessionKey)) {
            $ad->setUniqueImpressionsCount($ad->getUniqueImpressionsCount() + 1);
            $stat->setUniqueImpressions($stat->getUniqueImpressions() + 1);
            $session->set($sessionKey, true);
        }

        // Обновление почасовой статистики
        $hour = (int)(new \DateTime())->format('H');
        $hourlyData = $stat->getHourlyData() ?? [];
        $hourlyData[$hour] = ($hourlyData[$hour] ?? 0) + 1;
        $stat->setHourlyData($hourlyData);

        // Обновление гео-статистики
        if ($userIp) {
            $country = $this->getCountryByIp($userIp);
            if ($country) {
                $geoData = $stat->getGeoData() ?? [];
                if (!isset($geoData[$country])) {
                    $geoData[$country] = ['impressions' => 0, 'clicks' => 0];
                }
                $geoData[$country]['impressions']++;
                $stat->setGeoData($geoData);
            }
        }

        // Расчёт стоимости CPM
        if ($ad->getCpm()) {
            $cost = (float)$ad->getCpm() / 1000;
            $ad->setSpentAmount((string)((float)$ad->getSpentAmount() + $cost));
            $stat->setSpent((string)((float)$stat->getSpent() + $cost));
        }

        $this->em->persist($stat);
        $this->em->flush();
    }

    public function recordClick(Ad $ad, ?string $userIp = null): void
    {
        $ad->incrementClicks();
        
        $stat = $this->statisticRepository->findOrCreateForToday($ad);
        $stat->incrementClicks();
        
        // Проверка уникальности
        $sessionKey = 'ad_click_' . $ad->getId();
        $session = $this->requestStack->getSession();
        
        if (!$session->has($sessionKey)) {
            $ad->setUniqueClicksCount($ad->getUniqueClicksCount() + 1);
            $stat->setUniqueClicks($stat->getUniqueClicks() + 1);
            $session->set($sessionKey, true);
        }

        // Обновление гео-статистики
        if ($userIp) {
            $country = $this->getCountryByIp($userIp);
            if ($country) {
                $geoData = $stat->getGeoData() ?? [];
                if (!isset($geoData[$country])) {
                    $geoData[$country] = ['impressions' => 0, 'clicks' => 0];
                }
                $geoData[$country]['clicks']++;
                $stat->setGeoData($geoData);
            }
        }

        // Расчёт стоимости CPC
        if ($ad->getCpc()) {
            $cost = (float)$ad->getCpc();
            $ad->setSpentAmount((string)((float)$ad->getSpentAmount() + $cost));
            $stat->setSpent((string)((float)$stat->getSpent() + $cost));
        }

        $this->em->persist($stat);
        $this->em->flush();
    }

    public function recordConversion(Ad $ad, float $value = 0): void
    {
        $stat = $this->statisticRepository->findOrCreateForToday($ad);
        $stat->setConversions($stat->getConversions() + 1);
        
        if ($value > 0) {
            $stat->setRevenue((string)((float)$stat->getRevenue() + $value));
        }

        $this->em->persist($stat);
        $this->em->flush();
    }

    private function filterByTargeting(array $ads, array $context): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $userIp = $request?->getClientIp();
        $userAgent = $request?->headers->get('User-Agent');
        $currentHour = (int)(new \DateTime())->format('H');
        $currentDay = (int)(new \DateTime())->format('N');

        return array_filter($ads, function (Ad $ad) use ($context, $userIp, $userAgent, $currentHour, $currentDay) {
            // Проверка лимитов
            if ($ad->getImpressionLimit() && $ad->getImpressionsCount() >= $ad->getImpressionLimit()) {
                return false;
            }
            if ($ad->getClickLimit() && $ad->getClicksCount() >= $ad->getClickLimit()) {
                return false;
            }

            // Гео-таргетинг
            $geoTargeting = $ad->getGeoTargeting();
            if (!empty($geoTargeting['countries']) && $userIp) {
                $country = $this->getCountryByIp($userIp);
                if ($country && !in_array($country, $geoTargeting['countries'])) {
                    return false;
                }
            }

            // Временной таргетинг
            $timeTargeting = $ad->getTimeTargeting();
            if (!empty($timeTargeting)) {
                if (!empty($timeTargeting['hours']) && !in_array($currentHour, $timeTargeting['hours'])) {
                    return false;
                }
                if (!empty($timeTargeting['days']) && !in_array($currentDay, $timeTargeting['days'])) {
                    return false;
                }
            }

            // Таргетинг по устройствам
            $deviceTargeting = $ad->getDeviceTargeting();
            if (!empty($deviceTargeting) && $userAgent) {
                $device = $this->detectDevice($userAgent);
                if (!in_array($device, $deviceTargeting)) {
                    return false;
                }
            }

            // Таргетинг по категориям
            $categoryTargeting = $ad->getCategoryTargeting();
            if (!empty($categoryTargeting) && isset($context['category_id'])) {
                if (!in_array($context['category_id'], $categoryTargeting)) {
                    return false;
                }
            }

            return true;
        });
    }

    private function selectByWeight(array $ads): ?Ad
    {
        if (empty($ads)) {
            return null;
        }

        $totalWeight = array_sum(array_map(fn(Ad $ad) => $ad->getWeight(), $ads));
        if ($totalWeight === 0) {
            return $ads[array_rand($ads)];
        }

        $random = mt_rand(1, $totalWeight);
        $currentWeight = 0;

        foreach ($ads as $ad) {
            $currentWeight += $ad->getWeight();
            if ($random <= $currentWeight) {
                return $ad;
            }
        }

        return $ads[0];
    }

    private function getCountryByIp(?string $ip): ?string
    {
        // Простая заглушка - в реальном проекте использовать GeoIP
        // Можно интегрировать MaxMind GeoIP2 или аналог
        return null;
    }

    private function detectDevice(string $userAgent): string
    {
        $userAgent = strtolower($userAgent);
        
        if (preg_match('/(tablet|ipad|playbook|silk)|(android(?!.*mobile))/i', $userAgent)) {
            return 'tablet';
        }
        
        if (preg_match('/(mobile|iphone|ipod|android|blackberry|opera mini|iemobile)/i', $userAgent)) {
            return 'mobile';
        }
        
        return 'desktop';
    }

    public function getVastXml(Ad $ad): string
    {
        if ($ad->getFormat() !== Ad::FORMAT_VAST) {
            return '';
        }

        // Если есть внешний VAST URL - редирект
        if ($ad->getVastUrl()) {
            return $ad->getVastUrl();
        }

        // Генерация собственного VAST XML
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><VAST version="3.0"></VAST>');
        $adElement = $xml->addChild('Ad');
        $adElement->addAttribute('id', (string)$ad->getId());
        
        $inLine = $adElement->addChild('InLine');
        $inLine->addChild('AdSystem', 'RexTube Ads');
        $inLine->addChild('AdTitle', htmlspecialchars($ad->getName()));
        
        if ($ad->getVideoUrl()) {
            $creatives = $inLine->addChild('Creatives');
            $creative = $creatives->addChild('Creative');
            $linear = $creative->addChild('Linear');
            $mediaFiles = $linear->addChild('MediaFiles');
            $mediaFile = $mediaFiles->addChild('MediaFile', htmlspecialchars($ad->getVideoUrl()));
            $mediaFile->addAttribute('type', 'video/mp4');
            $mediaFile->addAttribute('delivery', 'progressive');
        }

        return $xml->asXML();
    }

    public function getDashboardStats(): array
    {
        return $this->adRepository->getStatsSummary();
    }
}
