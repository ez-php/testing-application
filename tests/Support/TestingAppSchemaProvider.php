<?php

declare(strict_types=1);

namespace Tests\Support;

use Closure;
use EzPhp\Contracts\DatabaseInterface;
use EzPhp\Contracts\Schema\SchemaInterface;
use EzPhp\Contracts\ServiceProvider;
use PDO;

/**
 * Stand-in for ez-php/orm's SchemaServiceProvider in MigrationBootstrapTest's
 * fixture application: binds a minimal SchemaInterface whose create()/drop()
 * issue plain SQLite DDL. Listed in the fixture's provider/modules.php.
 */
final class TestingAppSchemaProvider extends ServiceProvider
{
    /**
     * @return void
     */
    public function register(): void
    {
        $this->app->bind(SchemaInterface::class, function (): SchemaInterface {
            $pdo = $this->app->make(DatabaseInterface::class)->getPdo();

            return new class ($pdo) implements SchemaInterface {
                public function __construct(private readonly PDO $pdo)
                {
                }

                public function create(string $table, Closure $callback): void
                {
                    $this->pdo->exec("CREATE TABLE \"{$table}\" (id INTEGER PRIMARY KEY)");
                }

                public function table(string $table, Closure $callback): void
                {
                }

                public function drop(string $table): void
                {
                    $this->pdo->exec("DROP TABLE \"{$table}\"");
                }

                public function dropIfExists(string $table): void
                {
                    $this->pdo->exec("DROP TABLE IF EXISTS \"{$table}\"");
                }

                public function hasTable(string $table): bool
                {
                    return false;
                }

                public function hasColumn(string $table, string $column): bool
                {
                    return false;
                }

                public function rename(string $from, string $to): void
                {
                }
            };
        });
    }
}
