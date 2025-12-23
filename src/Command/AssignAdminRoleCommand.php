<?php

namespace App\Command;

use App\Repository\UserRepository;
use App\Service\RolePermissionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:assign-admin-role',
    description: 'Назначить роль администратора пользователю'
)]
class AssignAdminRoleCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        private RolePermissionService $rolePermissionService,
        private EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Email пользователя');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');

        $user = $this->userRepository->findOneBy(['email' => $email]);
        
        if (!$user) {
            $io->error("Пользователь с email '{$email}' не найден");
            return Command::FAILURE;
        }

        // Назначаем системную роль ROLE_ADMIN
        $roles = $user->getRoles();
        if (!in_array('ROLE_ADMIN', $roles)) {
            $currentRoles = $user->getRoles();
            $currentRoles[] = 'ROLE_ADMIN';
            $user->setRoles(array_diff($currentRoles, ['ROLE_USER'])); // Убираем ROLE_USER из массива, он добавляется автоматически
            $this->em->persist($user);
            $this->em->flush();
        }

        // Назначаем пользовательскую роль admin
        $success = $this->rolePermissionService->assignRoleToUser($user, 'admin');
        
        if ($success) {
            $io->success("Роль администратора назначена пользователю {$user->getUsername()} ({$email})");
            $io->note("Системные роли: " . implode(', ', $user->getRoles()));
            $io->note("Пользовательские роли: " . $user->getUserRoles()->count());
            return Command::SUCCESS;
        } else {
            $io->error('Не удалось назначить роль администратора');
            return Command::FAILURE;
        }
    }
}