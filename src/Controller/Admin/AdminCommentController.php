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
        $limit = 50;
        
        $qb = $this->commentRepository->createQueryBuilder('c')
            ->orderBy('c.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);
        
        $comments = $qb->getQuery()->getResult();
        $total = $this->commentRepository->count([]);
        
        return $this->render('admin/comments/index.html.twig', [
            'comments' => $comments,
            'page' => $page,
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
}
