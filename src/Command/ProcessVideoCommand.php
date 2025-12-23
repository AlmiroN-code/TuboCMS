<?php

namespace App\Command;

use App\Message\ProcessVideoEncodingMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:process-video',
    description: 'Manually trigger video processing for a specific video ID',
)]
class ProcessVideoCommand extends Command
{
    public function __construct(
        private MessageBusInterface $messageBus
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('videoId', InputArgument::REQUIRED, 'Video ID to process')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $videoId = (int) $input->getArgument('videoId');

        $io->info("Dispatching video processing message for video ID: {$videoId}");

        try {
            $this->messageBus->dispatch(new ProcessVideoEncodingMessage($videoId));
            $io->success("Message dispatched successfully! Check the worker console for processing logs.");
        } catch (\Exception $e) {
            $io->error("Failed to dispatch message: " . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
