<?php

namespace App\Controller\Admin;

use App\Repository\ContentProtectionSettingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/settings/protection')]
#[IsGranted('ROLE_ADMIN')]
class AdminContentProtectionController extends AbstractController
{
    public function __construct(
        private ContentProtectionSettingRepository $settingRepository
    ) {
    }

    #[Route('', name: 'admin_content_protection')]
    public function index(): Response
    {
        $settings = [
            'hotlink_protection_enabled' => $this->settingRepository->getValue('hotlink_protection_enabled', false),
            'user_agent_filtering_enabled' => $this->settingRepository->getValue('user_agent_filtering_enabled', false),
            'signed_urls_enabled' => $this->settingRepository->getValue('signed_urls_enabled', false),
            'token_lifetime' => $this->settingRepository->getValue('token_lifetime', 3600),
            'allowed_domains' => $this->settingRepository->getValue('allowed_domains', []),
            'blocked_user_agents' => $this->settingRepository->getValue('blocked_user_agents', []),
            'rate_limit_per_hour' => $this->settingRepository->getValue('rate_limit_per_hour', 100),
            'watermark_enabled' => $this->settingRepository->getValue('watermark_enabled', false),
            'watermark_text' => $this->settingRepository->getValue('watermark_text', ''),
            'watermark_position' => $this->settingRepository->getValue('watermark_position', 'bottom-right'),
            'watermark_opacity' => $this->settingRepository->getValue('watermark_opacity', 50),
        ];

        return $this->render('admin/content_protection/index.html.twig', [
            'settings' => $settings,
        ]);
    }

    #[Route('/save', name: 'admin_content_protection_save', methods: ['POST'])]
    public function save(Request $request): Response
    {
        // Hotlink Protection
        $this->settingRepository->setValue(
            'hotlink_protection_enabled',
            $request->request->get('hotlink_protection_enabled') === '1'
        );

        // User-Agent Filtering
        $this->settingRepository->setValue(
            'user_agent_filtering_enabled',
            $request->request->get('user_agent_filtering_enabled') === '1'
        );

        // Signed URLs
        $this->settingRepository->setValue(
            'signed_urls_enabled',
            $request->request->get('signed_urls_enabled') === '1'
        );

        // Token Lifetime
        $tokenLifetime = (int) $request->request->get('token_lifetime', 3600);
        $this->settingRepository->setValue('token_lifetime', $tokenLifetime);

        // Allowed Domains
        $allowedDomains = $request->request->get('allowed_domains', '');
        $allowedDomains = array_filter(array_map('trim', explode("\n", $allowedDomains)));
        $this->settingRepository->setValue('allowed_domains', $allowedDomains);

        // Blocked User-Agents
        $blockedUserAgents = $request->request->get('blocked_user_agents', '');
        $blockedUserAgents = array_filter(array_map('trim', explode("\n", $blockedUserAgents)));
        $this->settingRepository->setValue('blocked_user_agents', $blockedUserAgents);

        // Rate Limiting
        $rateLimit = (int) $request->request->get('rate_limit_per_hour', 100);
        $this->settingRepository->setValue('rate_limit_per_hour', $rateLimit);

        // Watermark
        $this->settingRepository->setValue(
            'watermark_enabled',
            $request->request->get('watermark_enabled') === '1'
        );

        $this->settingRepository->setValue(
            'watermark_text',
            $request->request->get('watermark_text', '')
        );

        $this->settingRepository->setValue(
            'watermark_position',
            $request->request->get('watermark_position', 'bottom-right')
        );

        $watermarkOpacity = (int) $request->request->get('watermark_opacity', 50);
        $this->settingRepository->setValue('watermark_opacity', $watermarkOpacity);

        $this->addFlash('success', 'Настройки защиты контента успешно сохранены');

        return $this->redirectToRoute('admin_content_protection');
    }
}
