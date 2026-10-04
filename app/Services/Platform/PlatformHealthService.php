<?php

namespace App\Services\Platform;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class PlatformHealthService
{
    /** @return array<string, array{status:string, label:string, detail:string}> */
    public function snapshot(): array
    {
        return [
            'application' => ['status' => 'healthy', 'label' => 'Healthy', 'detail' => config('app.env').' environment'],
            'database' => $this->database(),
            'queue' => $this->queue(),
            'scheduler' => ['status' => 'warning', 'label' => 'Not Verifiable', 'detail' => 'No scheduler heartbeat is recorded.'],
            'cache' => $this->cache(),
            'storage' => $this->storage(),
            'mail' => $this->mail(),
            'failed_jobs' => $this->failedJobs(),
        ];
    }

    private function database(): array
    {
        try {
            DB::select('select 1');
            return ['status' => 'healthy', 'label' => 'Healthy', 'detail' => 'Database connectivity verified.'];
        } catch (Throwable) {
            return ['status' => 'unavailable', 'label' => 'Unavailable', 'detail' => 'Database connectivity failed.'];
        }
    }

    private function queue(): array
    {
        $connection = (string) config('queue.default');
        $failed = $this->failedJobsCount();
        return ['status' => 'warning', 'label' => $connection ? 'Configured' : 'Not Configured', 'detail' => $connection ? "Connection: {$connection}; failed jobs: {$failed}. No worker heartbeat is recorded, so processing health is unverified." : 'No queue connection is configured.'];
    }

    private function cache(): array
    {
        try {
            Cache::put('platform-health-check', true, now()->addSeconds(10));
            return ['status' => 'healthy', 'label' => 'Healthy', 'detail' => 'Cache write/read verified.'];
        } catch (Throwable) {
            return ['status' => 'unavailable', 'label' => 'Unavailable', 'detail' => 'Cache check failed.'];
        }
    }

    private function storage(): array
    {
        try {
            $disk = (string) config('filesystems.default');
            $ok = Storage::disk($disk)->put('platform-health/.keep', 'ok');
            if ($ok) Storage::disk($disk)->delete('platform-health/.keep');
            return ['status' => $ok ? 'healthy' : 'warning', 'label' => $ok ? 'Healthy' : 'Warning', 'detail' => "Disk: {$disk}; write/read check completed."];
        } catch (Throwable) {
            return ['status' => 'unavailable', 'label' => 'Unavailable', 'detail' => 'Storage check failed.'];
        }
    }

    private function mail(): array
    {
        $mailer = (string) config('mail.default');
        $configured = $mailer !== '' && $mailer !== 'log' && $mailer !== 'array';
        return ['status' => $configured ? 'healthy' : 'warning', 'label' => $configured ? 'Configured' : 'Not Configured', 'detail' => $configured ? "Mailer: {$mailer}." : 'No delivery mailer is configured for operational delivery.'];
    }

    private function failedJobs(): array
    {
        $count = $this->failedJobsCount();
        return ['status' => $count === 0 ? 'healthy' : 'warning', 'label' => $count === 0 ? 'Healthy' : 'Warning', 'detail' => "Failed jobs: {$count}. Payloads are not shown here."];
    }

    private function failedJobsCount(): int
    {
        try {
            return Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
        } catch (Throwable) {
            return 0;
        }
    }
}
