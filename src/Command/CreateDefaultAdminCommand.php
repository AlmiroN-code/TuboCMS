<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-default-admin',
    description: 'Создать администратора по умолчанию (admin@sexvids.online / admin123)',
)]
class CreateDefaultAdminCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Перезаписать существующего пользователя');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = 'admin@sexvids.online';
        $username = 'admin';
        $password = 'admin123';
        $force = $input->getOption('force');

        $io->title('Создание администратора по умолчанию');

        // Проверяем, существует ли пользователь с таким email
        $existingUser = $this->entityManager->getRepository(User::class)
            ->findOneBy(['email' => $email]);

        if ($existingUser && !$force) {
            $io->warning(sprintf('Пользователь с email "%s" уже существует.', $email));
            $io->note('Используйте --force для перезаписи или измените данные вручную.');
            return Command::SUCCESS;
        }

        if ($existingUser && $force) {
            // Обновляем существующего пользователя
            $user = $existingUser;
            $user->setUsername($username);
            $io->writeln('🔄 Обновляю существующего пользователя...');
        } else {
            // Создаем нового пользователя
            $user = new User();
            $user->setEmail($email);
            $user->setUsername($username);
            $user->setCreatedAt(new \DateTimeImmutable());
            $io->writeln('➕ Создаю нового администратора...');
        }

        // Устанавливаем пароль
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        // Устанавливаем роли администратора
        $user->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
        
        // Устанавливаем дополнительные поля для админа
        $user->setVerified(true);
        $user->setPremium(true);
        $user->setProcessingPriority(1); // Высокий приоритет для админа
        $user->setUpdatedAt(new \DateTimeImmutable());

        // Сохраняем в базу данных
        if (!$existingUser) {
            $this->entityManager->persist($user);
        }
        
        try {
            $this->entityManager->flush();
            
            $io->success([
                '✅ Администратор по умолчанию успешно создан!',
                '',
                '📧 Email: admin@sexvids.online',
                '👤 Username: admin', 
                '🔑 Password: admin123',
                '🛡️  Роли: ROLE_ADMIN, ROLE_USER',
                '✅ Статус: Верифицирован, Премиум'
            ]);

            $io->section('Вход в админ-панель:');
            $io->listing([
                'URL: https://sexvids.online/admin',
                'Email: admin@sexvids.online',
                'Пароль: admin123'
            ]);

            $io->warning([
                '⚠️  ВАЖНО: Смените пароль после первого входа!',
                '⚠️  Этот пароль используется только для первоначальной настройки.'
            ]);

        } catch (\Exception $e) {
            $io->error([
                'Ошибка при создании администратора:',
                $e->getMessage()
            ]);
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}