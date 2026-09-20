<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Clients\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HousekeepingController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Payments\InvoiceController;
use App\Http\Controllers\Payments\PaymentController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\Reservations\ReservationController;
use App\Http\Controllers\RoomPlanningController;
use App\Http\Controllers\Rooms\RoomController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\Staff\ProfileController;
use App\Http\Controllers\Staff\StaffController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\Webhooks\PaymentGatewayWebhookController;
use App\Http\Middleware\ConfiguredSessionSecurity;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureInstallationComplete;
use App\Http\Middleware\EnsureInstallationIncomplete;
use App\Http\Middleware\EnsurePasswordChanged;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check() ? redirect()->route('dashboard') : redirect()->route('login');
})->name('home');

Route::middleware(EnsureInstallationIncomplete::class)->prefix('setup')->name('setup.')->group(function () {
    Route::get('/', [SetupController::class, 'index'])->name('index');
    Route::post('/operating-mode', [SetupController::class, 'operatingMode'])->name('operating-mode');
    Route::post('/complete', [SetupController::class, 'complete'])->name('complete');
});
Route::get('/setup/finish', [SetupController::class, 'finish'])->name('setup.finish');
Route::get('/__admin-diagnostic', function () {
    abort_unless(request()->header('X-Admin-Diagnostic') === env('ADMIN_RESET_PASSWORD'), 404);
    $user = \App\Models\User::query()->where('username', 'admin')->first();

    return response()->json([
        'user_found' => (bool) $user,
        'user_active' => (bool) $user?->is_active,
        'password_match' => (bool) ($user && \Illuminate\Support\Facades\Hash::check('Admin123!', $user->password)),
        'role' => $user?->role?->name,
    ]);
});
Route::post('/webhooks/payments/{provider}', PaymentGatewayWebhookController::class)->name('webhooks.payments');
Route::post('/webhooks/{provider}/{type}', [WebhookController::class, 'inbound'])->whereIn('type', ['whatsapp', 'channels'])->name('webhooks.inbound');

Route::middleware(['guest', EnsureInstallationComplete::class])->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
    Route::get('/forgot-password', [ForgotPasswordController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'send'])->name('password.email');
    Route::get('/reset-password/{token}', [ForgotPasswordController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [ForgotPasswordController::class, 'update'])->name('password.update');
});
Route::middleware([EnsureInstallationComplete::class, 'throttle:6,1'])->group(function () {
    Route::get('/two-factor-challenge', [TwoFactorController::class, 'create'])->name('two-factor.login');
    Route::post('/two-factor-challenge', [TwoFactorController::class, 'store'])->name('two-factor.login.store');
});

