<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Services\AnnouncementService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('announcements:sync', function (AnnouncementService $announcements): void {
    $this->info('Updated '.$announcements->processDue().' announcement status records.');
})->purpose('Activate scheduled announcements and expire ended announcements');
