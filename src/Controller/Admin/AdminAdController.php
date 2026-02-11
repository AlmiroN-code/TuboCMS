<?php

namespace App\Controller\Admin;

use App\Entity\Ad;
use App\Entity\AdPlacement;
use App\Entity\AdCampaign;
use App\Entity\AdSegment;
use App\Entity\AdAbTest;
use App\Repository\AdRepository;
use App\Repository\AdPlacementRepository;
use App\Repository\AdCampaignRepository;
use App\Repository\AdSegmentRepository;
use App\Repository\AdAbTestRepository;
use App\Repository\AdStatisticRepository;
use App\Repository\CategoryRepository;
use App\Service\AdService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[Route('/admin/ads')]
#[IsGranted('ROLE_ADMIN')]
class AdminAdController extends AbstractController
{
    public function __construct(
        private AdRepository $adRepository,
        private AdPlacementRepository $placementRepository,
        private AdCampaignRepository $campaignRepository,
        private AdSegmentRepository $segmentRepository,
        private AdAbTestRepository $abTestRepository,
        private AdStatisticRepository $statisticRepository,
        private CategoryRepository $categoryRepository,
        private EntityManagerInterface $em,
        private AdService $adService
    ) {
    }

    #[Route('', name: 'admin_ads')]
    public function index(Request $request): Response
    {
        $stats = $this->adService->getDashboardStats();
        $campaignStats = $this->campaignRepository->getStatsSummary();
        $dailyStats = $this->statisticRepository->getDailyStats(30);
        
        return $this->render('admin/ads/index.html.twig', [
            'stats' => $stats,
            'campaignStats' => $campaignStats,
            'dailyStats' => $dailyStats,
            'recentAds' => $this->adRepository->findForAdminList(5, 0),
            'activeCampaigns' => $this->campaignRepository->findActive(),
            'runningTests' => $this->abTestRepository->findRunning(),
        ]);
    }

    // ==================== ОБЪЯВЛЕНИЯ ====================

    #[Route('/list', name: 'admin_ads_list')]
    public function adsList(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = $request->query->getInt('per_page', 20);
        
        $allowedPerPage = [15, 25, 50, 100];
        if (!in_array($perPage, $allowedPerPage)) {
            $perPage = 20;
        }
        
        $limit = $perPage;
        $offset = ($page - 1) * $limit;
        $status = $request->query->get('status');
        $placementId = $request->query->getInt('placement');

        $ads = $this->adRepository->findForAdminList($limit, $offset, $status, $placementId ?: null);
        $total = $this->adRepository->countForAdminList($status, $placementId ?: null);

        return $this->render('admin/ads/ads/list.html.twig', [
            'ads' => $ads,
            'placements' => $this->placementRepository->findAllOrdered(),
            'currentPage' => $page,
            'perPage' => $perPage,
            'totalPages' => ceil($total / $limit),
            'total' => $total,
            'status' => $status,
            'placementId' => $placementId,
        ]);
    }

    #[Route('/new', name: 'admin_ads_new')]
    public function newAd(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            return $this->handleAdSave($request, new Ad());
        }

