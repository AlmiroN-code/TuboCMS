<?php

namespace App\Controller;

use App\Entity\Ad;
use App\Repository\AdRepository;
use App\Service\AdService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/ads')]
class AdController extends AbstractController
{
    public function __construct(
        private AdRepository $adRepository,
        private AdService $adService
    ) {
    }

    #[Route('/show/{placement}', name: 'ad_show', methods: ['GET'])]
    public function show(string $placement, Request $request): Response
    {
        $context = [
            'page' => $request->query->get('page'),
            'category_id' => $request->query->getInt('category_id'),
            'user_id' => $request->query->getInt('user_id'),
        ];

        $ad = $this->adService->getAdForPlacement($placement, $context);
        
        if (!$ad) {
            return new Response('', 204); // No Content
        }

        return $this->render('ads/display.html.twig', [
            'ad' => $ad,
            'placement' => $placement,
        ]);
    }

    #[Route('/click/{id}', name: 'ad_click', requirements: ['id' => '\d+'])]
    public function click(int $id, Request $request): Response
    {
        $ad = $this->adRepository->find($id);
        if (!$ad || !$ad->isActive()) {
            throw $this->createNotFoundException('Объявление не найдено');
        }

        // Записываем клик
        $userIp = $request->getClientIp();
        $this->adService->recordClick($ad, $userIp);

        // Редирект на целевую страницу
        if ($ad->getClickUrl()) {
            return $this->redirect($ad->getClickUrl());
        }

        return $this->redirectToRoute('app_home');
    }

    #[Route('/impression/{id}', name: 'ad_impression', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function impression(int $id, Request $request): JsonResponse
    {
        $ad = $this->adRepository->find($id);
        if (!$ad || !$ad->isActive()) {
            return new JsonResponse(['error' => 'Объявление не найдено'], 404);
        }

        // Записываем показ
        $userIp = $request->getClientIp();
        $userAgent = $request->headers->get('User-Agent');
        $this->adService->recordImpression($ad, $userIp, $userAgent);

        return new JsonResponse(['success' => true]);
    }

    #[Route('/conversion/{id}', name: 'ad_conversion', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function conversion(int $id, Request $request): JsonResponse
    {
        $ad = $this->adRepository->find($id);
        if (!$ad || !$ad->isActive()) {
            return new JsonResponse(['error' => 'Объявление не найдено'], 404);
        }

        $value = (float)$request->request->get('value', 0);
        $this->adService->recordConversion($ad, $value);

        return new JsonResponse(['success' => true]);
    }

    #[Route('/vast/{id}', name: 'ad_vast', requirements: ['id' => '\d+'])]
    public function vast(int $id): Response
    {
        $ad = $this->adRepository->find($id);
        if (!$ad || !$ad->isActive() || $ad->getFormat() !== Ad::FORMAT_VAST) {
            throw $this->createNotFoundException('VAST объявление не найдено');
        }

        $vastXml = $this->adService->getVastXml($ad);
        
        $response = new Response($vastXml);
        $response->headers->set('Content-Type', 'application/xml');
        
        return $response;
    }

    #[Route('/js/{placement}', name: 'ad_js')]
    public function javascript(string $placement, Request $request): Response
    {
        $context = [
            'page' => $request->query->get('page'),
            'category_id' => $request->query->getInt('category_id'),
            'user_id' => $request->query->getInt('user_id'),
        ];

        $ad = $this->adService->getAdForPlacement($placement, $context);
        
        $js = '';
        if ($ad) {
            $adUrl = $this->generateUrl('ad_show', ['placement' => $placement]) . '?' . http_build_query($context);
            $js = sprintf(
                'document.addEventListener("DOMContentLoaded", function() {
                    fetch("%s")
                        .then(response => response.text())
                        .then(html => {
                            const container = document.querySelector("[data-ad-placement=\'%s\']");
                            if (container) {
                                container.innerHTML = html;
                            }
                        })
                        .catch(error => console.error("Ad loading error:", error));
                });',
                $adUrl,
                $placement
            );
        }

        $response = new Response($js);
        $response->headers->set('Content-Type', 'application/javascript');
        
        return $response;
    }
}