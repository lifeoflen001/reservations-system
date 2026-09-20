<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\GatewayCallbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentGatewayWebhookController extends Controller
{
    public function __invoke(Request $request, string $provider, GatewayCallbackService $callbacks): JsonResponse
    {
        $transaction = $callbacks->handle($request, $provider);

        return response()->json(['accepted' => true, 'transaction_id' => $transaction->id]);
    }
}
