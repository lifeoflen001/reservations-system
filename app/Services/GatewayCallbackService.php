<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\GatewayTransaction;
use App\Models\IntegrationSetting;
use App\Models\Payment;
use App\Models\Reservation;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GatewayCallbackService
{
    public function __construct(private readonly WebhookSignatureService $signatures, private readonly PaymentService $payments) {}

    public function handle(Request $request, string $provider): GatewayTransaction
    {
        $context = app(TenantContext::class);
        $context->release();
        $raw = $request->getContent();
        $signature = (string) $request->header('X-Gateway-Signature');
        $timestamp = (string) $request->header('X-Gateway-Timestamp');
        $matches = IntegrationSetting::query()
            ->withoutGlobalScope('tenant-ownership')
            ->where('key', 'gateway:'.$provider)
            ->where('is_enabled', true)
            ->get()
            ->filter(function (IntegrationSetting $integration) use ($raw, $signature, $timestamp): bool {
                $secret = $integration->secrets['signing_secret'] ?? null;

                return is_string($secret) && $secret !== '' && $this->signatures->verify($raw, $secret, $signature, $timestamp);
            });

        if ($matches->count() !== 1 || ! $matches->first()->organization_id || ! $matches->first()->property_id) {
            abort(401, 'Invalid gateway signature.');
        }

        $setting = $matches->first();
        $context->activate((int) $setting->organization_id, (int) $setting->property_id);

        try {
            $data = $request->json()->all();
            abort_unless(is_array($data), 422, 'A JSON gateway payload is required.');
            $externalId = (string) ($data['id'] ?? $data['transaction_id'] ?? '');
            abort_unless($externalId !== '' && strlen($externalId) <= 255, 422, 'Gateway transaction id is required.');

            $reservationId = isset($data['reservation_id']) ? (int) $data['reservation_id'] : null;
            $reservation = $reservationId ? Reservation::query()->find($reservationId) : null;
            abort_if($reservationId && ! $reservation, 422, 'The gateway reservation is not part of the signed property context.');

            $amount = $data['amount'] ?? 0;
            abort_unless(is_numeric($amount) && (float) $amount >= 0, 422, 'Gateway amount is invalid.');
            $currency = strtoupper((string) ($data['currency'] ?? app(PropertySettingsService::class)->currency()->code));
            abort_unless((bool) preg_match('/^[A-Z]{3}$/', $currency), 422, 'Gateway currency is invalid.');
            $status = (string) ($data['status'] ?? 'succeeded');
            $reference = isset($data['reference']) ? (string) $data['reference'] : null;
            abort_unless($reference === null || strlen($reference) <= 100, 422, 'Gateway reference is too long.');

            return DB::transaction(function () use ($provider, $externalId, $reservation, $amount, $currency, $status, $reference, $context): GatewayTransaction {
                $transaction = GatewayTransaction::query()->firstOrCreate(
                    ['property_id' => $context->propertyId(), 'provider' => $provider, 'external_transaction_id' => $externalId],
                    ['reservation_id' => $reservation?->getKey(), 'amount' => $amount, 'currency' => $currency, 'status' => $status, 'reference' => $reference, 'received_at' => now()],
                );

                if (! $transaction->wasRecentlyCreated && (
                    (int) $transaction->reservation_id !== (int) ($reservation?->getKey() ?? 0)
                    || round((float) $transaction->amount, 2) !== round((float) $amount, 2)
                )) {
                    abort(409, 'Gateway transaction data does not match the recorded event.');
                }

                if ($transaction->wasRecentlyCreated && in_array($transaction->status, ['succeeded', 'paid'], true) && $transaction->reservation_id && (float) $transaction->amount > 0) {
                    $this->payments->post($reservation, ['amount' => $transaction->amount, 'method' => 'gateway:'.$provider, 'reference' => $externalId, 'status' => PaymentStatus::Paid->value], null);
                    $transaction->update(['status' => 'posted', 'confirmed_at' => now(), 'payment_id' => Payment::query()->where('reference', $externalId)->latest('id')->value('id')]);
                }

                return $transaction->fresh();
            });
        } finally {
            $context->release();
        }
    }
}
