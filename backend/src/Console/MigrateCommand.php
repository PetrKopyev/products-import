<?php

declare(strict_types=1);

namespace App\Console;

use App\Support\Env;
use App\Support\MigrationRunner;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:migrate', description: 'Накатить миграции Doctrine')]
final class MigrateCommand extends Command
{
    public function __construct(private readonly EntityManager $entityManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sql = MigrationRunner::migrate($this->entityManager);

        if ($sql === []) {
            $output->writeln('База данных уже актуальна, новых миграций нет.');
        } else {
            foreach ($sql as $statement) {
                $output->writeln('✔ ' . $statement);
            }
            $output->writeln(sprintf('Миграций применено: %d', \count($sql)));
        }

        $output->writeln(sprintf('DSN: %s', Env::string('DATABASE_URL', '(default)')));

        return Command::SUCCESS;
    }
}
