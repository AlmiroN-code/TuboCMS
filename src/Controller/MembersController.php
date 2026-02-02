<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Service\UserStatsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MembersController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private UserStatsService $userStatsService
    ) {
    }

    #[Route('/members', name: 'app_members')]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20;
        $search = $request->query->get('search', '');
        $sortBy = $request->query->get('sortBy', 'newest');
        
        $result = $this->userRepository->findPaginated($page, $limit, $search, $sortBy);
        
        return $this->render('members/index.html.twig', [
            'users' => $result['users'],
            'page' => $page,
            'totalPages' => $result['totalPages'],
            'total' => $result['total'],
            'search' => $search,
            'sortBy' => $sortBy,
        ]);
    }

    // Маршрут удален - функционал перенесен в UserProfileController::edit
}