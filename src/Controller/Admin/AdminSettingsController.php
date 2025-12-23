<?php

namespace App\Controller\Admin;

use App\Entity\VideoEncodingProfile;
use App\Repository\VideoEncodingProfileRepository;
use App\Service\SettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/settings')]
#[IsGranted('ROLE_ADMIN')]
class AdminSettingsController extends AbstractController
{
    public function __construct(
        private VideoEncodingProfileRepository $profileRepository,
        private EntityManagerInterface $em,
        private SettingsService $settingsService
    ) {
    }

    #[Route('', name: 'admin_settings')]
    public function index(Request $request): Response
    {
        $settingsRepo = $this->em->getRepository(\App\Entity\SiteSetting::class);
        
        if ($request->isMethod('POST')) {
            // Обработка загрузки логотипа
            $logoFile = $request->files->get('site_logo');
            if ($logoFile && $logoFile->isValid()) {
                $logoFileName = 'logo-' . uniqid() . '.' . $logoFile->guessExtension();
                $logoFile->move($this->getParameter('kernel.project_dir') . '/public/media/site', $logoFileName);
                $settingsRepo->setValue('site_logo', '/media/site/' . $logoFileName, 'string', 'Логотип сайта');
            }
            
            // Обработка загрузки фавикона
            $faviconFile = $request->files->get('site_favicon');
            if ($faviconFile && $faviconFile->isValid()) {
                $faviconFileName = 'favicon-' . uniqid() . '.' . $faviconFile->guessExtension();
                $faviconFile->move($this->getParameter('kernel.project_dir') . '/public/media/site', $faviconFileName);
                $settingsRepo->setValue('site_favicon', '/media/site/' . $faviconFileName, 'string', 'Фавикон сайта');
            }
            
            // Общие настройки
            $settingsRepo->setValue('site_name', $request->request->get('site_name'), 'string', 'Название сайта');
            $settingsRepo->setValue('append_site_name_to_title', $request->request->get('append_site_name_to_title') === '1', 'boolean', 'Добавлять название сайта в title');
            $settingsRepo->setValue('site_description', $request->request->get('site_description'), 'string', 'Описание сайта');
            $settingsRepo->setValue('site_keywords', $request->request->get('site_keywords'), 'string', 'Ключевые слова');
            $settingsRepo->setValue('contact_email', $request->request->get('contact_email'), 'string', 'Email для связи');
            
            // Настройки видео
            $settingsRepo->setValue('max_video_size', $request->request->get('max_video_size'), 'integer', 'Максимальный размер видео (MB)');
            $settingsRepo->setValue('allowed_video_formats', $request->request->get('allowed_video_formats'), 'string', 'Разрешенные форматы видео');
            $settingsRepo->setValue('videos_per_page', $request->request->get('videos_per_page'), 'integer', 'Видео на странице');
            
            // Настройки регистрации
            $settingsRepo->setValue('registration_enabled', $request->request->get('registration_enabled') === '1', 'boolean', 'Разрешить регистрацию');
            $settingsRepo->setValue('email_verification_required', $request->request->get('email_verification_required') === '1', 'boolean', 'Требовать подтверждение email');
            
            // Настройки комментариев
            $settingsRepo->setValue('comments_enabled', $request->request->get('comments_enabled') === '1', 'boolean', 'Включить комментарии');
            $settingsRepo->setValue('comments_moderation', $request->request->get('comments_moderation') === '1', 'boolean', 'Модерация комментариев');
            
            // Очистить кеш настроек
            $this->settingsService->clearCache();
            
            $this->addFlash('success', 'Настройки сохранены');
            return $this->redirectToRoute('admin_settings');
        }
        
        $settings = [
            'site_logo' => $settingsRepo->getValue('site_logo', null),
            'site_favicon' => $settingsRepo->getValue('site_favicon', null),
            'site_name' => $settingsRepo->getValue('site_name', 'RexTube'),
            'append_site_name_to_title' => $settingsRepo->getValue('append_site_name_to_title', true),
            'site_description' => $settingsRepo->getValue('site_description', 'Видео хостинг'),
            'site_keywords' => $settingsRepo->getValue('site_keywords', 'видео, хостинг, онлайн'),
            'contact_email' => $settingsRepo->getValue('contact_email', 'admin@rextube.test'),
            'max_video_size' => $settingsRepo->getValue('max_video_size', 500),
            'allowed_video_formats' => $settingsRepo->getValue('allowed_video_formats', 'mp4,avi,mov,mkv'),
            'videos_per_page' => $settingsRepo->getValue('videos_per_page', 24),
            'registration_enabled' => $settingsRepo->getValue('registration_enabled', true),
            'email_verification_required' => $settingsRepo->getValue('email_verification_required', false),
            'comments_enabled' => $settingsRepo->getValue('comments_enabled', true),
            'comments_moderation' => $settingsRepo->getValue('comments_moderation', false),
        ];
        
        return $this->render('admin/settings/index.html.twig', [
            'settings' => $settings,
        ]);
    }

