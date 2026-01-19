<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationType;
use App\Service\RolePermissionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Psr\Log\LoggerInterface;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(
        Request $request,
        AuthenticationUtils $authenticationUtils,
        RateLimiterFactory $loginAttemptsLimiter,
        LoggerInterface $logger
    ): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        // Apply rate limiting for login attempts
        $limiter = $loginAttemptsLimiter->create($request->getClientIp());
        if (false === $limiter->consume(1)->isAccepted()) {
            $logger->warning('Login rate limit exceeded', [
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent')
            ]);
            $this->addFlash('error', 'Слишком много попыток входа. Попробуйте позже.');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        if ($error) {
            $logger->info('Login form displayed with error', [
                'username' => $lastUsername,
                'ip' => $request->getClientIp()
            ]);
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
        RolePermissionService $rolePermissionService,
        RateLimiterFactory $registrationLimiter,
        LoggerInterface $logger
    ): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $user = new User();
        $form = $this->createForm(RegistrationType::class, $user);
        $form->handleRequest($request);

        // Apply rate limiting only on form submission
        if ($form->isSubmitted()) {
            $limiter = $registrationLimiter->create($request->getClientIp());
            if (false === $limiter->consume(1)->isAccepted()) {
                $logger->warning('Registration rate limit exceeded', [
                    'ip' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent')
                ]);
                $this->addFlash('error', 'Слишком много попыток регистрации. Попробуйте позже.');
                return $this->render('security/register.html.twig', [
                    'registrationForm' => $form,
                ]);
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $hashedPassword = $passwordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                );
                $user->setPassword($hashedPassword);
                $user->setRoles(['ROLE_USER']);

                $em->persist($user);
                $em->flush();

                // Автоматически назначаем роль по умолчанию
                $rolePermissionService->assignDefaultRoleToNewUser($user);

                $logger->info('New user registered successfully', [
                    'username' => $user->getUsername(),
                    'email' => $user->getEmail(),
                    'ip' => $request->getClientIp()
                ]);

                $this->addFlash('success', 'Регистрация успешна! Теперь вы можете войти.');
                return $this->redirectToRoute('app_login');
            } catch (\Exception $e) {
                $logger->error('Registration failed', [
                    'error' => $e->getMessage(),
                    'ip' => $request->getClientIp()
                ]);
                $this->addFlash('error', 'Ошибка при регистрации. Попробуйте еще раз.');
            }
        }

        return $this->render('security/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}
