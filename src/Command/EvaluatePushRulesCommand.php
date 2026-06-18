<?php

declare(strict_types=1);

namespace App\Command;

use App\PushMessage\PushRuleEvaluator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:push-rules:evaluate',
    description: 'Evaluate active push rules and create scheduled push messages.',
)]
final class EvaluatePushRulesCommand extends Command
{
    public function __construct(
        private readonly PushRuleEvaluator $pushRuleEvaluator,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $created = $this->pushRuleEvaluator->evaluate();

        $io->success(\sprintf('Created %d scheduled push message%s.', $created, 1 === $created ? '' : 's'));

        return Command::SUCCESS;
    }
}
