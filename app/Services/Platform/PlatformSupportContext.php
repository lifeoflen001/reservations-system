<?php

namespace App\Services\Platform;

use App\Models\PlatformAdministrator;
use App\Models\PlatformSupportSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class PlatformSupportContext
{
    private ?PlatformSupportSession $session = null;

    public function resolve(Request $request): PlatformSupportSession
    {
        $administrator = Auth::guard('platform')->user();
        abort_unless($administrator instanceof PlatformAdministrator && $administrator->isActive() && $administrator->hasPlatformPermission('platform.support.start'), 403);

        $routeSession = $request->route('platformSupportSession');
        abort_unless($routeSession instanceof PlatformSupportSession, 404);
        abort_unless((int) $routeSession->platform_administrator_id === (int) $administrator->getKey(), 404);

        if (! $routeSession->isActive()) {
            $this->expire($routeSession, $administrator, $request);
            throw new HttpException(410, 'This support session has expired or ended.');
        }

        $routeSession->loadMissing(['organization', 'property']);
        abort_unless($routeSession->organization?->status === 'active', 403);
        if ($routeSession->property) {
            abort_unless((int) $routeSession->property->organization_id === (int) $routeSession->organization_id && $routeSession->property->status === 'active', 403);
        }

        $requestSessionId = $request->session()->get(SupportAccessService::SESSION_KEY);
        abort_unless((int) $requestSessionId === (int) $routeSession->getKey(), 403, 'Enter the active support session before opening its workspace.');

        $routeSession->forceFill([
            'entered_at' => $routeSession->entered_at ?: now(),
            'last_activity_at' => now(),
        ])->save();
        $this->session = $routeSession;

        app(PlatformAuditService::class)->record($administrator, 'support.workspace_entered', $routeSession, [], $routeSession->organization_id, $routeSession->property_id, $request);

        return $routeSession;
    }

    public function current(): PlatformSupportSession
    {
        abort_unless($this->session instanceof PlatformSupportSession, 500, 'Support context has not been resolved.');

        return $this->session;
    }

    public function organizationId(): int
    {
        return (int) $this->current()->organization_id;
    }

    public function propertyId(): ?int
    {
        return $this->current()->property_id ? (int) $this->current()->property_id : null;
    }

    public function isOrganizationScope(): bool
    {
        return $this->propertyId() === null;
    }

    private function expire(PlatformSupportSession $session, PlatformAdministrator $administrator, Request $request): void
    {
        if ($session->status === 'active') {
            $session->forceFill(['status' => 'expired', 'ended_at' => now()])->save();
            app(PlatformAuditService::class)->record($administrator, 'support.session_expired', $session, [], $session->organization_id, $session->property_id, $request);
        }
        $request->session()->forget(SupportAccessService::SESSION_KEY);
    }
}
