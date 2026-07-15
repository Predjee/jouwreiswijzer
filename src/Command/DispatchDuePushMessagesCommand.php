<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ScheduledPushMessage;
use App\PushMessage\SendPushMessage;
use App\Repository\ScheduledPushMessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:push-messages:dispatch-due',
    description: 'Dispatch due scheduled push messages to the push_messages Messenger queue.',
)]
final class DispatchDuePushMessagesCommand extends Command
{
    public function __construct(
        private readonly ScheduledPushMessageRepository $scheduledPushMessageRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum number of messages to dispatch.', '50');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = $this->limit($input->getOption('limit'));
        $messages = $this->scheduledPushMessageRepository->findPendingDue(new \DateTimeImmutable(), $limit);
        $dispatched = 0;

        foreach ($messages as $scheduledMessage) {
            $id = $scheduledMessage->getId();

            if (null === $id) {
                continue;
            }

            $scheduledMessage->markQueued();
            $this->entityManager->flush();

            try {
                $this->messageBus->dispatch(new SendPushMessage($id));
            } catch (\Throwable $exception) {
                $scheduledMessage
                    ->setStatus(ScheduledPushMessage::STATUS_PENDING)
                    ->setLastError('Queue dispatch failed: ' . $exception->getMessage());
                $this->entityManager->flush();

                throw $exception;
            }

            ++$dispatched;
        }

        $io->success(\sprintf('Dispatched %d push message%s.', $dispatched, 1 === $dispatched ? '' : 's'));

        return Command::SUCCESS;
    }

    private function limit(mixed $value): int
    {
        if (\is_int($value)) {
            return \max(1, $value);
        }

        if (\is_string($value) && 1 === \preg_match('/^\d+$/D', $value)) {
            return \max(1, (int) $value);
        }

        return 50;
    }
}
