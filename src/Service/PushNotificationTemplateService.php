<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Entity\Video;
use App\Entity\Channel;
use App\Repository\SiteSettingRepository;

class PushNotificationTemplateService
{
    public function __construct(
        private SiteSettingRepository $settingRepo
    ) {
    }

    public function formatNewVideo(Channel $channel, Video $video): array
    {
        $title = $this->settingRepo->getValue('notif_new_video_title', 'Новое видео!');
        $body = $this->settingRepo->getValue('notif_new_video_body', '{video}');

        $title = str_replace('{channel}', $channel->getName(), $title);
        $title = str_replace('{video}', $video->getTitle(), $title);
        
        $body = str_replace('{channel}', $channel->getName(), $body);
        $body = str_replace('{video}', $video->getTitle(), $body);

        return ['title' => $title, 'body' => $body];
    }

    public function formatNewComment(User $commenter, Video $video): array
    {
        $title = $this->settingRepo->getValue('notif_new_comment_title', 'Новый комментарий');
        $body = $this->settingRepo->getValue('notif_new_comment_body', '{user} прокомментировал ваше видео');

        $title = str_replace('{user}', $commenter->getUsername(), $title);
        $title = str_replace('{video}', $video->getTitle(), $title);
        
        $body = str_replace('{user}', $commenter->getUsername(), $body);
        $body = str_replace('{video}', $video->getTitle(), $body);

        return ['title' => $title, 'body' => $body];
    }

    public function formatCommentReply(User $replier, Video $video): array
    {
        $title = $this->settingRepo->getValue('notif_comment_reply_title', 'Ответ на комментарий');
        $body = $this->settingRepo->getValue('notif_comment_reply_body', '{user} ответил на ваш комментарий');

        $title = str_replace('{user}', $replier->getUsername(), $title);
        $title = str_replace('{video}', $video->getTitle(), $title);
        
        $body = str_replace('{user}', $replier->getUsername(), $body);
        $body = str_replace('{video}', $video->getTitle(), $body);

        return ['title' => $title, 'body' => $body];
    }

    public function formatNewSubscriber(User $subscriber, Channel $channel): array
    {
        $title = $this->settingRepo->getValue('notif_new_subscriber_title', 'Новый подписчик!');
        $body = $this->settingRepo->getValue('notif_new_subscriber_body', '{user} подписался на ваш канал');

        $title = str_replace('{user}', $subscriber->getUsername(), $title);
        $title = str_replace('{channel}', $channel->getName(), $title);
        
        $body = str_replace('{user}', $subscriber->getUsername(), $body);
        $body = str_replace('{channel}', $channel->getName(), $body);

        return ['title' => $title, 'body' => $body];
    }

    public function formatMention(User $mentioner, Video $video): array
    {
        $title = $this->settingRepo->getValue('notif_mention_title', 'Вас упомянули');
        $body = $this->settingRepo->getValue('notif_mention_body', '{user} упомянул вас в комментарии');

        $title = str_replace('{user}', $mentioner->getUsername(), $title);
        $title = str_replace('{video}', $video->getTitle(), $title);
        
        $body = str_replace('{user}', $mentioner->getUsername(), $body);
        $body = str_replace('{video}', $video->getTitle(), $body);

        return ['title' => $title, 'body' => $body];
    }
}
