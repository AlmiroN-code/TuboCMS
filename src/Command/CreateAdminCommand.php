<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Создать администратора',
)]
class CreateAdminCommand extends Command
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
            ->addArgument('email', InputArgument::REQUIRED, 'Email администратора')
            ->addArgument('username', InputArgument::REQUIRED, 'Имя пользователя администратора')
            ->addArgument('password', InputArgument::REQUIRED, 'Пароль администратора')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Перезаписать существующего пользователя');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $input->getArgument('email');
        $username = $input->getArgument('username');
        $password = $input->getArgument('password');
        $force = $input->getOption('force');

        // Проверяем, существует ли пользователь с таким email
        $existingUser = $this->entityManager->getRepository(User::class)
            ->findOneBy(['email' => $email]);

        if ($existingUser && !$force) {
            $io->error(sprintf('Пользователь с email "%s" уже существует. Используйте --force для перезаписи.', $email));
            return Command::FAILURE;
        }

        // Проверяем, существует ли пользователь с таким username
        $existingUsername = $this->entityManager->getRepository(User::class)
            ->findOneBy(['username' => $username]);

        if ($existingUsername && $existingUsername !== $existingUser && !$force) {
            $io->error(sprintf('Пользователь с именем "%s" уже существует. Используйте --force для перезаписи.', $username));
            return Command::FAILURE;
        }

        if ($existingUser && $force) {
            // Обновляем существующего пользователя
            $user = $existingUser;
            $user->setUsername($username);
            $io->writeln('Обновляю существующего пользователя...');
        } else {
            // Создаем нового пользователя
            $user = new User();
            $user->setEmail($email);
            $user->setUsername($username);
            $user->setCreatedAt(new \DateTimeImmutable());
            $io->writeln('Создаю нового пользователя...');
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
        
        $this->entityManager->flush();

        $io->success([
            'Администратор успешно создан!',
            sprintf('Email: %s', $email),
            sprintf('Username: %s', $username),
            'Роли: ROLE_ADMIN, ROLE_USER',
            'Статус: Верифицирован, Премиум'
        ]);

        $io->note([
            'Теперь вы можете войти в админ-панель:',
            'URL: /admin',
            sprintf('Email: %s', $email),
            sprintf('Пароль: %s', $password)
        ]);

        return Command::SUCCESS;
    }
}