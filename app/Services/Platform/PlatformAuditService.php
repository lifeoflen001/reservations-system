<?php

namespace App\Services\Platform;

use App\Models\PlatformAdministrator;
use App\Models\PlatformAuditLog;
use App\Models\PlatformSupportSession;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class PlatformAuditService
{
    public function record(
        ?PlatformAdministrator $administrator,
        string $action,
        object|int|string|null $target = null,
        array $metadata = [],
        ?int $organizationId = null,
        ?int $propertyId = null,
        ?Request $request = null,
    ): PlatformAuditLog {
        $request ??= app()->bound('request') ? app(Request::class) : null;
        $targetType = is_object($target) ? $target::class : null;
        $targetId = is_object($target) ? (string) $target->getKey() : ($target === null ? null : (string) $target);

        return PlatformAuditLog::create([
            'uuid' => (string) Str::uuid(),
            'platform_administrator_id' => $administrator?->getKey(),
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'organization_id' => $organizationId,
            'property_id' => $propertyId,
            'metadata' => $this->safeMetadata($metadata),
            'ip_address' => $request?->ip(),
        ]);
    }

    private function safeMetadata(array $metadata): ?array
    {
        $blocked = ['password', 'password_confirmation', 'token', 'secret', 'api_key', 'authorization', 'cookie'];
        $filtered = collect($metadata)->reject(fn ($value, $key): bool => in_array(strtolower((string) $key), $blocked, true))->all();

        return $filtered === [] ? null : $filtered;
    }
}
