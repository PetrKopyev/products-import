<?php

declare(strict_types=1);

namespace App\Support;

use App\Migrations\Version20261106000000;
use Doctrine\Migrations\Configuration\Configuration;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\ExistingConfiguration;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Metadata\AvailableMigration;
use Doctrine\Migrations\Metadata\Storage\TableMetadataStorageConfiguration;
use Doctrine\Migrations\MigratorConfiguration;
use Doctrine\Migrations\Query\Query;
use Doctrine\Migrations\Version\Direction;
use Doctrine\Migrations\Version\Version;
use Doctrine\ORM\EntityManager;

/**
 * Программный запуск миграций (без symfony/console от doctrine).
 */
final class MigrationRunner
{
    /** @return array<string> выполненные SQL-выражения */
    public static function migrate(EntityManager $em): array
    {
        $configuration = new Configuration();
        $configuration->addMigrationClass(Version20261106000000::class);

        $metadataStorage = new TableMetadataStorageConfiguration();
        $configuration->setMetadataStorageConfiguration($metadataStorage);

        $dependencyFactory = DependencyFactory::fromConnection(
            new ExistingConfiguration($configuration),
            new ExistingConnection($em->getConnection()),
        );
        $dependencyFactory->getMetadataStorage()->ensureInitialized();

        $newMigrations = $dependencyFactory->getMigrationStatusCalculator()->getNewMigrations();
        if (\count($newMigrations) === 0) {
            return [];
        }

        $versions = array_map(
            static fn (AvailableMigration $migration): Version => $migration->getVersion(),
            $newMigrations->getItems(),
        );

        $plan = $dependencyFactory->getMigrationPlanCalculator()->getPlanForVersions($versions, Direction::UP);
        $migratorConfiguration = (new MigratorConfiguration())->setDryRun(false);

        $executed = $dependencyFactory->getMigrator()->migrate($plan, $migratorConfiguration);

        /** @var array<string, array<Query>> $executed */
        $sql = [];
        foreach ($executed as $version => $queries) {
            foreach ($queries as $query) {
                $sql[] = sprintf('[%s] %s', $version, $query->getStatement());
            }
        }

        return $sql;
    }
}
