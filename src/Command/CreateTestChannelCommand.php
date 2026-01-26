<?php

namespace App\Command;

use App\Entity\Channel;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\String\Slugger\SluggerInterface;

#[AsCommand(
    name: 'app:create-test-channel',
    description: 'Создает тестовый канал для проверки функциональности'
)]
class CreateTestChannelCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private SluggerInterface $slugger
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Найти первого пользователя
        $user = $this->userRepository->findOneBy([]);
        if (!$user) {
            $io->error('Не найден ни один пользователь. Создайте пользователя сначала.');
            return Command::FAILURE;
        }

        // Создать тестовый канал
        $channel = new Channel();
        $channel->setName('Тестовый канал');
        $channel->setDescription('Это тестовый канал для проверки функциональности системы каналов. Здесь будут размещаться различные видео для демонстрации возможностей платформы.');
        $channel->setType(Channel::TYPE_PERSONAL);
        $channel->setOwner($user);
        $channel->setIsActive(true);
        $channel->setIsVerified(true);
        $channel->setPrimaryColor('#3B82F6');
        $channel->setSecondaryColor('#8B5CF6');
        $channel->generateSlug($this->slugger);

        $this->entityManager->persist($channel);
        $this->entityManager->flush();

        $io->success("Тестовый канал '{$channel->getName()}' создан успешно!");
        $io->info("URL канала: /channel/{$channel->getSlug()}");
        $io->info("Владелец: {$user->getUsername()}");

        return Command::SUCCESS;
    }
}