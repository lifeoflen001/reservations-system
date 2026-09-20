<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\ChangePasswordRequest;
use App\Http\Requests\Staff\ConfirmTwoFactorRequest;
use App\Http\Requests\Staff\DisableTwoFactorRequest;
use App\Http\Requests\Staff\EnableTwoFactorRequest;
use App\Http\Requests\Staff\UpdateAvatarRequest;
use App\Http\Requests\Staff\UpdateProfilePreferencesRequest;
use App\Http\Requests\Staff\UpdateProfileRequest;
use App\Models\Language;
use App\Models\LoginHistory;
use App\Models\UserNotificationPreference;
use App\Models\UserPreference;
use App\Services\LoginHistoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ProfileController extends Controller
{
    public function profile(Request $request): View
    {
        return view('staff.profile', [
            'staff' => $request->user()->loadMissing(['role', 'department', 'language']),
            'languages' => Language::where('is_active', true)->orderBy('name')->get(),
            'preferences' => $request->user()->notificationPreferences()->first(),
            'userPreferences' => $request->user()->preferences()->first(),
            'section' => $request->string('section')->toString() ?: 'profile',
            'recoveryCodes' => $request->session()->get('profile.recovery_codes'),
            'loginHistories' => $request->user()->loginHistories()->latest('login_at')->limit(25)->get(),
            'currentSessionId' => $request->session()->getId(),
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['name'] = trim($data['first_name'].' '.$data['last_name']);
        $data['updated_by'] = $request->user()->id;
        $user = $request->user();
        $oldAvatarPath = $user->avatar_path;
        $newAvatarPath = null;

        if ($request->hasFile('avatar')) {
            $newAvatarPath = $request->file('avatar')->store('profile-avatars', 'public');
            $data['avatar_path'] = $newAvatarPath;
        } elseif ($request->boolean('remove_avatar')) {
            $data['avatar_path'] = null;
        }

        try {
            $user->update($data);
        } catch (Throwable $exception) {
            if ($newAvatarPath) {
                Storage::disk('public')->delete($newAvatarPath);
            }

            throw $exception;
        }

        if ($oldAvatarPath && $oldAvatarPath !== $user->avatar_path) {
            Storage::disk('public')->delete($oldAvatarPath);
        }

        return back()->with('success', 'Profile updated.');
    }

    public function avatar(Request $request): StreamedResponse
    {
        $disk = Storage::disk('public');
        $path = $request->user()->avatar_path;
        abort_unless($path && $disk->exists($path), 404);

        $stream = $disk->readStream($path);
        abort_unless(is_resource($stream), 404);
        $mime = $disk->mimeType($path) ?: 'image/jpeg';
        $size = $disk->size($path);

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Cache-Control' => 'private, max-age=3600',
            'Content-Type' => $mime,
            'Content-Length' => (string) $size,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function updateAvatar(UpdateAvatarRequest $request): RedirectResponse
    {
        $user = $request->user();
        $oldAvatarPath = $user->avatar_path;
        $newAvatarPath = null;

        try {
            if ($request->hasFile('avatar')) {
                $newAvatarPath = $request->file('avatar')->store('profile-avatars', 'public');
                $user->update(['avatar_path' => $newAvatarPath, 'updated_by' => $user->id]);
            } elseif ($request->boolean('remove_avatar')) {
                $user->update(['avatar_path' => null, 'updated_by' => $user->id]);
            } else {
                return back()->with('warning', 'Choose a profile picture or select remove first.');
            }
        } catch (Throwable $exception) {
            if ($newAvatarPath) {
                Storage::disk('public')->delete($newAvatarPath);
            }

            throw $exception;
        }

        if ($oldAvatarPath && $oldAvatarPath !== $user->fresh()->avatar_path) {
            Storage::disk('public')->delete($oldAvatarPath);
        }

        return back()->with('success', $newAvatarPath ? 'Profile picture updated.' : 'Profile picture removed.');
    }

    public function updatePreferences(UpdateProfilePreferencesRequest $request): RedirectResponse
    {
        UserPreference::updateOrCreate(
            ['user_id' => $request->user()->id],
            ['theme' => $request->input('theme', 'system')],
        );
        UserNotificationPreference::updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'channels' => $request->input('channels', ['in_app']),
                'categories' => $request->input('categories', ['operational', 'financial', 'integrations']),
            ],
        );

        return redirect()->route('profile', ['section' => 'preferences'])->with('success', 'Preferences saved.');
    }

    public function enableTwoFactor(EnableTwoFactorRequest $request, EnableTwoFactorAuthentication $enable): RedirectResponse
    {
        $enable($request->user(), true);

        return redirect()->route('profile', ['section' => 'two-factor'])->with('success', 'Authenticator setup started. Scan the QR code and confirm it to finish.');
    }

    public function confirmTwoFactor(ConfirmTwoFactorRequest $request, ConfirmTwoFactorAuthentication $confirm): RedirectResponse
    {
        $confirm($request->user(), $request->validated('code'));

        return redirect()->route('profile', ['section' => 'two-factor'])->with('success', 'Two-factor authentication is now enabled.');
    }

    public function disableTwoFactor(DisableTwoFactorRequest $request, DisableTwoFactorAuthentication $disable, TwoFactorAuthenticationProvider $provider): RedirectResponse
    {
        $user = $request->user();
        $valid = false;
        if ($request->filled('code') && $user->two_factor_secret) {
            $valid = $provider->verify(Fortify::currentEncrypter()->decrypt($user->two_factor_secret), $request->validated('code'));
        } elseif ($request->filled('recovery_code') && $user->two_factor_recovery_codes) {
            $validCode = collect($user->recoveryCodes())->first(fn (string $code): bool => hash_equals($code, $request->validated('recovery_code')));
            if ($validCode) {
                $user->replaceRecoveryCode($validCode);
                $valid = true;
            }
        }
        if (! $valid) {
            return back()->withErrors(['code' => 'The verification code is invalid.'])->withInput()->with('section', 'two-factor');
        }

        $disable($user);

        return redirect()->route('profile', ['section' => 'two-factor'])->with('success', 'Two-factor authentication has been disabled.');
    }

    public function regenerateRecoveryCodes(Request $request, GenerateNewRecoveryCodes $generate): RedirectResponse
    {
        abort_unless($request->user()->hasEnabledTwoFactorAuthentication(), 422);
        $request->validate(['current_password' => ['required', 'current_password']]);
        $generate($request->user());
        $request->session()->flash('profile.recovery_codes', $request->user()->fresh()->recoveryCodes());

        return redirect()->route('profile', ['section' => 'two-factor'])->with('success', 'New recovery codes generated. Store them in a secure place.');
    }

    public function forgetLoginDevice(Request $request, LoginHistory $history, LoginHistoryService $loginHistory): RedirectResponse
    {
        abort_unless($history->user_id === $request->user()->id, 404);

        if ($history->session_id === $request->session()->getId()) {
            return back()->with('warning', 'This is your current device. Sign out from the account menu instead.');
        }

        $loginHistory->forget($history);

        return redirect()->route('profile', ['section' => 'login-history'])->with('success', 'The selected device has been signed out.');
    }

    public function forgetOtherLoginDevices(Request $request, LoginHistoryService $loginHistory): RedirectResponse
    {
        $count = $loginHistory->forgetOthers($request->user(), $request->session()->getId());
        $message = $count > 0 ? $count.' other device(s) have been signed out.' : 'No other active devices were found.';

        return redirect()->route('profile', ['section' => 'login-history'])->with('success', $message);
    }

    public function password(): View
    {
        return view('auth.change-password');
    }

    public function updatePassword(ChangePasswordRequest $request): RedirectResponse
    {
        $request->user()->update(['password' => Hash::make($request->validated('password')), 'must_change_password' => false, 'updated_by' => $request->user()->id]);

        return redirect()->route('dashboard')->with('success', 'Password changed successfully.');
    }
}
