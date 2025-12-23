<?php

namespace App\Command;

use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:check-user',
    description: 'Check user data',
)]
class CheckUserCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('username', InputArgument::REQUIRED, 'Username to check');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $username = $input->getArgument('username');

        $user = $this->userRepository->findOneBy(['username' => $username]);

        if (!$user) {
            $io->error('User not found');
            return Command::FAILURE;
        }

        $io->success('User found: ' . $user->getUsername());
        $io->table(
            ['Field', 'Value'],
            [
                ['ID', $user->getId()],
                ['Username', $user->getUsername()],
                ['Email', $user->getEmail()],
                ['Avatar', $user->getAvatar() ?? 'NULL'],
                ['Cover Image', $user->getCoverImage() ?? 'NULL'],
                ['Country', $user->getCountry() ?? 'NULL'],
                ['City', $user->getCity() ?? 'NULL'],
                ['Gender', $user->getGender() ?? 'NULL'],
                ['Birth Date', $user->getBirthDate()?->format('Y-m-d') ?? 'NULL'],
                ['Age', $user->getAge() ?? 'NULL'],
            ]
        );

        return Command::SUCCESS;
    }
}
