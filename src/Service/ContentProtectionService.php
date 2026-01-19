<?php

namespace App\Service;

use App\Repository\ContentProtectionSettingRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class ContentProtectionService
{
    private const BLOCKED_USER_AGENTS = [
        'wget', 'curl', 'youtube-dl', 'yt-dlp', 'ffmpeg', 'aria2',
        'IDM', 'FlashGet', 'GetRight', 'Download Master', 'JDownloader',
        'python-requests', 'scrapy', 'bot', 'crawler', 'spider'
    ];

    public function __construct(
        private ContentProtectionSettingRepository $settingRepository,
        private RequestStack $requestStack
    ) {
    }

    /**
     * Проверка, включена ли защита от hotlink
     */
    public function isHotlinkProtectionEnabled(): bool
    {
        return (bool) $this->settingRepository->getValue('hotlink_protection_enabled', false);
    }

    /**
     * Проверка, включена ли фильтрация User-Agent
     */
    public function isUserAgentFilteringEnabled(): bool
    {
        return (bool) $this->settingRepository->getValue('user_agent_filtering_enabled', false);
    }

    /**
     * Проверка, включены ли подписанные URL
     */
    public function isSignedUrlsEnabled(): bool
    {
        return (bool) $this->settingRepository->getValue('signed_urls_enabled', false);
    }

    /**
     * Получить время жизни токена (в секундах)
     */
    public function getTokenLifetime(): int
    {
        return (int) $this->settingRepository->getValue('token_lifetime', 3600);
    }

    /**
     * Получить список разрешенных доменов для hotlink
     */
    public function getAllowedDomains(): array
    {
        $domains = $this->settingRepository->getValue('allowed_domains', []);
        return is_array($domains) ? $domains : [];
    }

    /**
     * Получить список заблокированных User-Agent
     */
    public function getBlockedUserAgents(): array
    {
        $custom = $this->settingRepository->getValue('blocked_user_agents', []);
        $custom = is_array($custom) ? $custom : [];
        
        return array_merge(self::BLOCKED_USER_AGENTS, $custom);
    }

    /**
     * Проверка запроса на соответствие правилам защиты
     */
    public function validateRequest(Request $request): array
    {
        $errors = [];

        // Проверка Referer (hotlink protection)
        if ($this->isHotlinkProtectionEnabled()) {
            if (!$this->validateReferer($request)) {
                $errors[] = 'Invalid referer';
            }
        }

        // Проверка User-Agent
        if ($this->isUserAgentFilteringEnabled()) {
            if (!$this->validateUserAgent($request)) {
                $errors[] = 'Blocked user agent';
            }
        }

        return $errors;
    }

    /**
     * Проверка Referer
     */
    private function validateReferer(Request $request): bool
    {
        $referer = $request->headers->get('referer');
        
        // Если нет referer - блокируем
        if (!$referer) {
            return false;
        }

        $refererHost = parse_url($referer, PHP_URL_HOST);
        $currentHost = $request->getHost();

        // Проверяем, что referer с нашего домена
        if ($refererHost === $currentHost) {
            return true;
        }

        // Проверяем список разрешенных доменов
        $allowedDomains = $this->getAllowedDomains();
        foreach ($allowedDomains as $domain) {
            if (str_ends_with($refererHost, $domain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Проверка User-Agent
     */
    private function validateUserAgent(Request $request): bool
    {
        $userAgent = strtolower($request->headers->get('user-agent', ''));
        
        if (empty($userAgent)) {
            return false;
        }

        $blockedAgents = $this->getBlockedUserAgents();
        
        foreach ($blockedAgents as $blocked) {
            if (str_contains($userAgent, strtolower($blocked))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Генерация подписанного URL для медиа-файла
     */
    public function generateSignedUrl(string $path, ?int $userId = null): string
    {
        if (!$this->isSignedUrlsEnabled()) {
            return $path;
        }

        $request = $this->requestStack->getCurrentRequest();
        $ip = $request ? $request->getClientIp() : '';
        
        $expires = time() + $this->getTokenLifetime();
        $token = $this->generateToken($path, $expires, $ip, $userId);

        $separator = str_contains($path, '?') ? '&' : '?';
        
        return $path . $separator . http_build_query([
            'token' => $token,
            'expires' => $expires,
        ]);
    }

    /**
     * Проверка подписанного URL
     */
    public function validateSignedUrl(Request $request, string $path): bool
    {
        if (!$this->isSignedUrlsEnabled()) {
            return true;
        }

        $token = $request->query->get('token');
        $expires = $request->query->get('expires');

        if (!$token || !$expires) {
            return false;
        }

        // Проверка времени жизни
        if (time() > $expires) {
            return false;
        }

        // Проверка токена
        $ip = $request->getClientIp();
        $userId = $request->getSession()->get('user_id');
        
        $expectedToken = $this->generateToken($path, $expires, $ip, $userId);

        return hash_equals($expectedToken, $token);
    }

    /**
     * Генерация токена для подписи URL
     */
    private function generateToken(string $path, int $expires, string $ip, ?int $userId): string
    {
        $secret = $this->getSecretKey();
        $data = $path . $expires . $ip . ($userId ?? '');
        
        return hash_hmac('sha256', $data, $secret);
    }

    /**
     * Получение секретного ключа для подписи
     */
    private function getSecretKey(): string
    {
        $key = $this->settingRepository->getValue('secret_key');
        
        if (!$key) {
            // Генерируем новый ключ
            $key = bin2hex(random_bytes(32));
            $this->settingRepository->setValue('secret_key', $key);
        }

        return $key;
    }

    /**
     * Получить максимальное количество запросов в час с одного IP
     */
    public function getRateLimitPerHour(): int
    {
        return (int) $this->settingRepository->getValue('rate_limit_per_hour', 100);
    }

    /**
     * Проверка, включен ли водяной знак
     */
    public function isWatermarkEnabled(): bool
    {
        return (bool) $this->settingRepository->getValue('watermark_enabled', false);
    }

    /**
     * Получить текст водяного знака
     */
    public function getWatermarkText(): string
    {
        return $this->settingRepository->getValue('watermark_text', '');
    }

    /**
     * Получить позицию водяного знака
     */
    public function getWatermarkPosition(): string
    {
        return $this->settingRepository->getValue('watermark_position', 'bottom-right');
    }

    /**
     * Получить прозрачность водяного знака (0-100)
     */
    public function getWatermarkOpacity(): int
    {
        return (int) $this->settingRepository->getValue('watermark_opacity', 50);
    }
}
