<?php

namespace App\Controller\Admin;

use App\Entity\Comment;
use App\Entity\Video;
use App\Repository\CommentRepository;
use App\Repository\VideoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Workflow\WorkflowInterface;

#[Route('/admin/workflow')]
#[IsGranted('ROLE_ADMIN')]
class AdminWorkflowController extends AbstractController
{
    public function __construct(
        private WorkflowInterface $videoStatusStateMachine,
        private WorkflowInterface $commentModerationStateMachine,
        private EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'admin_workflow', methods: ['GET'])]
    public function index(VideoRepository $videoRepository, CommentRepository $commentRepository): Response
    {
        // Статистика по статусам видео
        $videoStats = [
            'draft' => $videoRepository->count(['status' => 'draft']),
            'processing' => $videoRepository->count(['status' => 'processing']),
            'published' => $videoRepository->count(['status' => 'published']),
            'private' => $videoRepository->count(['status' => 'private']),
            'rejected' => $videoRepository->count(['status' => 'rejected']),
        ];

        // Статистика по статусам комментариев
        $commentStats = [
            'pending' => $commentRepository->count(['moderationStatus' => Comment::MODERATION_PENDING]),
            'approved' => $commentRepository->count(['moderationStatus' => Comment::MODERATION_APPROVED]),
            'rejected' => $commentRepository->count(['moderationStatus' => Comment::MODERATION_REJECTED]),
            'spam' => $commentRepository->count(['moderationStatus' => Comment::MODERATION_SPAM]),
        ];

        // Последние видео в обработке
        $processingVideos = $videoRepository->findBy(
            ['status' => 'processing'],
            ['updatedAt' => 'DESC'],
            10
        );

        // Комментарии на модерации
        $pendingComments = $commentRepository->findBy(
            ['moderationStatus' => Comment::MODERATION_PENDING],
            ['createdAt' => 'DESC'],
            10
        );

        return $this->render('admin/workflow/index.html.twig', [
            'videoStats' => $videoStats,
            'commentStats' => $commentStats,
            'processingVideos' => $processingVideos,
            'pendingComments' => $pendingComments,
        ]);
    }

    #[Route('/video/{id}/transition/{transition}', name: 'admin_workflow_video_transition', methods: ['POST'])]
    public function videoTransition(Video $video, string $transition, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('workflow_video_' . $video->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Недействительный CSRF токен');
            return $this->redirectToRoute('admin_workflow');
        }

        if ($this->videoStatusStateMachine->can($video, $transition)) {
            $this->videoStatusStateMachine->apply($video, $transition);
            $this->em->flush();
            $this->addFlash('success', "Переход '{$transition}' выполнен успешно");
        } else {
            $this->addFlash('error', "Переход '{$transition}' невозможен для текущего статуса");
        }

        return $this->redirectToRoute('admin_workflow');
    }

    #[Route('/comment/{id}/transition/{transition}', name: 'admin_workflow_comment_transition', methods: ['POST'])]
    public function commentTransition(Comment $comment, string $transition, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('workflow_comment_' . $comment->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Недействительный CSRF токен');
            return $this->redirectToRoute('admin_workflow');
        }

        if ($this->commentModerationStateMachine->can($comment, $transition)) {
            $this->commentModerationStateMachine->apply($comment, $transition);
            $this->em->flush();
            $this->addFlash('success', "Переход '{$transition}' выполнен успешно");
        } else {
            $this->addFlash('error', "Переход '{$transition}' невозможен для текущего статуса");
        }

        return $this->redirectToRoute('admin_workflow');
    }
}
