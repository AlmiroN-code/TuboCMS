<?php

namespace App\Controller\Admin;

use App\Repository\ChannelPlaylistRepository;
use App\Service\PlaylistService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/playlists')]
#[IsGranted('ROLE_ADMIN')]
class AdminPlaylistController extends AbstractController
{
    public function __construct(
        private ChannelPlaylistRepository $playlistRepository,
        private PlaylistService $playlistService,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('', name: 'admin_playlists_index')]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = $request->query->getInt('per_page', 20);
        
        $allowedPerPage = [15, 25, 50, 100];
        if (!in_array($perPage, $allowedPerPage)) {
            $perPage = 20;
        }
        
        $limit = $perPage;
        $search = $request->query->get('search', '');
        $visibility = $request->query->get('visibility', '');

        // Базовый запрос для подсчёта
        $countQb = $this->playlistRepository->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->leftJoin('p.channel', 'c');

        if ($search) {
            $countQb->andWhere('p.title LIKE :search OR c.name LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($visibility) {
            $countQb->andWhere('p.visibility = :visibility')
                ->setParameter('visibility', $visibility);
        }

        $totalPlaylists = (int) $countQb->getQuery()->getSingleScalarResult();
        $totalPages = (int) ceil($totalPlaylists / $limit);

        // Запрос для получения данных
        $queryBuilder = $this->playlistRepository->createQueryBuilder('p')
            ->leftJoin('p.channel', 'c')
            ->addSelect('c')
            ->orderBy('p.createdAt', 'DESC');

        if ($search) {
            $queryBuilder->andWhere('p.title LIKE :search OR c.name LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($visibility) {
            $queryBuilder->andWhere('p.visibility = :visibility')
                ->setParameter('visibility', $visibility);
        }

        $playlists = $queryBuilder
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $this->render('admin/playlists/index.html.twig', [
            'playlists' => $playlists,
            'current_page' => $page,
            'perPage' => $perPage,
            'total_pages' => $totalPages,
            'total_playlists' => $totalPlaylists,
            'search' => $search,
            'visibility' => $visibility,
        ]);
    }

    #[Route('/{id}/toggle-active', name: 'admin_playlists_toggle_active', methods: ['POST'])]
    public function toggleActive(int $id, Request $request): Response
    {
        $playlist = $this->playlistRepository->find($id);

        if (!$playlist) {
            $this->addFlash('error', 'Плейлист не найден');
            return $this->redirectToRoute('admin_playlists_index');
        }

        if (!$this->isCsrfTokenValid('toggle_active_' . $playlist->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Недействительный токен безопасности');
            return $this->redirectToRoute('admin_playlists_index');
        }

        $playlist->setIsActive(!$playlist->isActive());
        $this->entityManager->flush();

        $status = $playlist->isActive() ? 'активирован' : 'деактивирован';
        $this->addFlash('success', "Плейлист \"{$playlist->getTitle()}\" {$status}");

        return $this->redirectToRoute('admin_playlists_index');
    }

    #[Route('/{id}/delete', name: 'admin_playlists_delete', methods: ['POST'])]
    public function delete(int $id, Request $request): Response
    {
        $playlist = $this->playlistRepository->find($id);

        if (!$playlist) {
            $this->addFlash('error', 'Плейлист не найден');
            return $this->redirectToRoute('admin_playlists_index');
        }

        if (!$this->isCsrfTokenValid('delete_playlist_' . $playlist->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Недействительный токен безопасности');
            return $this->redirectToRoute('admin_playlists_index');
        }

        $title = $playlist->getTitle();
        $this->playlistService->deletePlaylist($playlist);

        $this->addFlash('success', "Плейлист \"{$title}\" удалён");

        return $this->redirectToRoute('admin_playlists_index');
    }

    #[Route('/bulk', name: 'admin_playlists_bulk', methods: ['POST'])]
    public function bulk(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('bulk_playlists', $request->request->get('_token'))) {
            $this->addFlash('error', 'Недействительный токен безопасности');
            return $this->redirectToRoute('admin_playlists_index');
        }

        $action = $request->request->get('action');
        $playlistIds = $request->request->all('playlist_ids');

        if (empty($playlistIds)) {
            $this->addFlash('warning', 'Не выбрано ни одного плейлиста');
            return $this->redirectToRoute('admin_playlists_index');
        }

        $count = 0;

        foreach ($playlistIds as $id) {
            $playlist = $this->playlistRepository->find($id);
            if (!$playlist) {
                continue;
            }

            switch ($action) {
                case 'activate':
                    $playlist->setIsActive(true);
                    $count++;
                    break;

                case 'deactivate':
                    $playlist->setIsActive(false);
                    $count++;
                    break;

                case 'delete':
                    $this->playlistService->deletePlaylist($playlist);
                    $count++;
                    break;
            }
        }

        $this->entityManager->flush();

        $actionLabels = [
            'activate' => 'активировано',
            'deactivate' => 'деактивировано',
            'delete' => 'удалено',
        ];

        $actionLabel = $actionLabels[$action] ?? 'обработано';
        $this->addFlash('success', "Плейлистов {$actionLabel}: {$count}");

        return $this->redirectToRoute('admin_playlists_index');
    }
}
