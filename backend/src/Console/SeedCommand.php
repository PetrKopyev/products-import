<?php

declare(strict_types=1);

namespace App\Console;

use App\Domain\Entity\ImportJob;
use App\Domain\Entity\Product;
use App\Domain\Entity\User;
use App\Support\Env;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Fixtures: admin-пользователь + демо-товары с атрибутами и изображениями.
 */
#[AsCommand(name: 'db:seed', description: 'Сидеры: users, products, product_attributes, product_images')]
final class SeedCommand extends Command
{
    public function __construct(private readonly EntityManager $entityManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $em = $this->entityManager;

        // --- users ---
        $adminEmail = Env::string('ADMIN_EMAIL', 'admin@example.com');
        $userRepo = $em->getRepository(User::class)->findOneBy(['email' => $adminEmail]);

        if ($userRepo === null) {
            $user = new User(
                email: $adminEmail,
                passwordHash: password_hash(Env::string('ADMIN_PASSWORD', 'admin123'), PASSWORD_DEFAULT),
                name: Env::string('ADMIN_NAME', 'Администратор'),
            );
            $em->persist($user);
            $output->writeln(sprintf('Создан пользователь %s', $adminEmail));
        } else {
            $output->writeln(sprintf('Пользователь %s уже существует', $adminEmail));
        }

        // --- products + attributes + images ---
        $existing = (int) $em->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from(Product::class, 'p')
            ->getQuery()
            ->getSingleScalarResult();

        if ($existing > 0) {
            $output->writeln(sprintf('Товары уже есть (%d), сидинг пропущен', $existing));
        } else {
            $demoProducts = [
                ['EXT-SEED-001', 'Футболка хлопковая, белая', 'Базовая футболка из 100% хлопка.', '1990.00', '33.33', ['Размер' => 'L', 'Цвет' => 'белый', 'Бренд' => 'DemoBrand'], 'https://placehold.co/600x600.jpg'],
                ['EXT-SEED-002', 'Джинсы прямые, синие', 'Классические прямые джинсы.', '4990.00', '20.00', ['Размер' => '32', 'Цвет' => 'синий'], 'https://placehold.co/600x600.jpg'],
                ['EXT-SEED-003', 'Кроссовки беговые', 'Лёгкие кроссовки для бега.', '7990.00', '25.00', ['Цвет' => 'чёрный', 'Бренд' => 'RunDemo'], null],
            ];

            foreach ($demoProducts as [$code, $name, $description, $price, $discount, $attributes, $imageUrl]) {
                $product = new Product(externalCode: $code, name: $name, description: $description, price: $price, discount: $discount);
                $product->replaceAttributes($attributes);
                if ($imageUrl !== null) {
                    $product->addImage($imageUrl, null);
                }
                $em->persist($product);
            }
            $output->writeln(sprintf('Создано товаров: %d', \count($demoProducts)));
        }

        // --- import_jobs (пример завершённой задачи для отображения отчёта) ---
        $jobs = (int) $em->createQueryBuilder()
            ->select('COUNT(j.id)')
            ->from(ImportJob::class, 'j')
            ->getQuery()
            ->getSingleScalarResult();

        if ($jobs === 0) {
            $job = new ImportJob(status: ImportJob::STATUS_COMPLETED, total: 3, processed: 3);
            $job->addError(5, 'EXT-BAD-ROW', ['«Цена продажи»: пустое значение цены']);
            $em->persist($job);
            $output->writeln('Создан пример завершённой задачи импорта с отчётом об ошибке');
        }

        $em->flush();
        $output->writeln('Сидинг завершён');

        return Command::SUCCESS;
    }
}
