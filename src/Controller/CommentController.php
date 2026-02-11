<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Video;
use App\Service\MentionService;
use App\Service\NotificationService;
use App\Service\PushNotificationService;
use App\Service\PushNotificationTemplateService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[Route('/comments')]
class CommentController extends AbstractController
{
    public function __construct(
        private MentionService $mentionService,
        private NotificationService $notificationService,
        private PushNotificationService $pushService,
        private PushNotificationTemplateService $pushTemplateService,
    ) {
    }

    #[Route('/video/{id}', name: 'comment_list', methods: ['GET'])]
    public function list(Video $video, EntityManagerInterface $em): Response
    {
        $comments = $em->getRepository(Comment::class)->findBy(
            ['video' => $video, 'parent' => null],
            ['createdAt' => 'DESC']
        );

        return $this->render('comment/_list.html.twig', [
            'comments' => $comments,
            'video' => $video,
        ]);
    }

    #[Route('/add', name: 'comment_add', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function add(
        Request $request, 
        EntityManagerInterface $em, 
        #[Autowire(service: 'limiter.comment_post')]
        RateLimiterFactory $commentPostLimiter
    ): Response
    {
        // Apply rate limiting
        $limiter = $commentPostLimiter->create($request->getClientIp());
        if (false === $limiter->consume(1)->isAccepted()) {
            return new Response('Too many comments. Please wait.', 429);
        }
        
        $videoId = $request->request->get('video_id');
        $parentId = $request->request->get('parent_id');
        $content = $request->request->get('content');

        if (!$content || !$videoId) {
            return new Response('Invalid data', 400);
        }

        $video = $em->getRepository(Video::class)->find($videoId);
        if (!$video) {
            return new Response('Video not found', 404);
        }

        $comment = new Comment();
        $comment->setVideo($video);
        $comment->setUser($this->getUser());
        $comment->setContent($content);

        $parent = null;
        if ($parentId) {
            $parent = $em->getRepository(Comment::class)->find($parentId);
            if ($parent) {
                $comment->setParent($parent);
                $parent->setRepliesCount($parent->getRepliesCount() + 1);
            }
        }

        $video->setCommentsCount($video->getCommentsCount() + 1);

        $em->persist($comment);
        $em->flush();

        // Send notification for reply
        if ($parent !== null) {
            $this->notificationService->notifyCommentReply($comment);
            
            // Push уведомление автору родительского комментария
            $parentAuthor = $parent->getUser();
            if ($parentAuthor->getId() !== $this->getUser()->getId()) {
                $notifText = $this->pushTemplateService->formatCommentReply($this->getUser(), $video);
                $this->pushService->sendToUser(
                    $parentAuthor,
                    $notifText['title'],
                    $notifText['body'],
                    '/video/' . $video->getSlug()
                );
            }
        } else {
            // Push уведомление автору видео о новом комментарии
            $videoOwner = $video->getCreatedBy();
            if ($videoOwner && $videoOwner->getId() !== $this->getUser()->getId()) {
                $notifText = $this->pushTemplateService->formatNewComment($this->getUser(), $video);
                $this->pushService->sendToUser(
                    $videoOwner,
                    $notifText['title'],
                    $notifText['body'],
                    '/video/' . $video->getSlug()
                );
            }
        }

        // Process mentions and send notifications
        $mentionedUsernames = $this->mentionService->extractMentions($content);
        $mentionedUsers = $this->mentionService->resolveMentions($mentionedUsernames);
        foreach ($mentionedUsers as $mentionedUser) {
            $this->notificationService->notifyMention($mentionedUser, $comment);
            
            // Push уведомление упомянутому пользователю
            if ($mentionedUser->getId() !== $this->getUser()->getId()) {
                $notifText = $this->pushTemplateService->formatMention($this->getUser(), $video);
                $this->pushService->sendToUser(
                    $mentionedUser,
                    $notifText['title'],
                    $notifText['body'],
                    '/video/' . $video->getSlug()
                );
            }
        }

        return $this->render('comment/_item.html.twig', [
            'comment' => $comment,
            'video' => $video,
        ]);
    }

    #[Route('/mentions/search', name: 'comment_mentions_search', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function searchMentions(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');
        $users = $this->mentionService->searchUsers($query, 10);

        $results = array_map(fn($user) => [
            'username' => $user->getUsername(),
            'avatar' => $user->getAvatar(),
        ], $users);

        return new JsonResponse($results);
    }

    #[Route('/{id}/delete', name: 'comment_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function delete(Comment $comment, EntityManagerInterface $em): Response
    {
        if ($comment->getUser() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            return new Response('Forbidden', 403);
        }

        $video = $comment->getVideo();
        $video->setCommentsCount($video->getCommentsCount() - 1);

        $em->remove($comment);
        $em->flush();

        return new Response('', 200);
    }
}
