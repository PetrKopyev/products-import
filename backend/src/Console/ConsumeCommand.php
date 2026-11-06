<?php

declare(strict_types=1);

namespace App\Console;

use App\Support\MessengerFactory;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'messenger:consume', description: 'Консьюмер очереди импорта (RabbitMQ через Symfony Messenger)')]
final class ConsumeCommand extends Command
{
    public function __construct(private readonly ContainerInterface $container)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Консьюмер запущен. Остановка: Ctrl+C.');

        MessengerFactory::runConsumer($this->container);

        return Command::SUCCESS;
    }
}