        return $this->render('admin/ads/ads/form.html.twig', [
            'ad' => null,
            'placements' => $this->placementRepository->findAllOrdered(),
            'campaigns' => $this->campaignRepository->findAllOrdered(),
            'segments' => $this->segmentRepository->findActive(),
            'categories' => $this->categoryRepository->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_ads_edit', requirements: ['id' => '\d+'])]
    public function editAd(Request $request, int $id): Response
    {
        $ad = $this->adRepository->find($id);
        if (!$ad) {
            throw $this->createNotFoundException('Объявление не найдено');
        }

        if ($request->isMethod('POST')) {
            return $this->handleAdSave($request, $ad);
        }

        return $this->render('admin/ads/ads/form.html.twig', [
            'ad' => $ad,
            'placements' => $this->placementRepository->findAllOrdered(),
            'campaigns' => $this->campaignRepository->findAllOrdered(),
            'segments' => $this->segmentRepository->findActive(),
            'categories' => $this->categoryRepository->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_ads_delete', methods: ['POST'])]
    public function deleteAd(int $id): Response
    {
        $ad = $this->adRepository->find($id);
        if ($ad) {
            $this->em->remove($ad);
            $this->em->flush();
            $this->addFlash('success', 'Объявление удалено');
        }

        return $this->redirectToRoute('admin_ads_list');
    }

    #[Route('/{id}/toggle', name: 'admin_ads_toggle', methods: ['POST'])]
    public function toggleAd(int $id): Response
    {
        $ad = $this->adRepository->find($id);
        if (!$ad) {
            $this->addFlash('error', 'Объявление не найдено');
            return $this->redirectToRoute('admin_ads_list');
        }

        $ad->setIsActive(!$ad->isActive());
        $ad->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();

        $this->addFlash('success', $ad->isActive() ? 'Объявление активировано' : 'Объявление деактивировано');
        return $this->redirectToRoute('admin_ads_list');
    }

    #[Route('/{id}/pause', name: 'admin_ads_pause', methods: ['POST'])]
    public function pauseAd(int $id): Response
    {
        $ad = $this->adRepository->find($id);
        if (!$ad) {
            $this->addFlash('error', 'Объявление не найдено');
            return $this->redirectToRoute('admin_ads_list');
        }

        $ad->setStatus(Ad::STATUS_PAUSED);
        $ad->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();

        $this->addFlash('success', 'Объявление приостановлено');
        return $this->redirectToRoute('admin_ads_list');
    }

    #[Route('/{id}/resume', name: 'admin_ads_resume', methods: ['POST'])]
    public function resumeAd(int $id): Response
    {
        $ad = $this->adRepository->find($id);
        if (!$ad) {
            $this->addFlash('error', 'Объявление не найдено');
            return $this->redirectToRoute('admin_ads_list');
        }

        $ad->setStatus(Ad::STATUS_ACTIVE);
        $ad->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();

        $this->addFlash('success', 'Объявление возобновлено');
        return $this->redirectToRoute('admin_ads_list');
    }

    #[Route('/{id}/duplicate', name: 'admin_ads_duplicate', methods: ['POST'])]
    public function duplicateAd(int $id): Response
    {
        $originalAd = $this->adRepository->find($id);
        if (!$originalAd) {
            $this->addFlash('error', 'Объявление не найдено');
            return $this->redirectToRoute('admin_ads_list');
        }

        $newAd = new Ad();
        $newAd->setName($originalAd->getName() . ' (копия)');
        $newAd->setDescription($originalAd->getDescription());
        $newAd->setFormat($originalAd->getFormat());
        $newAd->setStatus(Ad::STATUS_DRAFT);
        $newAd->setImageUrl($originalAd->getImageUrl());
        $newAd->setVideoUrl($originalAd->getVideoUrl());
        $newAd->setVastUrl($originalAd->getVastUrl());
        $newAd->setHtmlContent($originalAd->getHtmlContent());
        $newAd->setScriptCode($originalAd->getScriptCode());
        $newAd->setClickUrl($originalAd->getClickUrl());
        $newAd->setAltText($originalAd->getAltText());
        $newAd->setIsActive(false);
        $newAd->setOpenInNewTab($originalAd->isOpenInNewTab());
        $newAd->setPriority($originalAd->getPriority());
        $newAd->setWeight($originalAd->getWeight());
        $newAd->setBudget($originalAd->getBudget());
        $newAd->setCpm($originalAd->getCpm());
        $newAd->setCpc($originalAd->getCpc());
        $newAd->setImpressionLimit($originalAd->getImpressionLimit());
        $newAd->setClickLimit($originalAd->getClickLimit());
        $newAd->setPlacement($originalAd->getPlacement());
        $newAd->setCampaign($originalAd->getCampaign());
        $newAd->setGeoTargeting($originalAd->getGeoTargeting());
        $newAd->setTimeTargeting($originalAd->getTimeTargeting());
        $newAd->setDeviceTargeting($originalAd->getDeviceTargeting());
        $newAd->setCategoryTargeting($originalAd->getCategoryTargeting());

        $this->em->persist($newAd);
        $this->em->flush();

        $this->addFlash('success', 'Объявление продублировано');
        return $this->redirectToRoute('admin_ads_edit', ['id' => $newAd->getId()]);
    }

    #[Route('/{id}/reset-stats', name: 'admin_ads_reset_stats', methods: ['POST'])]
    public function resetAdStats(int $id): Response
    {
        $ad = $this->adRepository->find($id);
        if (!$ad) {
            $this->addFlash('error', 'Объявление не найдено');
            return $this->redirectToRoute('admin_ads_list');
        }

        // Сброс счетчиков в объявлении
        $ad->setImpressionsCount(0);
        $ad->setUniqueImpressionsCount(0);
        $ad->setClicksCount(0);
        $ad->setUniqueClicksCount(0);
        $ad->setSpentAmount('0');
        $ad->setUpdatedAt(new \DateTimeImmutable());

        // Удаление статистики
        $statistics = $this->statisticRepository->findBy(['ad' => $ad]);
        foreach ($statistics as $stat) {
            $this->em->remove($stat);
        }

        $this->em->flush();

        $this->addFlash('success', 'Статистика объявления сброшена');
        return $this->redirectToRoute('admin_ads_list');
    }

    #[Route('/bulk-action', name: 'admin_ads_bulk_action', methods: ['POST'])]
    public function bulkAction(Request $request): Response
    {
        $ids = $request->request->all('ids');
        $action = $request->request->get('action');

        if (empty($ids)) {
            $this->addFlash('error', 'Не выбрано ни одного объявления');
            return $this->redirectToRoute('admin_ads_list');
        }

        $ads = $this->adRepository->findBy(['id' => $ids]);
        $count = count($ads);

        switch ($action) {
            case 'activate':
                foreach ($ads as $ad) {
                    $ad->setIsActive(true);
                    $ad->setUpdatedAt(new \DateTimeImmutable());
                }
                $this->addFlash('success', "Активировано объявлений: $count");
                break;

            case 'deactivate':
                foreach ($ads as $ad) {
                    $ad->setIsActive(false);
                    $ad->setUpdatedAt(new \DateTimeImmutable());
                }
                $this->addFlash('success', "Деактивировано объявлений: $count");
                break;

            case 'pause':
                foreach ($ads as $ad) {
                    $ad->setStatus(Ad::STATUS_PAUSED);
                    $ad->setUpdatedAt(new \DateTimeImmutable());
                }
                $this->addFlash('success', "Приостановлено объявлений: $count");
                break;

            case 'delete':
                foreach ($ads as $ad) {
                    $this->em->remove($ad);
                }
                $this->addFlash('success', "Удалено объявлений: $count");
                break;

            default:
                $this->addFlash('error', 'Неизвестное действие');
                return $this->redirectToRoute('admin_ads_list');
        }

        $this->em->flush();
        return $this->redirectToRoute('admin_ads_list');
    }

    #[Route('/{id}/stats', name: 'admin_ads_stats', requirements: ['id' => '\d+'])]
    public function adStats(Request $request, int $id): Response
    {
        $ad = $this->adRepository->find($id);
        if (!$ad) {
            throw $this->createNotFoundException('Объявление не найдено');
        }

        $days = $request->query->getInt('days', 30);
        $startDate = new \DateTime("-{$days} days");
        $endDate = new \DateTime();

        $dailyStats = $this->statisticRepository->findByAdAndDateRange($ad, $startDate, $endDate);
        $aggregated = $this->statisticRepository->getAggregatedStats($ad, $days);

        return $this->render('admin/ads/ads/stats.html.twig', [
            'ad' => $ad,
            'dailyStats' => $dailyStats,
            'aggregated' => $aggregated,
            'days' => $days,
        ]);
    }

    private function handleAdSave(Request $request, Ad $ad): Response
    {
        $ad->setName($request->request->get('name'));
        $ad->setDescription($request->request->get('description'));
        $ad->setFormat($request->request->get('format'));
        $ad->setStatus($request->request->get('status'));
        $ad->setClickUrl($request->request->get('click_url'));
        $ad->setAltText($request->request->get('alt_text'));
        $ad->setIsActive($request->request->getBoolean('is_active'));
        $ad->setOpenInNewTab($request->request->getBoolean('open_in_new_tab', true));
        $ad->setPriority($request->request->getInt('priority', 0));
        $ad->setWeight($request->request->getInt('weight', 100));

        // Контент в зависимости от формата
        $ad->setImageUrl($request->request->get('image_url'));
        $ad->setVideoUrl($request->request->get('video_url'));
        $ad->setVastUrl($request->request->get('vast_url'));
        $ad->setHtmlContent($request->request->get('html_content'));
        $ad->setScriptCode($request->request->get('script_code'));

        // Даты
        if ($startDate = $request->request->get('start_date')) {
            $ad->setStartDate(new \DateTime($startDate));
        }
        if ($endDate = $request->request->get('end_date')) {
            $ad->setEndDate(new \DateTime($endDate));
        }

        // Бюджет и ставки
        $ad->setBudget($request->request->get('budget') ?: null);
        $ad->setCpm($request->request->get('cpm') ?: null);
        $ad->setCpc($request->request->get('cpc') ?: null);

        // Лимиты
        $impressionLimit = $request->request->get('impression_limit');
        $ad->setImpressionLimit($impressionLimit !== '' && $impressionLimit !== null ? (int)$impressionLimit : null);
        
        $clickLimit = $request->request->get('click_limit');
        $ad->setClickLimit($clickLimit !== '' && $clickLimit !== null ? (int)$clickLimit : null);
        
        $dailyImpressionLimit = $request->request->get('daily_impression_limit');
        $ad->setDailyImpressionLimit($dailyImpressionLimit !== '' && $dailyImpressionLimit !== null ? (int)$dailyImpressionLimit : null);
        
        $dailyClickLimit = $request->request->get('daily_click_limit');
        $ad->setDailyClickLimit($dailyClickLimit !== '' && $dailyClickLimit !== null ? (int)$dailyClickLimit : null);

        // Связи
        $placementId = $request->request->getInt('placement_id');
        $placement = $this->placementRepository->find($placementId);
        $ad->setPlacement($placement);

        $campaignId = $request->request->getInt('campaign_id');
        if ($campaignId) {
            $campaign = $this->campaignRepository->find($campaignId);
            $ad->setCampaign($campaign);
        }

        // Таргетинг
        $geoCountries = $request->request->all('geo_countries');
        $ad->setGeoTargeting(['countries' => array_filter($geoCountries)]);

        $timeHours = $request->request->all('time_hours');
        $timeDays = $request->request->all('time_days');
        $ad->setTimeTargeting([
            'hours' => array_map('intval', array_filter($timeHours)),
            'days' => array_map('intval', array_filter($timeDays)),
        ]);

        $devices = $request->request->all('devices');
        $ad->setDeviceTargeting(array_filter($devices));

        $categories = $request->request->all('category_targeting');
        $ad->setCategoryTargeting(array_map('intval', array_filter($categories)));

        // Сегменты
        $segmentIds = $request->request->all('segments');
        foreach ($ad->getSegments()->toArray() as $segment) {
            $ad->removeSegment($segment);
        }
        foreach ($segmentIds as $segmentId) {
            $segment = $this->segmentRepository->find($segmentId);
            if ($segment) {
                $ad->addSegment($segment);
            }
        }

        if (!$ad->getId()) {
            $ad->setCreatedBy($this->getUser());
        }
        $ad->setUpdatedAt(new \DateTimeImmutable());

        $this->em->persist($ad);
        $this->em->flush();

        $this->addFlash('success', 'Объявление сохранено');
        return $this->redirectToRoute('admin_ads_list');
    }

    // ==================== МЕСТА РАЗМЕЩЕНИЯ ====================

    #[Route('/placements', name: 'admin_ads_placements')]
    public function placements(): Response
    {
        return $this->render('admin/ads/placements/list.html.twig', [
            'placements' => $this->placementRepository->findAllOrdered(),
        ]);
    }

    #[Route('/placements/new', name: 'admin_ads_placements_new')]
    public function newPlacement(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            return $this->handlePlacementSave($request, new AdPlacement());
        }

        return $this->render('admin/ads/placements/form.html.twig', [
            'placement' => null,
        ]);
    }

    #[Route('/placements/{id}/edit', name: 'admin_ads_placements_edit')]
    public function editPlacement(Request $request, int $id): Response
    {
        $placement = $this->placementRepository->find($id);
        if (!$placement) {
            throw $this->createNotFoundException('Место размещения не найдено');
        }

        if ($request->isMethod('POST')) {
            return $this->handlePlacementSave($request, $placement);
        }

        return $this->render('admin/ads/placements/form.html.twig', [
            'placement' => $placement,
        ]);
    }

    #[Route('/placements/{id}/delete', name: 'admin_ads_placements_delete', methods: ['POST'])]
    public function deletePlacement(int $id): Response
    {
        $placement = $this->placementRepository->find($id);
        if ($placement && $placement->getAds()->isEmpty()) {
            $this->em->remove($placement);
            $this->em->flush();
            $this->addFlash('success', 'Место размещения удалено');
        } else {
            $this->addFlash('error', 'Невозможно удалить: есть связанные объявления');
        }

        return $this->redirectToRoute('admin_ads_placements');
    }

    private function handlePlacementSave(Request $request, AdPlacement $placement): Response
    {
        $placement->setName($request->request->get('name'));
        $placement->setDescription($request->request->get('description'));
        $placement->setType($request->request->get('type'));
        $placement->setPosition($request->request->get('position'));
        $placement->setWidth($request->request->getInt('width') ?: null);
        $placement->setHeight($request->request->getInt('height') ?: null);
        $placement->setIsActive($request->request->getBoolean('is_active'));
        $placement->setOrderPosition($request->request->getInt('order_position', 0));

        $slugger = new AsciiSlugger();
        $slug = $request->request->get('slug') ?: $slugger->slug($placement->getName())->lower();
        $placement->setSlug($slug);

        $allowedPages = $request->request->all('allowed_pages');
        $placement->setAllowedPages(array_filter($allowedPages));

        $placement->setUpdatedAt(new \DateTimeImmutable());

        $this->em->persist($placement);
        $this->em->flush();

        $this->addFlash('success', 'Место размещения сохранено');
        return $this->redirectToRoute('admin_ads_placements');
    }

    // ==================== КАМПАНИИ ====================

    #[Route('/campaigns', name: 'admin_ads_campaigns')]
    public function campaigns(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = $request->query->getInt('per_page', 20);
        
        $allowedPerPage = [15, 25, 50, 100];
        if (!in_array($perPage, $allowedPerPage)) {
            $perPage = 20;
        }
        
        $limit = $perPage;
        $offset = ($page - 1) * $limit;
        $status = $request->query->get('status');

        $campaigns = $this->campaignRepository->findPaginated($limit, $offset, $status);
        $total = $this->campaignRepository->countAll($status);

        return $this->render('admin/ads/campaigns/list.html.twig', [
            'campaigns' => $campaigns,
            'currentPage' => $page,
            'perPage' => $perPage,
            'totalPages' => ceil($total / $limit),
            'total' => $total,
            'status' => $status,
        ]);
    }

    #[Route('/campaigns/new', name: 'admin_ads_campaigns_new')]
    public function newCampaign(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            return $this->handleCampaignSave($request, new AdCampaign());
        }

        return $this->render('admin/ads/campaigns/form.html.twig', [
            'campaign' => null,
        ]);
    }

    #[Route('/campaigns/{id}/edit', name: 'admin_ads_campaigns_edit')]
    public function editCampaign(Request $request, int $id): Response
    {
        $campaign = $this->campaignRepository->find($id);
        if (!$campaign) {
            throw $this->createNotFoundException('Кампания не найдена');
        }

        if ($request->isMethod('POST')) {
            return $this->handleCampaignSave($request, $campaign);
        }

        return $this->render('admin/ads/campaigns/form.html.twig', [
            'campaign' => $campaign,
            'ads' => $this->adRepository->findByCampaign($id),
        ]);
    }

    #[Route('/campaigns/{id}/delete', name: 'admin_ads_campaigns_delete', methods: ['POST'])]
    public function deleteCampaign(int $id): Response
    {
        $campaign = $this->campaignRepository->find($id);
        if ($campaign) {
            $this->em->remove($campaign);
            $this->em->flush();
            $this->addFlash('success', 'Кампания удалена');
        }

        return $this->redirectToRoute('admin_ads_campaigns');
    }

    private function handleCampaignSave(Request $request, AdCampaign $campaign): Response
    {
        $campaign->setName($request->request->get('name'));
        $campaign->setDescription($request->request->get('description'));
        $campaign->setStatus($request->request->get('status'));
        $campaign->setTotalBudget($request->request->get('total_budget') ?: null);
        $campaign->setDailyBudget($request->request->get('daily_budget') ?: null);

        if ($startDate = $request->request->get('start_date')) {
            $campaign->setStartDate(new \DateTime($startDate));
        }
        if ($endDate = $request->request->get('end_date')) {
            $campaign->setEndDate(new \DateTime($endDate));
        }

        if (!$campaign->getId()) {
            $campaign->setCreatedBy($this->getUser());
        }
        $campaign->setUpdatedAt(new \DateTimeImmutable());

        $this->em->persist($campaign);
        $this->em->flush();

        $this->addFlash('success', 'Кампания сохранена');
        return $this->redirectToRoute('admin_ads_campaigns');
    }

    // ==================== СЕГМЕНТЫ ====================

    #[Route('/segments', name: 'admin_ads_segments')]
    public function segments(): Response
    {
        return $this->render('admin/ads/segments/list.html.twig', [
            'segments' => $this->segmentRepository->findAllOrdered(),
        ]);
    }

    #[Route('/segments/new', name: 'admin_ads_segments_new')]
    public function newSegment(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            return $this->handleSegmentSave($request, new AdSegment());
        }

        return $this->render('admin/ads/segments/form.html.twig', [
            'segment' => null,
        ]);
    }

    #[Route('/segments/{id}/edit', name: 'admin_ads_segments_edit')]
    public function editSegment(Request $request, int $id): Response
    {
        $segment = $this->segmentRepository->find($id);
        if (!$segment) {
            throw $this->createNotFoundException('Сегмент не найден');
        }

        if ($request->isMethod('POST')) {
            return $this->handleSegmentSave($request, $segment);
        }

        return $this->render('admin/ads/segments/form.html.twig', [
            'segment' => $segment,
        ]);
    }

    #[Route('/segments/{id}/delete', name: 'admin_ads_segments_delete', methods: ['POST'])]
    public function deleteSegment(int $id): Response
    {
        $segment = $this->segmentRepository->find($id);
        if ($segment) {
            $this->em->remove($segment);
            $this->em->flush();
            $this->addFlash('success', 'Сегмент удалён');
        }

        return $this->redirectToRoute('admin_ads_segments');
    }

    private function handleSegmentSave(Request $request, AdSegment $segment): Response
    {
        $segment->setName($request->request->get('name'));
        $segment->setDescription($request->request->get('description'));
        $segment->setType($request->request->get('type'));
        $segment->setIsActive($request->request->getBoolean('is_active'));

        $slugger = new AsciiSlugger();
        $slug = $request->request->get('slug') ?: $slugger->slug($segment->getName())->lower();
        $segment->setSlug($slug);

        // Правила сегментации (JSON)
        $rulesJson = $request->request->get('rules');
        if ($rulesJson) {
            $rules = json_decode($rulesJson, true);
            $segment->setRules($rules ?: []);
        }

        $segment->setUpdatedAt(new \DateTimeImmutable());

        $this->em->persist($segment);
        $this->em->flush();

        $this->addFlash('success', 'Сегмент сохранён');
        return $this->redirectToRoute('admin_ads_segments');
    }

    // ==================== A/B ТЕСТЫ ====================

    #[Route('/ab-tests', name: 'admin_ads_abtests')]
    public function abTests(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = $request->query->getInt('per_page', 20);
        
        $allowedPerPage = [15, 25, 50, 100];
        if (!in_array($perPage, $allowedPerPage)) {
            $perPage = 20;
        }
        
        $limit = $perPage;
        $offset = ($page - 1) * $limit;
        $status = $request->query->get('status');

        $tests = $this->abTestRepository->findPaginated($limit, $offset, $status);
        $total = $this->abTestRepository->countAll($status);

        return $this->render('admin/ads/abtests/list.html.twig', [
            'tests' => $tests,
            'currentPage' => $page,
            'perPage' => $perPage,
            'totalPages' => ceil($total / $limit),
            'total' => $total,
            'status' => $status,
        ]);
    }

    #[Route('/ab-tests/new', name: 'admin_ads_abtests_new')]
    public function newAbTest(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            return $this->handleAbTestSave($request, new AdAbTest());
        }

        return $this->render('admin/ads/abtests/form.html.twig', [
            'test' => null,
            'ads' => $this->adRepository->findForAdminList(100, 0),
        ]);
    }

    #[Route('/ab-tests/{id}/edit', name: 'admin_ads_abtests_edit')]
    public function editAbTest(Request $request, int $id): Response
    {
        $test = $this->abTestRepository->find($id);
        if (!$test) {
            throw $this->createNotFoundException('A/B тест не найден');
        }

        if ($request->isMethod('POST')) {
            return $this->handleAbTestSave($request, $test);
        }

        return $this->render('admin/ads/abtests/form.html.twig', [
            'test' => $test,
            'ads' => $this->adRepository->findForAdminList(100, 0),
        ]);
    }

    #[Route('/ab-tests/{id}/delete', name: 'admin_ads_abtests_delete', methods: ['POST'])]
    public function deleteAbTest(int $id): Response
    {
        $test = $this->abTestRepository->find($id);
        if ($test) {
            $this->em->remove($test);
            $this->em->flush();
            $this->addFlash('success', 'A/B тест удалён');
        }

        return $this->redirectToRoute('admin_ads_abtests');
    }

    #[Route('/ab-tests/{id}/results', name: 'admin_ads_abtests_results')]
    public function abTestResults(int $id): Response
    {
        $test = $this->abTestRepository->find($id);
        if (!$test) {
            throw $this->createNotFoundException('A/B тест не найден');
        }

        $variantA = $test->getVariantA();
        $variantB = $test->getVariantB();

        $statsA = $variantA ? $this->statisticRepository->getAggregatedStats($variantA) : null;
        $statsB = $variantB ? $this->statisticRepository->getAggregatedStats($variantB) : null;

        return $this->render('admin/ads/abtests/results.html.twig', [
            'test' => $test,
            'variantA' => $variantA,
            'variantB' => $variantB,
            'statsA' => $statsA,
            'statsB' => $statsB,
        ]);
    }

    private function handleAbTestSave(Request $request, AdAbTest $test): Response
    {
        $test->setName($request->request->get('name'));
        $test->setDescription($request->request->get('description'));
        $test->setStatus($request->request->get('status'));
        $test->setTrafficSplitA($request->request->getInt('traffic_split_a', 50));
        $test->setTrafficSplitB($request->request->getInt('traffic_split_b', 50));
        $test->setWinnerMetric($request->request->get('winner_metric', 'ctr'));

        if ($startDate = $request->request->get('start_date')) {
            $test->setStartDate(new \DateTime($startDate));
        }
        if ($endDate = $request->request->get('end_date')) {
            $test->setEndDate(new \DateTime($endDate));
        }

        // Привязка объявлений к вариантам
        $adIdA = $request->request->getInt('ad_variant_a');
        $adIdB = $request->request->getInt('ad_variant_b');

        if ($adIdA) {
            $adA = $this->adRepository->find($adIdA);
            if ($adA) {
                $adA->setAbTest($test);
                $adA->setAbTestVariant('A');
            }
        }

        if ($adIdB) {
            $adB = $this->adRepository->find($adIdB);
            if ($adB) {
                $adB->setAbTest($test);
                $adB->setAbTestVariant('B');
            }
        }

        if (!$test->getId()) {
            $test->setCreatedBy($this->getUser());
        }
        $test->setUpdatedAt(new \DateTimeImmutable());

        $this->em->persist($test);
        $this->em->flush();

        $this->addFlash('success', 'A/B тест сохранён');
        return $this->redirectToRoute('admin_ads_abtests');
    }

    // ==================== СТАТИСТИКА ====================

    #[Route('/statistics', name: 'admin_ads_statistics')]
    public function statistics(Request $request): Response
    {
        $days = $request->query->getInt('days', 30);
        
        $dailyStats = $this->statisticRepository->getDailyStats($days);
        $geoStats = $this->statisticRepository->getGeoStats($days);
        $topAds = $this->statisticRepository->getTopAdsByMetric('clicks', $days, 10);

        // Получаем объявления для топа
        $topAdsWithDetails = [];
        foreach ($topAds as $stat) {
            $ad = $this->adRepository->find($stat['adId']);
            if ($ad) {
                $topAdsWithDetails[] = [
                    'ad' => $ad,
                    'total' => $stat['total'],
                ];
            }
        }

        return $this->render('admin/ads/statistics/index.html.twig', [
            'dailyStats' => $dailyStats,
            'geoStats' => $geoStats,
            'topAds' => $topAdsWithDetails,
            'days' => $days,
        ]);
    }

    #[Route('/statistics/export', name: 'admin_ads_statistics_export')]
    public function exportStatistics(Request $request): Response
    {
        $days = $request->query->getInt('days', 30);
        $dailyStats = $this->statisticRepository->getDailyStats($days);

        $csv = "Дата,Показы,Клики,CTR,Расход,Доход\n";
        foreach ($dailyStats as $stat) {
            $ctr = $stat['impressions'] > 0 
                ? round(($stat['clicks'] / $stat['impressions']) * 100, 2) 
                : 0;
            $csv .= sprintf(
                "%s,%d,%d,%.2f%%,%.2f,%.2f\n",
                $stat['date']->format('Y-m-d'),
                $stat['impressions'],
                $stat['clicks'],
                $ctr,
                $stat['spent'],
                $stat['revenue']
            );
        }

        $response = new Response($csv);
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="ad_statistics.csv"');

        return $response;
    }
}
