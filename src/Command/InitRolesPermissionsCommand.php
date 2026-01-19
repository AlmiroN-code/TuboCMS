<?php

namespace App\Command;

use App\Service\RolePermissionService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:init-roles-permissions',
    description: 'Инициализация базовых ролей и разрешений'
)]
class InitRolesPermissionsCommand extends Command
{
    public function __construct(
        private RolePermissionService $rolePermissionService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Инициализация ролей и разрешений');

        try {
            $this->rolePermissionService->initializeDefaultRolesAndPermissions();
            $io->success('Роли и разрешения успешно инициализированы!');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Ошибка при инициализации: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}