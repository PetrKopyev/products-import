<?php

declare(strict_types=1);

use App\Console\ConsumeCommand;
use App\Console\ImportFileCommand;
use App\Console\MigrateCommand;
use App\Console\SeedCommand;
use Symfony\Component\Console\Application;

require __DIR__ . '/../vendor/autoload.php';

/** @var Psr\Container\ContainerInterface $container */
$container = (require __DIR__ . '/../config/container.php')();

$app = new Application('products-import', '1.0.0');
$app->add($container->get(MigrateCommand::class));
$app->add($container->get(SeedCommand::class));
$app->add($container->get(ConsumeCommand::class));
$app->add($container->get(ImportFileCommand::class));

$app->run();
