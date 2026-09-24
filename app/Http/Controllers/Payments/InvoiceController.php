<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\InvoicePdfService;
use App\Services\PropertySettingsService;
use App\Support\CurrencyFormatter;
use Illuminate\Support\Facades\Gate;

class InvoiceController extends Controller
{
    public function show(Invoice $invoice, CurrencyFormatter $formatter, PropertySettingsService $propertySettings)
    {
        Gate::authorize('view', $invoice);
        $invoice->load(['payment.client', 'payment.reservation.room.roomType', 'payment.reservation.source', 'payment.reservation.posRoomCharges.order.outlet', 'payment.reservation.posRoomCharges.order.items', 'payment.creator']);
        return view('invoices.show', compact('invoice', 'formatter') + ['property' => $propertySettings->current()]);
    }

    public function print(Invoice $invoice, CurrencyFormatter $formatter, PropertySettingsService $propertySettings)
    {
        Gate::authorize('print', $invoice);
        $invoice->load(['payment.client', 'payment.reservation.room.roomType', 'payment.reservation.source', 'payment.reservation.posRoomCharges.order.outlet', 'payment.reservation.posRoomCharges.order.items', 'payment.creator']);
        return view('invoices.print', compact('invoice', 'formatter') + ['property' => $propertySettings->current()]);
    }

    public function download(Invoice $invoice, CurrencyFormatter $formatter, InvoicePdfService $pdf)
    {
        Gate::authorize('download', $invoice);
        $invoice->load(['payment.client', 'payment.reservation.room.roomType', 'payment.reservation.source', 'payment.reservation.posRoomCharges.order.outlet', 'payment.reservation.posRoomCharges.order.items', 'payment.creator']);
        return response($pdf->render($invoice, $formatter), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$invoice->invoice_number.'.pdf"',
        ]);
    }
}