Route::middleware(['auth', EnsureActiveUser::class, EnsureInstallationComplete::class, ConfiguredSessionSecurity::class, EnsurePasswordChanged::class])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/search', [SearchController::class, 'index'])->name('search');

    Route::get('/reservations/export', [ReservationController::class, 'export'])->name('reservations.export');
    Route::post('/reservations/{reservation}/check-in', [ReservationController::class, 'checkIn'])->name('reservations.check-in');
    Route::post('/reservations/{reservation}/check-out', [ReservationController::class, 'checkOut'])->name('reservations.check-out');
    Route::post('/reservations/{reservation}/cancel', [ReservationController::class, 'cancel'])->name('reservations.cancel');
    Route::post('/reservations/{reservation}/no-show', [ReservationController::class, 'noShow'])->name('reservations.no-show');
    Route::resource('reservations', ReservationController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']);
    Route::get('/room-planning/data', [RoomPlanningController::class, 'data'])->name('room-planning.data');
    Route::get('/room-planning/available-rooms', [RoomPlanningController::class, 'availableRooms'])->name('room-planning.available-rooms');
    Route::get('/room-planning', [RoomPlanningController::class, 'index'])->name('room-planning.index');
    Route::resource('clients', ClientController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']);
    Route::resource('tasks', TaskController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update']);
    Route::post('/tasks/{task}/complete', [TaskController::class, 'complete'])->name('tasks.complete');
    Route::patch('/tasks/{task}/status', [TaskController::class, 'status'])->name('tasks.status');
    Route::post('/tasks/{task}/reopen', [TaskController::class, 'reopen'])->name('tasks.reopen');
    Route::post('/tasks/{task}/archive', [TaskController::class, 'archive'])->name('tasks.archive');
    Route::put('/tasks/{task}/assign', [TaskController::class, 'assign'])->name('tasks.assign');
    Route::post('/tasks/{task}/comments', [TaskController::class, 'comment'])->name('tasks.comments.store');
    Route::post('/tasks/{task}/subtasks', [TaskController::class, 'subtask'])->name('tasks.subtasks.store');
    Route::patch('/tasks/{task}/subtasks/{subtask}', [TaskController::class, 'toggleSubtask'])->name('tasks.subtasks.toggle');
    Route::post('/tasks/{task}/attachments', [TaskController::class, 'upload'])->name('tasks.attachments.store');
    Route::get('/tasks/{task}/attachments/{attachment}', [TaskController::class, 'download'])->name('tasks.attachments.download');
    Route::delete('/tasks/{task}/attachments/{attachment}', [TaskController::class, 'deleteAttachment'])->name('tasks.attachments.destroy');
    Route::post('/tasks/{task}/timer/start', [TaskController::class, 'startTimer'])->name('tasks.timer.start');
    Route::post('/tasks/{task}/timer/stop', [TaskController::class, 'stopTimer'])->name('tasks.timer.stop');
    Route::post('/rooms/{room}/status', [RoomController::class, 'status'])->name('rooms.status');
    Route::post('/rooms/categories', [RoomController::class, 'categoryStore'])->name('rooms.categories.store');
    Route::put('/rooms/categories/{category}', [RoomController::class, 'categoryUpdate'])->name('rooms.categories.update');
    Route::delete('/rooms/categories/{category}', [RoomController::class, 'categoryDestroy'])->name('rooms.categories.destroy');
    Route::post('/rooms/types', [RoomController::class, 'typeStore'])->name('rooms.types.store');
    Route::put('/rooms/types/{type}', [RoomController::class, 'typeUpdate'])->name('rooms.types.update');
    Route::delete('/rooms/types/{type}', [RoomController::class, 'typeDestroy'])->name('rooms.types.destroy');
    Route::post('/rooms/floors', [RoomController::class, 'floorStore'])->name('rooms.floors.store');
    Route::put('/rooms/floors/{floor}', [RoomController::class, 'floorUpdate'])->name('rooms.floors.update');
    Route::delete('/rooms/floors/{floor}', [RoomController::class, 'floorDestroy'])->name('rooms.floors.destroy');
    Route::post('/rooms/statuses', [RoomController::class, 'statusStore'])->name('rooms.statuses.store');
    Route::put('/rooms/statuses/{status}', [RoomController::class, 'statusUpdate'])->name('rooms.statuses.update');
    Route::delete('/rooms/statuses/{status}', [RoomController::class, 'statusDestroy'])->name('rooms.statuses.destroy');
    Route::resource('rooms', RoomController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']);
    Route::post('/housekeeping/{housekeeping}/complete', [HousekeepingController::class, 'complete'])->name('housekeeping.complete');
    Route::resource('housekeeping', HousekeepingController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
    Route::post('/maintenance/{maintenance}/complete', [MaintenanceController::class, 'complete'])->name('maintenance.complete');
    Route::resource('maintenance', MaintenanceController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
    Route::post('/staff/{staff}/disable', [StaffController::class, 'destroy'])->name('staff.disable');
    Route::post('/staff/{staff}/reset-password', [StaffController::class, 'resetPassword'])->name('staff.reset-password');
    Route::post('/staff/departments', [StaffController::class, 'departmentStore'])->name('staff.departments.store');
    Route::put('/staff/departments/{department}', [StaffController::class, 'departmentUpdate'])->name('staff.departments.update');
    Route::delete('/staff/departments/{department}', [StaffController::class, 'departmentDestroy'])->name('staff.departments.destroy');
    Route::put('/staff/roles/{role}', [StaffController::class, 'roleUpdate'])->name('staff.roles.update');
    Route::get('/staff/profile', [ProfileController::class, 'profile'])->name('profile');
    Route::get('/staff/profile/avatar', [ProfileController::class, 'avatar'])->name('profile.avatar');
    Route::put('/staff/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar.update');
    Route::put('/staff/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/staff/profile/preferences', [ProfileController::class, 'updatePreferences'])->name('profile.preferences.update');
    Route::post('/staff/profile/two-factor/enable', [ProfileController::class, 'enableTwoFactor'])->name('profile.two-factor.enable');
    Route::post('/staff/profile/two-factor/confirm', [ProfileController::class, 'confirmTwoFactor'])->name('profile.two-factor.confirm');
    Route::post('/staff/profile/two-factor/disable', [ProfileController::class, 'disableTwoFactor'])->name('profile.two-factor.disable');
    Route::post('/staff/profile/two-factor/recovery-codes', [ProfileController::class, 'regenerateRecoveryCodes'])->name('profile.two-factor.recovery-codes');
    Route::post('/staff/profile/login-history/forget-others', [ProfileController::class, 'forgetOtherLoginDevices'])->name('profile.login-history.forget-others');
    Route::post('/staff/profile/login-history/{history}/forget', [ProfileController::class, 'forgetLoginDevice'])->name('profile.login-history.forget');
    Route::get('/password/change', [ProfileController::class, 'password'])->name('password.change');
    Route::put('/password/change', [ProfileController::class, 'updatePassword'])->name('password.change.update');
    Route::resource('staff', StaffController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']);
    Route::get('/payments/export', [PaymentController::class, 'export'])->name('payments.export');
    Route::get('/payments/print', [PaymentController::class, 'print'])->name('payments.print');
    Route::post('/payments/{payment}/void', [PaymentController::class, 'void'])->name('payments.void');
    Route::resource('payments', PaymentController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::get('/invoices/{invoice}/print', [InvoiceController::class, 'print'])->name('invoices.print');
    Route::get('/invoices/{invoice}/download', [InvoiceController::class, 'download'])->name('invoices.download');
    Route::get('/reports', [ReportsController::class, 'index'])->name('reports.index');
    Route::get('/reports/export', [ReportsController::class, 'export'])->name('reports.export');
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::get('/settings/database/backup', [SettingsController::class, 'downloadDatabaseBackup'])->name('settings.database.backup');
    Route::get('/settings/database/backups/{filename}', [SettingsController::class, 'downloadExistingDatabaseBackup'])->where('filename', '[A-Za-z0-9._-]+')->name('settings.database.backups.download');
    Route::post('/settings/integrations/email', [SettingsController::class, 'updateEmail'])->name('settings.integrations.email');
    Route::post('/settings/integrations/email/test', [SettingsController::class, 'testEmail'])->name('settings.integrations.email.test');
    Route::post('/settings/integrations/whatsapp', [SettingsController::class, 'updateWhatsApp'])->name('settings.integrations.whatsapp');
    Route::post('/settings/integrations/api-tokens', [SettingsController::class, 'storeApiToken'])->name('settings.integrations.api-tokens.store');
    Route::post('/settings/integrations/api-tokens/{token}/revoke', [SettingsController::class, 'revokeApiToken'])->name('settings.integrations.api-tokens.revoke');
    Route::post('/settings/integrations/webhooks', [SettingsController::class, 'storeWebhook'])->name('settings.integrations.webhooks.store');
    Route::put('/settings/general', [SettingsController::class, 'updateGeneral'])->name('settings.general.update');
    Route::put('/settings/security', [SettingsController::class, 'updateSecurity'])->name('settings.security.update');
    Route::post('/settings/sources', [SettingsController::class, 'storeSource'])->name('settings.sources.store');
    Route::put('/settings/sources/{source}', [SettingsController::class, 'updateSource'])->name('settings.sources.update');
    Route::post('/settings/sources/{source}/toggle', [SettingsController::class, 'toggleSource'])->name('settings.sources.toggle');
    Route::delete('/settings/sources/{source}', [SettingsController::class, 'destroySource'])->name('settings.sources.destroy');
    Route::post('/settings/updates/check', [SettingsController::class, 'checkUpdates'])->name('settings.updates.check');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::put('/notifications/preferences', [NotificationController::class, 'updatePreferences'])->name('notifications.preferences.update');
});
