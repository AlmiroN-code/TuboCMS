<?php

namespace App\Controller\Admin;

use App\Entity\Permission;
use App\Repository\PermissionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/permissions')]
#[IsGranted('ROLE_ADMIN')]
class AdminPermissionController extends AbstractController
{
    public function __construct(
        private PermissionRepository $permissionRepository,
        private EntityManagerInterface $em
    ) {
    }

    #[Route('', name: 'admin_permissions')]
    public function index(): Response
    {
        $permissions = $this->permissionRepository->findGroupedByCategory();
        
        return $this->render('admin/permissions/index.html.twig', [
            'permissions' => $permissions,
        ]);
    }

    #[Route('/new', name: 'admin_permissions_new')]
    public function new(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            return $this->handleSave($request, new Permission());
        }

        return $this->render('admin/permissions/form.html.twig', [
            'permission' => new Permission(),
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_permissions_edit')]
    public function edit(Request $request, Permission $permission): Response
    {
        if ($request->isMethod('POST')) {
            return $this->handleSave($request, $permission);
        }

        return $this->render('admin/permissions/form.html.twig', [
            'permission' => $permission,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_permissions_delete', methods: ['POST'])]
    public function delete(Permission $permission): Response
    {
        // Проверяем, что разрешение не используется
        if ($permission->getRoles()->count() > 0) {
            $this->addFlash('error', 'Нельзя удалить разрешение, которое назначено ролям');
            return $this->redirectToRoute('admin_permissions');
        }

        $this->em->remove($permission);
        $this->em->flush();
        
        $this->addFlash('success', 'Разрешение удалено');
        return $this->redirectToRoute('admin_permissions');
    }

    private function handleSave(Request $request, Permission $permission): Response
    {
        $isNew = $permission->getId() === null;
        
        $permission->setName($request->request->get('name'));
        $permission->setDisplayName($request->request->get('display_name'));
        $permission->setDescription($request->request->get('description'));
        $permission->setCategory($request->request->get('category'));
        $permission->setActive($request->request->get('is_active') === '1');
        
        if (!$isNew) {
            $permission->setUpdatedAt(new \DateTimeImmutable());
        }
        
        $this->em->persist($permission);
        $this->em->flush();
        
        $this->addFlash('success', $isNew ? 'Разрешение создано' : 'Разрешение обновлено');
        return $this->redirectToRoute('admin_permissions');
    }
}