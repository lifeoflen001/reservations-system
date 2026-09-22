<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\ChangePasswordRequest;
use App\Http\Requests\Staff\ConfirmTwoFactorRequest;
use App\Http\Requests\Staff\DisableTwoFactorRequest;
use App\Http\Requests\Staff\EnableTwoFactorRequest;
use App\Http\Requests\Staff\RequestEmailChangeRequest;
use App\Http\Requests\Staff\UpdateAvatarRequest;
use App\Http\Requests\Staff\UpdateProfilePreferencesRequest;
use App\Http\Requests\Staff\UpdateProfileRequest;
use App\Http\Requests\Staff\VerifyEmailChangeRequest;
use App\Models\EmailChangeVerification;
use App\Models\Language;
use App\Models\LoginHistory;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Models\UserPreference;
use App\Services\HotelEmailService;
use App\Services\LoginHistoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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
            'pendingEmailChange' => EmailChangeVerification::query()
                ->where('user_id', $request->user()->id)
                ->where('expires_at', '>', now())
                ->first(),
        ]);
    }

    public function requestEmailChange(RequestEmailChangeRequest $request, HotelEmailService $emails): RedirectResponse
    {
        $user = $request->user();
        $email = $request->validated('email');
        $pending = EmailChangeVerification::query()->where('user_id', $user->id)->first();

        if (strcasecmp($email, (string) $user->email) === 0) {
            return back()->withErrors(['email' => 'Enter a different email address.'])->with('section', 'profile');
        }

        if ($pending?->email === $email && $pending->last_sent_at?->gt(now()->subSeconds(60))) {
            return back()->withErrors(['email' => 'Please wait a minute before requesting another verification code.'])->with('section', 'profile');
        }

        $code = (string) random_int(100000, 999999);
        $pending = EmailChangeVerification::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'email' => $email,
                'code_hash' => Hash::make($code),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(10),
                'last_sent_at' => now(),
            ],
        );

        try {
            $delivery = $emails->queue('email_change_verification', $email, [
                'user_name' => $user->display_name,
                'verification_code' => $code,
                'expires_in' => '10 minutes',
            ], sendImmediately: true);
        } catch (Throwable $exception) {
            report($exception);
            $pending->delete();

            return back()->withErrors(['email' => 'We could not deliver the verification code to that address. Check the address and try again.'])->with('section', 'profile');
        }

        if (! $delivery) {
            $pending->delete();

            return back()->withErrors(['email' => 'Email delivery is not configured. Ask an administrator to configure email, then try again.'])->with('section', 'profile');
        }

        return back()->with('success', 'A verification code was sent to '.$email.'. It expires in 10 minutes.')->with('section', 'profile');
    }

    public function verifyEmailChange(VerifyEmailChangeRequest $request): RedirectResponse
    {
        $user = $request->user();
        $code = $request->validated('code');
        $result = DB::transaction(function () use ($user, $code): string {
            $pending = EmailChangeVerification::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (! $pending || $pending->expires_at->isPast()) {
                $pending?->delete();

                return 'expired';
            }

            if ($pending->attempts >= 5) {
                return 'locked';
            }

            if (! Hash::check($code, $pending->code_hash)) {
                $pending->increment('attempts');

                return 'invalid';
            }

            $emailInUse = User::query()
                ->where('email', $pending->email)
                ->whereKeyNot($user->id)
                ->exists();

            if ($emailInUse) {
                $pending->delete();

                return 'unavailable';
            }

            $user->update([
                'email' => $pending->email,
                'email_verified_at' => now(),
                'updated_by' => $user->id,
            ]);
            $pending->delete();

            return 'verified';
        });

        return match ($result) {
            'verified' => back()->with('success', 'Your email address has been updated and verified.')->with('section', 'profile'),
            'invalid' => back()->withErrors(['code' => 'That verification code is incorrect.'])->with('section', 'profile'),
            'locked' => back()->withErrors(['code' => 'Too many incorrect attempts. Request a new verification code.'])->with('section', 'profile'),
            'unavailable' => back()->withErrors(['code' => 'That email address is no longer available. Request a new address.'])->with('section', 'profile'),
            default => back()->withErrors(['code' => 'That verification code has expired. Request a new code.'])->with('section', 'profile'),
        };
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $data = $request->validated();
        unset($data['avatar'], $data['remove_avatar']);
        $data['name'] = trim($data['first_name'].' '.$data['last_name']);
        $data['updated_by'] = $request->user()->id;
        $user = $request->user();
        $oldAvatarPath = $user->avatar_path;

        if ($request->hasFile('avatar')) {
            $data = array_merge($data, $this->avatarAttributes($request->file('avatar')));
        } elseif ($request->boolean('remove_avatar')) {
            $data = array_merge($data, $this->emptyAvatarAttributes());
        }

        $user->update($data);

        if ($oldAvatarPath && $oldAvatarPath !== $user->avatar_path) {
            Storage::disk('public')->delete($oldAvatarPath);
        }

        return back()->with('success', 'Profile updated.');
    }

    public function avatar(Request $request): Response|StreamedResponse
    {
        $user = $request->user();
        if ($user->avatar_data && $user->avatar_mime) {
            $contents = base64_decode($user->avatar_data, true);
            abort_unless($contents !== false, 404);

            return response($contents, 200, [
                'Cache-Control' => 'private, max-age=3600',
                'Content-Type' => $user->avatar_mime,
                'Content-Length' => (string) strlen($contents),
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        $disk = Storage::disk('public');
        $path = $user->avatar_path;
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

        if ($request->hasFile('avatar')) {
            $user->update(array_merge($this->avatarAttributes($request->file('avatar')), ['updated_by' => $user->id]));
        } elseif ($request->boolean('remove_avatar')) {
            $user->update(array_merge($this->emptyAvatarAttributes(), ['updated_by' => $user->id]));
        } else {
            return back()->with('warning', 'Choose a profile picture or select remove first.');
        }

        if ($oldAvatarPath && $oldAvatarPath !== $user->fresh()->avatar_path) {
            Storage::disk('public')->delete($oldAvatarPath);
        }

        return back()->with('success', $request->hasFile('avatar') ? 'Profile picture updated.' : 'Profile picture removed.');
    }

    /** @return array{avatar_path: null, avatar_data: string, avatar_mime: string} */
    private function avatarAttributes(UploadedFile $file): array
    {
        return [
            'avatar_path' => null,
            'avatar_data' => base64_encode($file->get()),
            'avatar_mime' => $file->getMimeType() ?: 'application/octet-stream',
        ];
    }

    /** @return array{avatar_path: null, avatar_data: null, avatar_mime: null} */
    private function emptyAvatarAttributes(): array
    {
        return [
            'avatar_path' => null,
            'avatar_data' => null,
            'avatar_mime' => null,
        ];
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

    public function updateTheme(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate(['theme' => ['required', 'string', 'in:light,dark']]);
        UserPreference::updateOrCreate(['user_id' => $request->user()->id], ['theme' => $data['theme']]);

        return response()->json(['theme' => $data['theme']]);
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
