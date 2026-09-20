<?php

namespace App\Console\Commands;

use App\Enums\HousekeepingStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\RoomOperationalStatus;
use App\Enums\TaskStatus;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DiagnoseData extends Command
{
    protected $signature = 'pms:diagnose-data {--json : Print the diagnostic report as JSON}';

    protected $description = 'Read-only audit of the PMS database connection, records, relationships, and status values';

    /**
     * The command deliberately reads through the same default connection used
     * by the application. It never seeds, repairs, caches, or mutates records.
     */
    public function handle(): int
    {
        try {
            $report = $this->report(DB::connection());
        } catch (Throwable $exception) {
            $this->error('PMS data diagnostic failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return $report['healthy'] ? self::SUCCESS : self::FAILURE;
        }

        $this->info('PMS data consistency diagnostic (read-only)');
        $this->line('Connection: '.$report['connection']['driver'].' / '.$report['connection']['database']);
        $this->line('Server database: '.$report['connection']['server_database']);
        $this->line('Cache: '.$report['runtime']['cache'].' | Queue: '.$report['runtime']['queue']);
        $this->line('Config cached: '.($report['runtime']['config_cached'] ? 'yes' : 'no').' | Routes cached: '.($report['runtime']['routes_cached'] ? 'yes' : 'no'));
        $this->line('Pending migrations: '.($report['migrations']['pending'] === [] ? 'none' : implode(', ', $report['migrations']['pending'])));
        $this->newLine();

        $this->info('Operational rows');
        foreach ($report['counts'] as $table => $count) {
            $this->line(str_pad($table, 24).$count);
        }

        $this->newLine();
        $this->info('Integrity checks');
        $this->line('Foreign-key orphans: '.$report['integrity']['foreign_key_orphans']);
        $this->line('Invalid status values: '.$report['integrity']['invalid_status_values']);
        $this->line('NULL active flags: '.$report['integrity']['null_active_flags']);
        $this->line('Soft-deleted reservations: '.$report['integrity']['soft_deleted_reservations']);

        if ($report['healthy']) {
            $this->newLine();
            $this->info('Result: no data consistency anomalies detected.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->error('Result: one or more data consistency anomalies require review.');

        return self::FAILURE;
    }

    /** @return array<string, mixed> */
    private function report(ConnectionInterface $connection): array
    {
        $serverDatabase = $connection->getDatabaseName();
        if ($connection->getDriverName() === 'mysql') {
            $serverDatabase = (string) ($connection->selectOne('select database() as database_name')->database_name ?? $serverDatabase);
        }

        $tables = [
            'properties', 'users', 'clients', 'floors', 'room_categories', 'room_types', 'rooms',
            'reservations', 'payments', 'invoices', 'housekeeping_tasks', 'maintenance_tasks',
            'room_blocks', 'reservation_sources',
        ];
        $counts = [];
        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                $counts[$table] = DB::table($table)->count();
            }
        }

        $orphanChecks = [
            ['rooms', 'floor_id', 'floors'],
            ['rooms', 'room_category_id', 'room_categories'],
            ['rooms', 'room_type_id', 'room_types'],
            ['reservations', 'client_id', 'clients'],
            ['reservations', 'room_id', 'rooms'],
            ['reservations', 'reservation_source_id', 'reservation_sources'],
            ['payments', 'reservation_id', 'reservations'],
            ['payments', 'client_id', 'clients'],
            ['invoices', 'payment_id', 'payments'],
            ['housekeeping_tasks', 'room_id', 'rooms'],
            ['housekeeping_tasks', 'assignee_id', 'users'],
            ['maintenance_tasks', 'room_id', 'rooms'],
            ['maintenance_tasks', 'assignee_id', 'users'],
        ];
        $foreignKeyOrphans = 0;
        foreach ($orphanChecks as [$child, $column, $parent]) {
            if (! Schema::hasTable($child) || ! Schema::hasTable($parent) || ! Schema::hasColumn($child, $column)) {
                continue;
            }
            $foreignKeyOrphans += DB::table($child)
                ->leftJoin($parent, "$child.$column", '=', "$parent.id")
                ->whereNotNull("$child.$column")
                ->whereNull("$parent.id")
                ->count();
        }

        $nullActiveFlags = 0;
        foreach (['users', 'clients', 'rooms', 'room_categories', 'room_types', 'floors', 'reservation_sources'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'is_active')) {
                $nullActiveFlags += DB::table($table)->whereNull('is_active')->count();
            }
        }

        $invalidStatusValues = 0;
        $statusChecks = [
            ['rooms', 'operational_status', array_map(fn (RoomOperationalStatus $status) => $status->value, RoomOperationalStatus::cases())],
            ['rooms', 'housekeeping_status', array_map(fn (HousekeepingStatus $status) => $status->value, HousekeepingStatus::cases())],
            ['reservations', 'status', array_map(fn (ReservationStatus $status) => $status->value, ReservationStatus::cases())],
            ['payments', 'status', array_map(fn (PaymentStatus $status) => $status->value, PaymentStatus::cases())],
            ['housekeeping_tasks', 'status', array_map(fn (TaskStatus $status) => $status->value, TaskStatus::cases())],
            ['maintenance_tasks', 'status', array_map(fn (TaskStatus $status) => $status->value, TaskStatus::cases())],
        ];
        foreach ($statusChecks as [$table, $column, $allowed]) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                $invalidStatusValues += DB::table($table)->whereNotIn($column, $allowed)->count();
            }
        }

        $integrity = [
            'foreign_key_orphans' => $foreignKeyOrphans,
            'invalid_status_values' => $invalidStatusValues,
            'null_active_flags' => $nullActiveFlags,
            'soft_deleted_reservations' => Schema::hasColumn('reservations', 'deleted_at')
                ? DB::table('reservations')->whereNotNull('deleted_at')->count()
                : 0,
        ];

        $ranMigrations = Schema::hasTable('migrations')
            ? DB::table('migrations')->pluck('migration')->all()
            : [];
        $ranMigrations = array_fill_keys($ranMigrations, true);
        $pendingMigrations = [];
        foreach (glob(database_path('migrations/*.php')) ?: [] as $migrationFile) {
            $migration = pathinfo($migrationFile, PATHINFO_FILENAME);
            if (! isset($ranMigrations[$migration])) {
                $pendingMigrations[] = $migration;
            }
        }

        return [
            'healthy' => $foreignKeyOrphans === 0 && $invalidStatusValues === 0 && $nullActiveFlags === 0 && $pendingMigrations === [],
            'connection' => [
                'driver' => $connection->getDriverName(),
                'database' => $connection->getDatabaseName(),
                'server_database' => $serverDatabase,
            ],
            'runtime' => [
                'cache' => (string) config('cache.default'),
                'queue' => (string) config('queue.default'),
                'config_cached' => app()->configurationIsCached(),
                'routes_cached' => app()->routesAreCached(),
            ],
            'migrations' => ['pending' => $pendingMigrations],
            'counts' => $counts,
            'integrity' => $integrity,
        ];
    }
}
