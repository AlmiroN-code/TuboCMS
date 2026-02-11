<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\SiteSettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/pwa-settings')]
class AdminPwaSettingsController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private SiteSettingRepository $settingRepo
    ) {
    }

    #[Route('', name: 'admin_pwa_settings')]
    public function index(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            // PWA настройки
            $this->settingRepo->setValue('pwa_enabled', $request->request->get('pwa_enabled') === '1', 'boolean', 'Включить PWA');
            $this->settingRepo->setValue('pwa_name', $request->request->get('pwa_name', 'RexTube'), 'string', 'Название PWA');
            $this->settingRepo->setValue('pwa_short_name', $request->request->get('pwa_short_name', 'RexTube'), 'string', 'Короткое название PWA');
            $this->settingRepo->setValue('pwa_description', $request->request->get('pwa_description', ''), 'string', 'Описание PWA');
            $this->settingRepo->setValue('pwa_theme_color', $request->request->get('pwa_theme_color', '#f97316'), 'string', 'Цвет темы PWA');
            $this->settingRepo->setValue('pwa_background_color', $request->request->get('pwa_background_color', '#ffffff'), 'string', 'Цвет фона PWA');
            
            // Service Worker настройки
            $this->settingRepo->setValue('sw_enabled', $request->request->get('sw_enabled') === '1', 'boolean', 'Включить Service Worker');
            $this->settingRepo->setValue('sw_cache_version', $request->request->get('sw_cache_version', 'v1'), 'string', 'Версия кеша SW');
            
            // Push уведомления
            $this->settingRepo->setValue('push_enabled', $request->request->get('push_enabled') === '1', 'boolean', 'Включить Push уведомления');
            $this->settingRepo->setValue('vapid_public_key', $request->request->get('vapid_public_key', ''), 'string', 'VAPID Public Key');
            $this->settingRepo->setValue('push_auto_subscribe', $request->request->get('push_auto_subscribe') === '1', 'boolean', 'Автоматически предлагать подписку');
            
            // Тексты уведомлений
            $this->settingRepo->setValue('notif_new_video_title', $request->request->get('notif_new_video_title', 'Новое видео!'), 'string', 'Заголовок: Новое видео');
            $this->settingRepo->setValue('notif_new_video_body', $request->request->get('notif_new_video_body', '{video}'), 'string', 'Текст: Новое видео');
            $this->settingRepo->setValue('notif_new_comment_title', $request->request->get('notif_new_comment_title', 'Новый комментарий'), 'string', 'Заголовок: Новый комментарий');
            $this->settingRepo->setValue('notif_new_comment_body', $request->request->get('notif_new_comment_body', '{user} прокомментировал ваше видео'), 'string', 'Текст: Новый комментарий');
            $this->settingRepo->setValue('notif_comment_reply_title', $request->request->get('notif_comment_reply_title', 'Ответ на комментарий'), 'string', 'Заголовок: Ответ на комментарий');
            $this->settingRepo->setValue('notif_comment_reply_body', $request->request->get('notif_comment_reply_body', '{user} ответил на ваш комментарий'), 'string', 'Текст: Ответ на комментарий');
            $this->settingRepo->setValue('notif_new_subscriber_title', $request->request->get('notif_new_subscriber_title', 'Новый подписчик!'), 'string', 'Заголовок: Новый подписчик');
            $this->settingRepo->setValue('notif_new_subscriber_body', $request->request->get('notif_new_subscriber_body', '{user} подписался на ваш канал'), 'string', 'Текст: Новый подписчик');
            $this->settingRepo->setValue('notif_mention_title', $request->request->get('notif_mention_title', 'Вас упомянули'), 'string', 'Заголовок: Упоминание');
            $this->settingRepo->setValue('notif_mention_body', $request->request->get('notif_mention_body', '{user} упомянул вас в комментарии'), 'string', 'Текст: Упоминание');
            
            $this->addFlash('success', 'Настройки PWA успешно сохранены');
            return $this->redirectToRoute('admin_pwa_settings');
        }

        return $this->render('admin/settings/pwa.html.twig', [
            'pwa_enabled' => $this->settingRepo->getValue('pwa_enabled', true),
            'pwa_name' => $this->settingRepo->getValue('pwa_name', 'RexTube'),
            'pwa_short_name' => $this->settingRepo->getValue('pwa_short_name', 'RexTube'),
            'pwa_description' => $this->settingRepo->getValue('pwa_description', 'Adult video hosting platform'),
            'pwa_theme_color' => $this->settingRepo->getValue('pwa_theme_color', '#f97316'),
            'pwa_background_color' => $this->settingRepo->getValue('pwa_background_color', '#ffffff'),
            'sw_enabled' => $this->settingRepo->getValue('sw_enabled', true),
            'sw_cache_version' => $this->settingRepo->getValue('sw_cache_version', 'v1'),
            'push_enabled' => $this->settingRepo->getValue('push_enabled', true),
            'vapid_public_key' => $this->settingRepo->getValue('vapid_public_key', ''),
            'push_auto_subscribe' => $this->settingRepo->getValue('push_auto_subscribe', false),
            'notif_new_video_title' => $this->settingRepo->getValue('notif_new_video_title', 'Новое видео!'),
            'notif_new_video_body' => $this->settingRepo->getValue('notif_new_video_body', '{video}'),
            'notif_new_comment_title' => $this->settingRepo->getValue('notif_new_comment_title', 'Новый комментарий'),
            'notif_new_comment_body' => $this->settingRepo->getValue('notif_new_comment_body', '{user} прокомментировал ваше видео'),
            'notif_comment_reply_title' => $this->settingRepo->getValue('notif_comment_reply_title', 'Ответ на комментарий'),
            'notif_comment_reply_body' => $this->settingRepo->getValue('notif_comment_reply_body', '{user} ответил на ваш комментарий'),
            'notif_new_subscriber_title' => $this->settingRepo->getValue('notif_new_subscriber_title', 'Новый подписчик!'),
            'notif_new_subscriber_body' => $this->settingRepo->getValue('notif_new_subscriber_body', '{user} подписался на ваш канал'),
            'notif_mention_title' => $this->settingRepo->getValue('notif_mention_title', 'Вас упомянули'),
            'notif_mention_body' => $this->settingRepo->getValue('notif_mention_body', '{user} упомянул вас в комментарии'),
        ]);
    }
}
