<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\PushNotificationService;
use App\Service\PushNotificationTemplateService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class MentionService
{
    private const MENTION_PATTERN = '/@([a-zA-Z0-9_-]+)/';

    public function __construct(
        private UserRepository $userRepository,
        private UrlGeneratorInterface $urlGenerator,
        private PushNotificationService $pushService,
        private PushNotificationTemplateService $pushTemplateService,
    ) {
    }

    /**
     * @return string[] Array of usernames mentioned in text
     */
    public function extractMentions(string $text): array
    {
        preg_match_all(self::MENTION_PATTERN, $text, $matches);
        return array_unique($matches[1] ?? []);
    }

    /**
     * @param string[] $usernames
     * @return User[] Array of User entities for valid usernames
     */
    public function resolveMentions(array $usernames): array
    {
        if (empty($usernames)) {
            return [];
        }

        return $this->userRepository->createQueryBuilder('u')
            ->where('u.username IN (:usernames)')
            ->setParameter('usernames', $usernames)
            ->getQuery()
            ->getResult();
    }

    /**
     * Replace @username with clickable links
     */
    public function formatMentions(string $text): string
    {
        return preg_replace_callback(
            self::MENTION_PATTERN,
            function ($matches) {
                $username = $matches[1];
                $user = $this->userRepository->findOneBy(['username' => $username]);
                
                if ($user === null) {
                    return $matches[0]; // Return original if user not found
                }

                $url = $this->urlGenerator->generate('app_profile_show', ['username' => $username]);
                return sprintf('<a href="%s" class="mention">@%s</a>', htmlspecialchars($url), htmlspecialchars($username));
            },
            $text
        );
    }

    /**
     * @return User[] Users matching query for autocomplete
     */
    public function searchUsers(string $query, int $limit = 10): array
    {
        if (strlen($query) < 2) {
            return [];
        }

        return $this->userRepository->createQueryBuilder('u')
            ->where('u.username LIKE :query')
            ->setParameter('query', $query . '%')
            ->orderBy('u.username', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
