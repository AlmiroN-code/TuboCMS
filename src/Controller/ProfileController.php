<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ProfileEditType;
use App\Service\ImageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ProfileController extends AbstractController
{
    public function edit(
        Request $request,
        EntityManagerInterface $entityManager,
        ImageService $imageService,
        ?User $user = null
    ): Response {
        // Если пользователь не передан, используем текущего
        if (!$user) {
            $user = $this->getUser();
        }
        
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Необходима авторизация');
        }
        
        $form = $this->createForm(ProfileEditType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Обработка аватара
            $avatarFile = $form->get('avatarFile')->getData();
            if ($avatarFile) {
                try {
                    // Удаляем старый аватар
                    if ($user->getAvatar()) {
                        $imageService->deleteImage($user->getAvatar(), $this->getParameter('avatars_directory'));
                    }
                    
                    $newFilename = $imageService->processAvatar($avatarFile);
                    $user->setAvatar($newFilename);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Ошибка при загрузке аватара: ' . $e->getMessage());
                }
            }

            // Обработка обложки
            $coverImageFile = $form->get('coverImageFile')->getData();
            if ($coverImageFile) {
                try {
                    // Удаляем старую обложку
                    if ($user->getCoverImage()) {
                        $imageService->deleteImage($user->getCoverImage(), $this->getParameter('covers_directory'));
                    }
                    
                    $newFilename = $imageService->processCover($coverImageFile);
                    $user->setCoverImage($newFilename);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Ошибка при загрузке обложки: ' . $e->getMessage());
                }
            }

            $user->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            $this->addFlash('success', 'Профиль успешно обновлен');
            return $this->redirectToRoute('app_member_profile', ['username' => $user->getUsername()]);
        }

        return $this->render('members/edit.html.twig', [
            'form' => $form,
            'user' => $user,
        ]);
    }
}
