<?php

namespace App\Controller\Api;

use App\Entity\ChannelPlaylist;
use App\Entity\PlaylistCollaborator;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\PlaylistService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/playlist/{id}/collaborators')]
#[IsGranted('ROLE_USER')]
class PlaylistCollaboratorController extends AbstractController
{
    public function __construct(
        private PlaylistService $playlistService,
        private UserRepository $userRepository,
        private EntityManagerInterface $em
    ) {}

    #[Route('', name: 'api_playlist_collaborators_list', methods: ['GET'])]
    public function list(ChannelPlaylist $playlist): JsonResponse
    {
        if (!$this->playlistService->canUserManagePlaylist($playlist, $this->getUser())) {
            return $this->json(['error' => 'Доступ запрещён'], Response::HTTP_FORBIDDEN);
        }

        $collaborators = $this->playlistService->getCollaborators($playlist);
        
        $data = array_map(function($collaborator) {
            return [
                'userId' => $collaborator->getUser()->getId(),
                'username' => $collaborator->getUser()->getUsername(),
                'avatar' => $collaborator->getUser()->getAvatar(),
                'permission' => $collaborator->getPermission(),
                'addedAt' => $collaborator->getAddedAt()->format('Y-m-d H:i:s'),
            ];
        }, $collaborators);

        return $this->json($data);
    }

    #[Route('', name: 'api_playlist_collaborators_add', methods: ['POST'])]
    public function add(ChannelPlaylist $playlist, Request $request): JsonResponse
    {
        if (!$this->playlistService->canUserManagePlaylist($playlist, $this->getUser())) {
            return $this->json(['error' => 'Доступ запрещён'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        $username = $data['username'] ?? null;
        $permission = $data['permission'] ?? PlaylistCollaborator::PERMISSION_ADD;

        if (!$username) {
            return $this->json(['error' => 'Не указан пользователь'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepository->findOneBy(['username' => $username]);
        if (!$user) {
            return $this->json(['error' => 'Пользователь не найден'], Response::HTTP_NOT_FOUND);
        }

        try {
            $collaborator = $this->playlistService->addCollaborator(
                $playlist,
                $user,
                $permission,
                $this->getUser()
            );

            return $this->json([
                'success' => true,
                'message' => 'Соавтор добавлен',
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{userId}', name: 'api_playlist_collaborators_update', methods: ['PUT'])]
    public function update(ChannelPlaylist $playlist, int $userId, Request $request): JsonResponse
    {
        if (!$this->playlistService->canUserManagePlaylist($playlist, $this->getUser())) {
            return $this->json(['error' => 'Доступ запрещён'], Response::HTTP_FORBIDDEN);
        }

        $user = $this->userRepository->find($userId);
        if (!$user) {
            return $this->json(['error' => 'Пользователь не найден'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $permission = $data['permission'] ?? null;

        if (!$permission) {
            return $this->json(['error' => 'Не указаны права'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->playlistService->updateCollaboratorPermission($playlist, $user, $permission);

            return $this->json([
                'success' => true,
                'message' => 'Права обновлены',
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{userId}', name: 'api_playlist_collaborators_remove', methods: ['DELETE'])]
    public function remove(ChannelPlaylist $playlist, int $userId): JsonResponse
    {
        if (!$this->playlistService->canUserManagePlaylist($playlist, $this->getUser())) {
            return $this->json(['error' => 'Доступ запрещён'], Response::HTTP_FORBIDDEN);
        }

        $user = $this->userRepository->find($userId);
        if (!$user) {
            return $this->json(['error' => 'Пользователь не найден'], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->playlistService->removeCollaborator($playlist, $user);

            return $this->json([
                'success' => true,
                'message' => 'Соавтор удалён',
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/toggle-collaborative', name: 'api_playlist_toggle_collaborative', methods: ['POST'])]
    public function toggleCollaborative(ChannelPlaylist $playlist): JsonResponse
    {
        if (!$this->playlistService->canUserManagePlaylist($playlist, $this->getUser())) {
            return $this->json(['error' => 'Доступ запрещён'], Response::HTTP_FORBIDDEN);
        }

        if ($playlist->isCollaborative()) {
            $this->playlistService->disableCollaborative($playlist);
            $message = 'Совместный режим отключён';
        } else {
            $this->playlistService->makeCollaborative($playlist);
            $message = 'Совместный режим включён';
        }

        return $this->json([
            'success' => true,
            'message' => $message,
            'isCollaborative' => $playlist->isCollaborative(),
        ]);
    }
}
