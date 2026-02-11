<?php

namespace App\Controller\Admin;

use App\Entity\Comment;
use App\Repository\CommentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/comments')]
#[IsGranted('ROLE_ADMIN')]
class AdminCommentController extends AbstractController
{
    public function __construct(
        private CommentRepository $commentRepository,
        private EntityManagerInterface $em
    ) {
    }

    #[Route('', name: 'admin_comments')]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = $request->query->getInt('per_page', 50);
        
        $allowedPerPage = [15, 25, 50, 100];
        if (!in_array($perPage, $allowedPerPage)) {
            $perPage = 50;
        }
        
        $limit = $perPage;
        
        $qb = $this->commentRepository->createQueryBuilder('c')
            ->orderBy('c.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);
        
        $comments = $qb->getQuery()->getResult();
        $total = $this->commentRepository->count([]);
        
        return $this->render('admin/comments/index.html.twig', [
            'comments' => $comments,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'pages' => ceil($total / $limit),
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_comments_delete', methods: ['POST'])]
    public function delete(Comment $comment): Response
    {
        $this->em->remove($comment);
        $this->em->flush();
        
        $this->addFlash('success', 'Комментарий удален');
        return $this->redirectToRoute('admin_comments');
    }

    #[Route('/bulk', name: 'admin_comments_bulk', methods: ['POST'])]
    public function bulk(Request $request): Response
    {
        // Проверяем CSRF токен
        if (!$this->isCsrfTokenValid('bulk_comments', $request->request->get('_token'))) {
            $this->addFlash('error', 'Недействительный токен безопасности');
            return $this->redirectToRoute('admin_comments');
        }

        $commentIds = $request->request->all('comment_ids');
        $action = $request->request->get('bulk_action');

        if (empty($commentIds)) {
            $this->addFlash('error', 'Не выбрано ни одного комментария');
            return $this->redirectToRoute('admin_comments');
        }

        if (empty($action)) {
            $this->addFlash('error', 'Не выбрано действие');
            return $this->redirectToRoute('admin_comments');
        }

        $comments = $this->commentRepository->findBy(['id' => $commentIds]);
        $count = count($comments);

        switch ($action) {
            case 'approve':
                foreach ($comments as $comment) {
                    $comment->setModerationStatus(Comment::MODERATION_APPROVED);
                }
                $this->em->flush();
                $this->addFlash('success', "Одобрено комментариев: {$count}");
                break;

            case 'reject':
                foreach ($comments as $comment) {
                    $comment->setModerationStatus(Comment::MODERATION_REJECTED);
                }
                $this->em->flush();
                $this->addFlash('success', "Отклонено комментариев: {$count}");
                break;

            case 'delete':
                foreach ($comments as $comment) {
                    $this->em->remove($comment);
                }
                $this->em->flush();
                $this->addFlash('success', "Удалено комментариев: {$count}");
                break;

            default:
                $this->addFlash('error', 'Неизвестное действие');
        }

        return $this->redirectToRoute('admin_comments');
    }
}
