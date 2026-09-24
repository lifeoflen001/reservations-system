<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $mysqlIntegration = app()->environment('testing')
            && filter_var(env('POS_MYSQL_INTEGRATION', false), FILTER_VALIDATE_BOOL)
            && config('database.default') === 'mysql'
            && str_starts_with((string) config('database.connections.mysql.database'), 'reservations_pos_integration_')
            && ! str_contains((string) config('database.connections.mysql.database'), 'reservations_db');
        $sqlite = app()->environment('testing')
            && config('database.default') === 'sqlite'
            && config('database.connections.sqlite.database') === ':memory:';

        if (! $sqlite && ! $mysqlIntegration) {
            throw new RuntimeException('Automated tests must use APP_ENV=testing with the isolated SQLite :memory: database.');
        }
    }
}
