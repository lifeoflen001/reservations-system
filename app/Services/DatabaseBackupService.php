<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class DatabaseBackupService
{
    public function recent(int $limit = 10): array
    {
        $directory = $this->directory();

        if (! File::isDirectory($directory)) {
            return [];
        }

        return collect(File::files($directory))
            ->filter(fn ($file) => strtolower($file->getExtension()) === 'sql')
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->take($limit)
            ->map(fn ($file) => [
                'name' => $file->getFilename(),
                'size' => $file->getSize(),
                'created_at' => Carbon::createFromTimestamp($file->getMTime()),
            ])
            ->values()
            ->all();
    }

    public function create(): string
    {
        File::ensureDirectoryExists($this->directory(), 0750, true);

        $filename = 'hoteldesk-backup-'.now()->format('Ymd-His').'-'.Str::lower(Str::random(6)).'.sql';
        $path = $this->directory().DIRECTORY_SEPARATOR.$filename;
        $driver = DB::connection()->getDriverName();

        $contents = match ($driver) {
            'mysql' => $this->mysqlDump(),
            'sqlite' => $this->sqliteDump(),
            default => throw new RuntimeException("SQL backups are not supported for the {$driver} database driver."),
        };

        File::put($path, $contents);

        return $path;
    }

    private function mysqlDump(): string
    {
        $connection = DB::connection();
        $config = $connection->getConfig();
        $binary = config('hotel.database_backup.binary')
            ?: (PHP_OS_FAMILY === 'Windows' ? 'C:\\xampp\\mysql\\bin\\mysqldump.exe' : 'mysqldump');

        $arguments = [
            '--single-transaction',
            '--quick',
            '--routines',
            '--triggers',
            '--host='.(string) ($config['host'] ?? '127.0.0.1'),
            '--port='.(string) ($config['port'] ?? 3306),
            '--user='.(string) ($config['username'] ?? ''),
            (string) ($config['database'] ?? ''),
        ];
        $environment = [];

        if (! empty($config['password'])) {
            $environment['MYSQL_PWD'] = (string) $config['password'];
        }

        $process = new Process(array_merge([$binary], $arguments), base_path(), $environment);
        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput() ?: $process->getOutput() ?: 'mysqldump failed.'));
        }

        return $process->getOutput();
    }

    private function sqliteDump(): string
    {
        $pdo = DB::connection()->getPdo();
        $tables = DB::select("SELECT name, sql FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
        $sql = [
            '-- HotelDesk SQLite database backup',
            'PRAGMA foreign_keys=OFF;',
            'BEGIN TRANSACTION;',
        ];

        foreach ($tables as $table) {
            if (! empty($table->sql)) {
                $sql[] = rtrim($table->sql, ';').';';
            }

            $rows = DB::table($table->name)->get();
            foreach ($rows as $row) {
                $values = collect((array) $row)
                    ->map(fn ($value) => $value === null ? 'NULL' : $pdo->quote((string) $value))
                    ->implode(', ');
                $columns = collect(array_keys((array) $row))
                    ->map(fn ($column) => '"'.str_replace('"', '""', $column).'"')
                    ->implode(', ');
                $tableName = '"'.str_replace('"', '""', $table->name).'"';
                $sql[] = "INSERT INTO {$tableName} ({$columns}) VALUES ({$values});";
            }
        }

        $sql[] = 'COMMIT;';
        $sql[] = 'PRAGMA foreign_keys=ON;';

        return implode(PHP_EOL, $sql).PHP_EOL;
    }

    private function directory(): string
    {
        return (string) config('hotel.database_backup.path', storage_path('app/backups'));
    }
}
