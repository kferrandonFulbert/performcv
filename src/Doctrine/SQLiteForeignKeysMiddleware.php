<?php

declare(strict_types=1);

namespace App\Doctrine;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\AbstractSQLiteDriver;
use Doctrine\DBAL\Driver\AbstractSQLiteDriver\Middleware\EnableForeignKeys;
use Doctrine\DBAL\Driver\Middleware;

final class SQLiteForeignKeysMiddleware implements Middleware
{
    public function wrap(Driver $driver): Driver
    {
        return $driver instanceof AbstractSQLiteDriver
            ? (new EnableForeignKeys())->wrap($driver)
            : $driver;
    }
}
