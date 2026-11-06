<?php

declare(strict_types=1);

namespace App\Console;

use App\Domain\Entity\ImportJob;
use App\Service\ImportService;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Прямой запуск импорта из консоли (dev-инструмент, без очереди).
 */
#[AsCommand(name: 'import:file', description: 'Импортировать .xlsx файл напрямую (без очереди)')]
final class ImportFileCommand extends Command
{
    public function __construct(
        private readonly ImportService $importService,
        private readonly EntityManager $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('path', InputArgument::REQUIRED, 'Путь к .xlsx файлу');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $path */
        $path = $input->getArgument('path');

        $job = new ImportJob();
        $this->entityManager->persist($job);
        $this->entityManager->flush();

        $output->writeln(sprintf('Создана задача #%d, импорт файла: %s', $job->getId(), $path));

        $stats = $this->importService->importFromFile($job->getId(), $path);

        foreach ($stats['warnings'] as $warning) {
            $output->writeln('⚠ ' . $warning);
        }
        $output->writeln(sprintf(
            'Готово: строк=%d, создано=%d, обновлено=%d',
            $stats['rows'],
            $stats['created'],
            $stats['updated'],
        ));

        return Command::SUCCESS;
    }
}
