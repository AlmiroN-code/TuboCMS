<?php

namespace App\Command;

use App\Entity\Role;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:assign-admin-role',
    description: 'Назначить роль ROLE_ADMIN пользователям с правами администратора',
)]
class AssignAdminRoleCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Находим роль ROLE_ADMIN
        $adminRole = $this->entityManager->getRepository(Role::class)
            ->findOneBy(['name' => 'ROLE_ADMIN']);

        if (!$adminRole) {
            $io->error('Роль ROLE_ADMIN не найдена. Сначала запустите: php bin/console app:init-permissions');
            return Command::FAILURE;
        }

        // Находим всех пользователей с ROLE_ADMIN в массиве roles
        $users = $this->entityManager->getRepository(User::class)->findAll();
        $count = 0;

        foreach ($users as $user) {
            $roles = $user->getRoles();
            
            // Если у пользователя есть ROLE_ADMIN в массиве roles
            if (in_array('ROLE_ADMIN', $roles, true)) {
                // Проверяем, не назначена ли уже роль через userRoles
                if (!$user->getUserRoles()->contains($adminRole)) {
                    $user->addUserRole($adminRole);
                    $count++;
                    $io->writeln(sprintf('✓ Назначена роль ROLE_ADMIN пользователю: %s', $user->getEmail()));
                }
            }
        }

        $this->entityManager->flush();

        if ($count > 0) {
            $io->success(sprintf('Роль ROLE_ADMIN назначена %d пользователям', $count));
        } else {
            $io->info('Нет пользователей для назначения роли');
        }

        return Command::SUCCESS;
    }
}