    #[Route('/encoding-profiles', name: 'admin_encoding_profiles')]
    public function encodingProfiles(): Response
    {
        return $this->render('admin/settings/encoding_profiles.html.twig', [
            'profiles' => $this->profileRepository->findAll(),
        ]);
    }

    #[Route('/encoding-profiles/new', name: 'admin_encoding_profiles_new')]
    public function newProfile(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            return $this->handleProfileSave($request, new VideoEncodingProfile());
        }

        return $this->render('admin/settings/encoding_profile_form.html.twig', [
            'profile' => new VideoEncodingProfile(),
        ]);
    }

    #[Route('/encoding-profiles/{id}/edit', name: 'admin_encoding_profiles_edit')]
    public function editProfile(Request $request, VideoEncodingProfile $profile): Response
    {
        if ($request->isMethod('POST')) {
            return $this->handleProfileSave($request, $profile);
        }

        return $this->render('admin/settings/encoding_profile_form.html.twig', [
            'profile' => $profile,
        ]);
    }

    #[Route('/encoding-profiles/{id}/delete', name: 'admin_encoding_profiles_delete', methods: ['POST'])]
    public function deleteProfile(VideoEncodingProfile $profile): Response
    {
        $this->em->remove($profile);
        $this->em->flush();
        
        $this->addFlash('success', 'Профиль кодирования удален');
        return $this->redirectToRoute('admin_encoding_profiles');
    }

    #[Route('/main', name: 'admin_main_page_settings')]
    public function mainPageSettings(Request $request): Response
    {
        $settingsRepo = $this->em->getRepository(\App\Entity\SiteSetting::class);
        
        if ($request->isMethod('POST')) {
            $settingsRepo->setValue('home_new_videos_count', (int) $request->request->get('home_new_videos_count'), 'integer', 'Количество новых видео на главной');
            $settingsRepo->setValue('home_popular_videos_count', (int) $request->request->get('home_popular_videos_count'), 'integer', 'Количество популярных видео на главной');
            $settingsRepo->setValue('home_featured_videos_count', (int) $request->request->get('home_featured_videos_count'), 'integer', 'Количество избранных видео на главной');
            $settingsRepo->setValue('home_show_new_videos', $request->request->get('home_show_new_videos') === '1', 'boolean', 'Показывать новые видео');
            $settingsRepo->setValue('home_show_popular_videos', $request->request->get('home_show_popular_videos') === '1', 'boolean', 'Показывать популярные видео');
            $settingsRepo->setValue('home_show_featured_videos', $request->request->get('home_show_featured_videos') === '1', 'boolean', 'Показывать избранные видео');
            
            $this->settingsService->clearCache();
            
            $this->addFlash('success', 'Настройки главной страницы сохранены');
            return $this->redirectToRoute('admin_main_page_settings');
        }
        
        $settings = [
            'home_new_videos_count' => $settingsRepo->getValue('home_new_videos_count', 12),
            'home_popular_videos_count' => $settingsRepo->getValue('home_popular_videos_count', 12),
            'home_featured_videos_count' => $settingsRepo->getValue('home_featured_videos_count', 10),
            'home_show_new_videos' => $settingsRepo->getValue('home_show_new_videos', true),
            'home_show_popular_videos' => $settingsRepo->getValue('home_show_popular_videos', true),
            'home_show_featured_videos' => $settingsRepo->getValue('home_show_featured_videos', true),
        ];
        
        return $this->render('admin/settings/main_page.html.twig', [
            'settings' => $settings,
        ]);
    }

    #[Route('/transcoding', name: 'admin_transcoding_settings')]
    public function transcodingSettings(Request $request): Response
    {
        $settingsRepo = $this->em->getRepository(\App\Entity\SiteSetting::class);
        
        if ($request->isMethod('POST')) {
            // Настройки постера
            $settingsRepo->setValue('poster_width', (int) $request->request->get('poster_width'), 'integer', 'Ширина постера (px)');
            $settingsRepo->setValue('poster_height', (int) $request->request->get('poster_height'), 'integer', 'Высота постера (px)');
            $settingsRepo->setValue('poster_format', $request->request->get('poster_format'), 'string', 'Формат постера');
            $settingsRepo->setValue('poster_quality', (int) $request->request->get('poster_quality'), 'integer', 'Качество постера (%)');
            
            // Настройки видео-превью
            $settingsRepo->setValue('preview_width', (int) $request->request->get('preview_width'), 'integer', 'Ширина видео-превью (px)');
            $settingsRepo->setValue('preview_height', (int) $request->request->get('preview_height'), 'integer', 'Высота видео-превью (px)');
            $settingsRepo->setValue('preview_duration', (int) $request->request->get('preview_duration'), 'integer', 'Длительность видео-превью (сек)');
            $settingsRepo->setValue('preview_segments', (int) $request->request->get('preview_segments'), 'integer', 'Количество фрагментов');
            $settingsRepo->setValue('preview_format', $request->request->get('preview_format'), 'string', 'Формат видео-превью');
            $settingsRepo->setValue('preview_quality', $request->request->get('preview_quality'), 'string', 'Качество видео-превью');
            
            // Очистить кеш настроек
            $this->settingsService->clearCache();
            
            $this->addFlash('success', 'Настройки транскодирования сохранены');
            return $this->redirectToRoute('admin_transcoding_settings');
        }
        
        $settings = [
            // Настройки постера
            'poster_width' => $settingsRepo->getValue('poster_width', 400),
            'poster_height' => $settingsRepo->getValue('poster_height', 225),
            'poster_format' => $settingsRepo->getValue('poster_format', 'JPEG'),
            'poster_quality' => $settingsRepo->getValue('poster_quality', 85),
            
            // Настройки видео-превью
            'preview_width' => $settingsRepo->getValue('preview_width', 640),
            'preview_height' => $settingsRepo->getValue('preview_height', 360),
            'preview_duration' => $settingsRepo->getValue('preview_duration', 12),
            'preview_segments' => $settingsRepo->getValue('preview_segments', 6),
            'preview_format' => $settingsRepo->getValue('preview_format', 'MP4'),
            'preview_quality' => $settingsRepo->getValue('preview_quality', 'medium'),
        ];
        
        return $this->render('admin/settings/transcoding.html.twig', [
            'settings' => $settings,
        ]);
    }

    #[Route('/seo', name: 'admin_seo_settings')]
    public function seoSettings(Request $request): Response
    {
        $settingsRepo = $this->em->getRepository(\App\Entity\SiteSetting::class);
        
        if ($request->isMethod('POST')) {
            $settingsRepo->setValue('seo_home_title', $request->request->get('seo_home_title'), 'string', 'Заголовок главной страницы');
            $settingsRepo->setValue('seo_home_description', $request->request->get('seo_home_description'), 'string', 'Описание для главной страницы');
            $settingsRepo->setValue('seo_home_keywords', $request->request->get('seo_home_keywords'), 'string', 'Ключевые слова для главной');
            $settingsRepo->setValue('seo_home_only', $request->request->get('seo_home_only') === '1', 'boolean', 'Использовать SEO только для главной');
            
            $this->settingsService->clearCache();
            
            $this->addFlash('success', 'SEO настройки сохранены');
            return $this->redirectToRoute('admin_seo_settings');
        }
        
        $settings = [
            'seo_home_title' => $settingsRepo->getValue('seo_home_title', ''),
            'seo_home_description' => $settingsRepo->getValue('seo_home_description', ''),
            'seo_home_keywords' => $settingsRepo->getValue('seo_home_keywords', ''),
            'seo_home_only' => $settingsRepo->getValue('seo_home_only', false),
        ];
        
        return $this->render('admin/settings/seo.html.twig', [
            'settings' => $settings,
        ]);
    }

    private function handleProfileSave(Request $request, VideoEncodingProfile $profile): Response
    {
        $width = (int) $request->request->get('width');
        $height = (int) $request->request->get('height');
        
        $profile->setName($request->request->get('name'));
        $profile->setResolution("{$width}x{$height}");
        $profile->setBitrate((int) $request->request->get('bitrate'));
        $profile->setCodec($request->request->get('codec', 'h264'));
        $profile->setActive($request->request->get('is_active') === '1');
        
        $this->em->persist($profile);
        $this->em->flush();
        
        $this->addFlash('success', 'Профиль кодирования сохранен');
        return $this->redirectToRoute('admin_encoding_profiles');
    }
}
