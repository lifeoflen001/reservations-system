<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\GatewayTransaction;
use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GatewayCallbackService
{
    public function __construct(private readonly WebhookSignatureService $signatures, private readonly PaymentService $payments) {}

    public function handle(Request $request, string $provider): GatewayTransaction
    {
        $setting = app(IntegrationSettingsService::class)->get('gateway:'.$provider);
        $secret = $setting?->secrets['signing_secret'] ?? null;
        abort_unless($secret && $this->signatures->verify($request->getContent(), $secret, (string) $request->header('X-Gateway-Signature'), (string) $request->header('X-Gateway-Timestamp')), 401, 'Invalid gateway signature.');
        $data = $request->json()->all();
        $externalId = (string) ($data['id'] ?? $data['transaction_id'] ?? '');
        abort_unless($externalId !== '', 422, 'Gateway transaction id is required.');

        return DB::transaction(function () use ($data, $provider, $externalId): GatewayTransaction {
            $transaction = GatewayTransaction::firstOrCreate(['provider' => $provider, 'external_transaction_id' => $externalId], ['reservation_id' => $data['reservation_id'] ?? null, 'amount' => $data['amount'] ?? 0, 'currency' => $data['currency'] ?? app(PropertySettingsService::class)->currency()->code, 'status' => $data['status'] ?? 'succeeded', 'reference' => $data['reference'] ?? null, 'received_at' => now()]);
            if ($transaction->wasRecentlyCreated && ($transaction->status === 'succeeded' || $transaction->status === 'paid') && $transaction->reservation_id) {
                $reservation = Reservation::find($transaction->reservation_id);
                if ($reservation && (float) $transaction->amount > 0) {
                    $this->payments->post($reservation, ['amount' => $transaction->amount, 'method' => 'gateway:'.$provider, 'reference' => $externalId, 'status' => PaymentStatus::Paid->value], null);
                    $transaction->update(['status' => 'posted', 'confirmed_at' => now(), 'payment_id' => Payment::query()->where('reference', $externalId)->latest('id')->value('id')]);
                }
            }

            return $transaction->fresh();
        });
    }
}
