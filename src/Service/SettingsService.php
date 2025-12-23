<?php

namespace App\Service;

use App\Repository\SiteSettingRepository;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Psr\Log\LoggerInterface;

class SettingsService
{
    private const CACHE_KEY = 'site_settings_all';
    private const CACHE_TTL = 3600; // 1 час
    
    private ?array $settings = null;

    public function __construct(
        private SiteSettingRepository $settingRepository,
        private CacheInterface $cache,
        private LoggerInterface $logger
    ) {
    }

    private function loadSettings(): array
    {
        if ($this->settings === null) {
            try {
                $this->settings = $this->cache->get(self::CACHE_KEY, function (ItemInterface $item) {
                    $item->expiresAfter(self::CACHE_TTL);
                    
                    $settings = [];
                    $allSettings = $this->settingRepository->findAll();
                    
                    foreach ($allSettings as $setting) {
                        $value = $setting->getSettingValue();
                        
                        try {
                            $settings[$setting->getSettingKey()] = match ($setting->getSettingType()) {
                                'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                                'integer' => (int) $value,
                                'float' => (float) $value,
                                'json' => json_decode($value, true, 512, JSON_THROW_ON_ERROR),
                                default => $value,
                            };
                        } catch (\JsonException $e) {
                            $this->logger->error('Invalid JSON in setting', [
                                'key' => $setting->getSettingKey(),
                                'value' => $value,
                                'error' => $e->getMessage()
                            ]);
                            $settings[$setting->getSettingKey()] = $value; // Fallback to string
                        }
                    }
                    
                    return $settings;
                });
            } catch (\Exception $e) {
                $this->logger->error('Failed to load settings from cache', [
                    'error' => $e->getMessage()
                ]);
                // Fallback: load directly from database
                $this->settings = [];
                $allSettings = $this->settingRepository->findAll();
                foreach ($allSettings as $setting) {
                    $this->settings[$setting->getSettingKey()] = $setting->getSettingValue();
                }
            }
        }
        
        return $this->settings;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $settings = $this->loadSettings();
        return $settings[$key] ?? $default;
    }
    
    public function clearCache(): void
    {
        $this->cache->delete(self::CACHE_KEY);
        $this->settings = null;
    }

    public function set(string $key, mixed $value, string $type = 'string', ?string $description = null): void
    {
        $this->settingRepository->setValue($key, $value, $type, $description);
        $this->clearCache();
    }

    public function getSiteLogo(): ?string
    {
        return $this->get('site_logo', null);
    }

    public function getSiteFavicon(): ?string
    {
        return $this->get('site_favicon', null);
    }

    public function getSiteName(): string
    {
        return $this->get('site_name', 'RexTube');
    }

    public function getSiteDescription(): string
    {
        return $this->get('site_description', 'Видео хостинг');
    }

    public function getContactEmail(): string
    {
        return $this->get('contact_email', 'admin@rextube.test');
    }

    public function getMaxVideoSize(): int
    {
        return $this->get('max_video_size', 500);
    }

    public function getAllowedVideoFormats(): array
    {
        $formats = $this->get('allowed_video_formats', 'mp4,avi,mov,mkv');
        return array_map('trim', explode(',', $formats));
    }

    public function getVideosPerPage(): int
    {
        return $this->get('videos_per_page', 24);
    }

    public function isRegistrationEnabled(): bool
    {
        return $this->get('registration_enabled', true);
    }

    public function isEmailVerificationRequired(): bool
    {
        return $this->get('email_verification_required', false);
    }

    public function areCommentsEnabled(): bool
    {
        return $this->get('comments_enabled', true);
    }

    public function isCommentModerationEnabled(): bool
    {
        return $this->get('comments_moderation', false);
    }
}
