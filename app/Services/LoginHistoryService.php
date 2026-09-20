<?php

namespace App\Services;

use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class LoginHistoryService
{
    public function record(User $user, Request $request): LoginHistory
    {
        $now = now();
        $sessionId = $request->hasSession() ? $request->session()->getId() : null;
        $userAgent = (string) $request->userAgent();

        return LoginHistory::create([
            'user_id' => $user->id,
            'session_id' => $sessionId,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit($userAgent, 1000, ''),
            'device_name' => $this->browser($userAgent).' on '.$this->platform($userAgent),
            'browser' => $this->browser($userAgent),
            'platform' => $this->platform($userAgent),
            'device_type' => $this->deviceType($userAgent),
            'location' => $this->location($request->ip()),
            'login_at' => $now,
            'last_seen_at' => $now,
        ]);
    }

    public function touch(Request $request): void
    {
        $user = $request->user();
        if (! $user || ! $request->hasSession()) {
            return;
        }

        LoginHistory::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('session_id', $request->session()->getId())
            ->whereNull('logged_out_at')
            ->update(['last_seen_at' => now()]);
    }

    public function isRevoked(Request $request): bool
    {
        $user = $request->user();
        if (! $user || ! $request->hasSession()) {
            return false;
        }

        $history = LoginHistory::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('session_id', $request->session()->getId())
            ->latest('id')
            ->first(['logged_out_at']);

        return $history?->logged_out_at !== null;
    }

    public function logoutCurrent(Request $request): void
    {
        $user = $request->user();
        if (! $user || ! $request->hasSession()) {
            return;
        }

        $this->markLoggedOut($user->getAuthIdentifier(), $request->session()->getId());
    }

    public function forget(LoginHistory $history): void
    {
        $this->markLoggedOut($history->user_id, $history->session_id);
        $this->removeStoredSession($history->session_id);
    }

    public function forgetOthers(User $user, string $currentSessionId): int
    {
        $histories = LoginHistory::query()
            ->where('user_id', $user->id)
            ->whereNull('logged_out_at')
            ->where(function ($query) use ($currentSessionId): void {
                $query->whereNull('session_id')->orWhere('session_id', '!=', $currentSessionId);
            })
            ->get(['id', 'session_id']);

        if ($histories->isEmpty()) {
            return 0;
        }

        LoginHistory::query()->whereKey($histories->pluck('id'))->update([
            'logged_out_at' => now(),
            'last_seen_at' => now(),
        ]);
        $this->removeStoredSessions($histories->pluck('session_id')->filter()->all());

        return $histories->count();
    }

    private function markLoggedOut(int|string $userId, ?string $sessionId): void
    {
        if (! $sessionId) {
            return;
        }

        LoginHistory::query()
            ->where('user_id', $userId)
            ->where('session_id', $sessionId)
            ->whereNull('logged_out_at')
            ->update(['logged_out_at' => now(), 'last_seen_at' => now()]);
    }

    private function removeStoredSession(?string $sessionId): void
    {
        if (! $sessionId) {
            return;
        }

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('id', $sessionId)->delete();

            return;
        }

        $this->destroyHandlerSession($sessionId);
    }

    private function removeStoredSessions(array $sessionIds): void
    {
        foreach ($sessionIds as $sessionId) {
            $this->removeStoredSession($sessionId);
        }
    }

    private function destroyHandlerSession(string $sessionId): void
    {
        if (config('session.driver') === 'array') {
            return;
        }

        try {
            app('session')->driver()->getHandler()->destroy($sessionId);
        } catch (Throwable) {
            // The database-backed revocation record remains authoritative if a handler cannot destroy a session.
        }
    }

    private function browser(string $userAgent): string
    {
        return match (true) {
            Str::contains($userAgent, 'Edg/') => 'Edge',
            Str::contains($userAgent, 'OPR/') => 'Opera',
            Str::contains($userAgent, 'Chrome/') => 'Chrome',
            Str::contains($userAgent, 'Firefox/') => 'Firefox',
            Str::contains($userAgent, 'Safari/') => 'Safari',
            default => 'Unknown browser',
        };
    }

    private function platform(string $userAgent): string
    {
        return match (true) {
            Str::contains($userAgent, ['iPad', 'iPhone', 'iPod']) => 'iOS',
            Str::contains($userAgent, 'Android') => 'Android',
            Str::contains($userAgent, 'Windows') => 'Windows',
            Str::contains($userAgent, ['Macintosh', 'Mac OS']) => 'macOS',
            Str::contains($userAgent, 'Linux') => 'Linux',
            default => 'Unknown platform',
        };
    }

    private function deviceType(string $userAgent): string
    {
        return match (true) {
            Str::contains($userAgent, 'iPad') => 'Tablet',
            Str::contains($userAgent, ['Mobile', 'iPhone', 'Android']) => 'Mobile',
            default => 'Desktop',
        };
    }

    private function location(?string $ip): string
    {
        if (! $ip) {
            return 'Unknown location';
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
            ? 'Local network'
            : 'Unknown location';
    }
}
