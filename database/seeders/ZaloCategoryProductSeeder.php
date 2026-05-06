<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class ZaloCategoryProductSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production') && !env('ALLOW_VIETPONICS_SEED')) {
            throw new RuntimeException('Refuse to run ZaloCategoryProductSeeder in production. Set ALLOW_VIETPONICS_SEED=1 to override.');
        }

        if (!Schema::hasTable('zalo_categories') || !Schema::hasTable('zalo_products')) {
            throw new RuntimeException('Missing zalo_categories or zalo_products table. Run migrations first.');
        }

        $markdown = $this->loadSeedMarkdown();
        [$categories, $products] = $this->parseSeedData($markdown);

        $tablesToTruncate = [
            'zalo_deliveries',
            'zalo_order_items',
            'zalo_orders',
            'zalo_products',
            'zalo_categories',
            'banners',
            'stations',
        ];

        $driver = DB::getDriverName();

        try {
            $this->truncateTables($driver, $tablesToTruncate);

            DB::transaction(function () use ($categories, $products) {
                DB::table('zalo_categories')->insert($categories);
                $this->chunkInsert('zalo_products', $products, 250);
            });
        } catch (Throwable $e) {
            if ($this->command) {
                $this->command->error($e->getMessage());
            }
            throw $e;
        }
    }

    private function loadSeedMarkdown(): string
    {
        $path = database_path('seeders/vietponics/vietponics_seed_data.md');
        if (!File::exists($path)) {
            throw new RuntimeException("Seed data file not found: {$path}");
        }
        return File::get($path);
    }

    private function parseSeedData(string $markdown): array
    {
        $lines = preg_split("/\r\n|\n|\r/", $markdown) ?: [];

        $categories = [];
        $products = [];
        $currentCategoryId = null;
        $inCategoriesTable = false;
        $inProductsTable = false;

        foreach ($lines as $line) {
            $trim = trim($line);

            if ($trim === '---') {
                $inCategoriesTable = false;
                $inProductsTable = false;
                continue;
            }

            if (preg_match('/^##\s+Categories\b/i', $trim)) {
                $inCategoriesTable = false;
                $inProductsTable = false;
                $currentCategoryId = null;
                continue;
            }

            if (preg_match('/^###\s+Category\s+(\d+)\b/i', $trim, $m)) {
                $currentCategoryId = (int) $m[1];
                $inCategoriesTable = false;
                $inProductsTable = false;
                continue;
            }

            if ($trim === '' || str_starts_with($trim, '```') || str_starts_with($trim, '>')) {
                continue;
            }

            if (
                str_starts_with($trim, '|')
                && str_contains(strtolower($trim), '| id')
                && str_contains(strtolower($trim), '| name')
                && !str_contains(strtolower($trim), 'price')
            ) {
                $inCategoriesTable = true;
                $inProductsTable = false;
                continue;
            }

            if (
                str_starts_with($trim, '|')
                && str_contains(strtolower($trim), 'original_price')
                && str_contains(strtolower($trim), 'detail')
            ) {
                $inProductsTable = true;
                $inCategoriesTable = false;
                continue;
            }

            if (!$inCategoriesTable && !$inProductsTable) {
                continue;
            }

            if (!str_starts_with($trim, '|')) {
                continue;
            }

            if (preg_match('/^\|\s*-+/', $trim)) {
                continue;
            }

            $cells = array_values(array_filter(array_map('trim', explode('|', trim($trim, '|'))), static fn ($v) => $v !== ''));

            if ($inCategoriesTable) {
                if (count($cells) < 2) {
                    continue;
                }
                if (!ctype_digit($cells[0])) {
                    continue;
                }
                $id = (int) $cells[0];
                $name = $cells[1];
                $categories[$id] = ['id' => $id, 'name' => $name, 'image' => null];
                continue;
            }

            if ($inProductsTable) {
                if ($currentCategoryId === null) {
                    throw new RuntimeException('Invalid seed file: products table encountered before category heading.');
                }
                if (count($cells) < 5) {
                    continue;
                }
                if (!ctype_digit($cells[0])) {
                    continue;
                }

                $id = (int) $cells[0];
                $name = $cells[1];
                $price = (int) preg_replace('/[^\d]/', '', $cells[2]);
                $originalPrice = trim($cells[3]) === '' ? null : (int) preg_replace('/[^\d]/', '', $cells[3]);
                $detail = $cells[4] === '' ? null : $cells[4];

                $products[$id] = [
                    'id' => $id,
                    'category_id' => $currentCategoryId,
                    'name' => $name,
                    'price' => $price,
                    'original_price' => $originalPrice,
                    'image' => null,
                    'detail' => $detail,
                ];
            }
        }

        ksort($categories);
        ksort($products);

        if (count($categories) !== 10) {
            throw new RuntimeException('Expected 10 categories from seed file; got '.count($categories).'.');
        }

        if (count($products) !== 125) {
            throw new RuntimeException('Expected 125 products from seed file; got '.count($products).'.');
        }

        $categoryIds = array_flip(array_map(static fn ($c) => $c['id'], $categories));
        foreach ($products as $p) {
            if (!isset($categoryIds[$p['category_id']])) {
                throw new RuntimeException('Product '.$p['id'].' references missing category_id '.$p['category_id'].'.');
            }
        }

        return [array_values($categories), array_values($products)];
    }

    private function truncateTables(string $driver, array $tables): void
    {
        if ($driver === 'pgsql') {
            $existing = array_values(array_filter($tables, static fn ($t) => Schema::hasTable($t)));
            if (!$existing) {
                return;
            }
            $quoted = implode(', ', array_map(static fn ($t) => '"' . str_replace('"', '""', $t) . '"', $existing));
            DB::statement("TRUNCATE {$quoted} RESTART IDENTITY CASCADE");
            return;
        }

        Schema::disableForeignKeyConstraints();
        try {
            foreach ($tables as $table) {
                if (!Schema::hasTable($table)) {
                    continue;
                }
                DB::table($table)->truncate();
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    private function chunkInsert(string $table, array $rows, int $chunkSize): void
    {
        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }
}
