<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Repository\RoleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
class AdminUserController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private RoleRepository $roleRepository,
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    #[Route('', name: 'admin_users')]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = $request->query->getInt('per_page', 30);
        
        // Ограничиваем допустимые значения
        $allowedPerPage = [15, 25, 50, 100];
        if (!in_array($perPage, $allowedPerPage)) {
            $perPage = 30;
        }
        
        $limit = $perPage;
        
        // Получаем пользователей с подсчетом видео
        $qb = $this->userRepository->createQueryBuilder('u')
            ->leftJoin('u.videos', 'v')
            ->addSelect('COUNT(v.id) as videosCount')
            ->groupBy('u.id')
            ->orderBy('u.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);
        
        $result = $qb->getQuery()->getResult();
        
        // Обновляем счетчики видео в базе данных
        foreach ($result as $row) {
            $user = $row[0];
            $actualVideosCount = (int) $row['videosCount'];
            
            if ($user->getVideosCount() !== $actualVideosCount) {
                $user->setVideosCount($actualVideosCount);
                $this->em->persist($user);
            }
        }
        $this->em->flush();
        
        $users = array_map(fn($row) => $row[0], $result);
        $total = $this->userRepository->count([]);
        
        return $this->render('admin/users/index.html.twig', [
            'users' => $users,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'pages' => ceil($total / $limit),
        ]);
    }

    #[Route('/new', name: 'admin_users_new')]
    public function new(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            return $this->handleSave($request, new User());
        }

        $availableRoles = $this->roleRepository->findActiveRoles();

        return $this->render('admin/users/form.html.twig', [
            'user' => new User(),
            'available_roles' => $availableRoles,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_users_edit')]
    public function edit(Request $request, User $user): Response
    {
        if ($request->isMethod('POST')) {
            return $this->handleSave($request, $user);
        }

        $availableRoles = $this->roleRepository->findActiveRoles();

        return $this->render('admin/users/form.html.twig', [
            'user' => $user,
            'available_roles' => $availableRoles,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_users_delete', methods: ['POST'])]
    public function delete(User $user): Response
    {
        $this->em->remove($user);
        $this->em->flush();
        
        $this->addFlash('success', 'Пользователь удален');
        return $this->redirectToRoute('admin_users');
    }

    private function handleSave(Request $request, User $user): Response
    {
        $isNew = $user->getId() === null;
        
        $user->setUsername($request->request->get('username'));
        $user->setEmail($request->request->get('email'));
        
        $password = $request->request->get('password');
        if ($password) {
            $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
            $user->setPassword($hashedPassword);
        }
        
        $roles = $request->request->all('roles') ?: [];
        $user->setRoles($roles);
        
        // Обработка пользовательских ролей
        $user->getUserRoles()->clear();
        $selectedUserRoles = $request->request->all('user_roles') ?: [];
        foreach ($selectedUserRoles as $roleId) {
            $role = $this->roleRepository->find($roleId);
            if ($role) {
                $user->addUserRole($role);
            }
        }
        
        $user->setVerified($request->request->get('is_verified') === '1');
        $user->setPremium($request->request->get('is_premium') === '1');
        
        $this->em->persist($user);
        $this->em->flush();
        
        $this->addFlash('success', $isNew ? 'Пользователь создан' : 'Пользователь обновлен');
        return $this->redirectToRoute('admin_users');
    }

    #[Route('/bulk', name: 'admin_users_bulk', methods: ['POST'])]
    public function bulk(Request $request): Response
    {
        // Проверяем CSRF токен
        if (!$this->isCsrfTokenValid('bulk_users', $request->request->get('_token'))) {
            $this->addFlash('error', 'Недействительный токен безопасности');
            return $this->redirectToRoute('admin_users');
        }

        $userIds = $request->request->all('user_ids');
        $action = $request->request->get('bulk_action');

        if (empty($userIds)) {
            $this->addFlash('error', 'Не выбрано ни одного пользователя');
            return $this->redirectToRoute('admin_users');
        }

        if (empty($action)) {
            $this->addFlash('error', 'Не выбрано действие');
            return $this->redirectToRoute('admin_users');
        }

        $users = $this->userRepository->findBy(['id' => $userIds]);
        $count = count($users);

        switch ($action) {
            case 'verify':
                foreach ($users as $user) {
                    $user->setVerified(true);
                }
                $this->em->flush();
                $this->addFlash('success', "Верифицировано пользователей: {$count}");
                break;

            case 'unverify':
                foreach ($users as $user) {
                    $user->setVerified(false);
                }
                $this->em->flush();
                $this->addFlash('success', "Снята верификация у пользователей: {$count}");
                break;

            case 'premium':
                foreach ($users as $user) {
                    $user->setPremium(true);
                }
                $this->em->flush();
                $this->addFlash('success', "Выдан премиум пользователям: {$count}");
                break;

            case 'remove_premium':
                foreach ($users as $user) {
                    $user->setPremium(false);
                }
                $this->em->flush();
                $this->addFlash('success', "Снят премиум у пользователей: {$count}");
                break;

            case 'delete':
                foreach ($users as $user) {
                    $this->em->remove($user);
                }
                $this->em->flush();
                $this->addFlash('success', "Удалено пользователей: {$count}");
                break;

            default:
                $this->addFlash('error', 'Неизвестное действие');
        }

        return $this->redirectToRoute('admin_users');
    }
}
