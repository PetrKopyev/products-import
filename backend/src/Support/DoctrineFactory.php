<?php

declare(strict_types=1);

namespace App\Support;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;

/**
 * Фабрика EntityManager: парсит DATABASE_URL, конфигурирует метаданные через атрибуты.
 */
final class DoctrineFactory
{
    public static function create(array $settings, bool $isDev = true): EntityManager
    {
        /** @var array{db: array{url: string}} $settings */
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [__DIR__ . '/../Domain/Entity'],
            isDevMode: $isDev,
            cache: self::createCache($isDev),
        );

        $dbSettings = $settings['db'];
        $connection = DriverManager::getConnection(
            self::connectionParams($dbSettings['url']),
            $config,
        );

        return new EntityManager($connection, $config);
    }

    /** @return array<string, mixed> */
    private static function connectionParams(string $url): array
    {
        if (str_starts_with($url, 'sqlite:')) {
            if ($url === 'sqlite:///:memory:' || str_ends_with($url, '::memory:')) {
                return ['driver' => 'pdo_sqlite', 'memory' => true];
            }

            return ['driver' => 'pdo_sqlite', 'path' => self::sqlitePath($url)];
        }

        $parser = new DsnParser(['pgsql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql', 'mysql' => 'pdo_mysql']);
        /** @var array<string, mixed> $params */
        $params = $parser->parse($url);

        return $params;
    }

    private static function sqlitePath(string $url): string
    {
        $path = (string) preg_replace('/^sqlite:\/\/\//', '', $url);
        $dir = \dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0o775, true);
        }

        return $path;
    }

    private static function createCache(bool $isDev): CacheItemPoolInterface
    {
        if ($isDev) {
            return new ArrayAdapter();
        }

        return new PhpFilesAdapter('doctrine_metadata', 0, __DIR__ . '/../../storage/cache');
    }
}
