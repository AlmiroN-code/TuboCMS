<?php

namespace App\Controller\Admin;

use App\Entity\Role;
use App\Repository\RoleRepository;
use App\Repository\PermissionRepository;
use App\Service\RolePermissionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/roles')]
#[IsGranted('ROLE_ADMIN')]
class AdminRoleController extends AbstractController
{
    public function __construct(
        private RoleRepository $roleRepository,
        private PermissionRepository $permissionRepository,
        private EntityManagerInterface $em,
        private RolePermissionService $rolePermissionService
    ) {
    }

    #[Route('', name: 'admin_roles')]
    public function index(): Response
    {
        $roles = $this->roleRepository->findRolesWithPermissions();
        
        return $this->render('admin/roles/index.html.twig', [
            'roles' => $roles,
        ]);
    }

    #[Route('/new', name: 'admin_roles_new')]
    public function new(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            return $this->handleSave($request, new Role());
        }

        $permissions = $this->permissionRepository->findGroupedByCategory();

        return $this->render('admin/roles/form.html.twig', [
            'role' => new Role(),
            'permissions' => $permissions,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_roles_edit')]
    public function edit(Request $request, Role $role): Response
    {
        if ($request->isMethod('POST')) {
            return $this->handleSave($request, $role);
        }

        $permissions = $this->permissionRepository->findGroupedByCategory();

        return $this->render('admin/roles/form.html.twig', [
            'role' => $role,
            'permissions' => $permissions,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_roles_delete', methods: ['POST'])]
    public function delete(Role $role): Response
    {
        // Проверяем, что роль не используется
        if ($role->getUsers()->count() > 0) {
            $this->addFlash('error', 'Нельзя удалить роль, которая назначена пользователям');
            return $this->redirectToRoute('admin_roles');
        }

        $this->em->remove($role);
        $this->em->flush();
        
        $this->addFlash('success', 'Роль удалена');
        return $this->redirectToRoute('admin_roles');
    }

    private function handleSave(Request $request, Role $role): Response
    {
        $isNew = $role->getId() === null;
        
        $role->setName($request->request->get('name'));
        $role->setDisplayName($request->request->get('display_name'));
        $role->setDescription($request->request->get('description'));
        $role->setActive($request->request->get('is_active') === '1');
        
        if (!$isNew) {
            $role->setUpdatedAt(new \DateTimeImmutable());
        }

        // Очищаем текущие разрешения
        $role->getPermissions()->clear();
        
        // Добавляем выбранные разрешения
        $selectedPermissions = $request->request->all('permissions') ?: [];
        foreach ($selectedPermissions as $permissionId) {
            $permission = $this->permissionRepository->find($permissionId);
            if ($permission) {
                $role->addPermission($permission);
            }
        }
        
        $this->em->persist($role);
        $this->em->flush();
        
        $this->addFlash('success', $isNew ? 'Роль создана' : 'Роль обновлена');
        return $this->redirectToRoute('admin_roles');
    }
}