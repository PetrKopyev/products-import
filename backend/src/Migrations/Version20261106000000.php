<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261106000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema: products, product_attributes, product_images, import_jobs, users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
                CREATE TABLE products (
                    id SERIAL PRIMARY KEY,
                    external_code VARCHAR(64) NOT NULL UNIQUE,
                    name VARCHAR(512) NOT NULL,
                    description TEXT DEFAULT NULL,
                    price NUMERIC(12,2) NOT NULL,
                    discount NUMERIC(6,2) DEFAULT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                )
            SQL);

        $this->addSql(<<<'SQL'
                CREATE TABLE product_attributes (
                    id SERIAL PRIMARY KEY,
                    product_id INT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
                    "key" VARCHAR(255) NOT NULL,
                    value TEXT NOT NULL
                )
            SQL);
        $this->addSql('CREATE INDEX idx_attribute_product ON product_attributes (product_id)');

        $this->addSql(<<<'SQL'
                CREATE TABLE product_images (
                    id SERIAL PRIMARY KEY,
                    product_id INT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
                    url TEXT NOT NULL,
                    path VARCHAR(255) DEFAULT NULL
                )
            SQL);
        $this->addSql('CREATE INDEX idx_image_product ON product_images (product_id)');

        $this->addSql(<<<'SQL'
                CREATE TABLE import_jobs (
                    id SERIAL PRIMARY KEY,
                    status VARCHAR(32) NOT NULL DEFAULT 'pending',
                    total INT NOT NULL DEFAULT 0,
                    processed INT NOT NULL DEFAULT 0,
                    errors JSON NOT NULL DEFAULT '[]',
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                )
            SQL);

        $this->addSql(<<<'SQL'
                CREATE TABLE users (
                    id SERIAL PRIMARY KEY,
                    email VARCHAR(255) NOT NULL UNIQUE,
                    password_hash VARCHAR(255) NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS product_attributes');
        $this->addSql('DROP TABLE IF EXISTS product_images');
        $this->addSql('DROP TABLE IF EXISTS import_jobs');
        $this->addSql('DROP TABLE IF EXISTS products');
        $this->addSql('DROP TABLE IF EXISTS users');
    }
}
