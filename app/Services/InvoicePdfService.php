<?php

namespace App\Services;

use App\Models\Invoice;
use App\Support\CurrencyFormatter;
use Dompdf\Dompdf;

class InvoicePdfService
{
    public function render(Invoice $invoice, CurrencyFormatter $formatter): string
    {
        $payment = $invoice->payment;
        $reservation = $payment->reservation;
        $property = app(PropertySettingsService::class)->current();
        if (class_exists(Dompdf::class)) {
            $dompdf = new Dompdf(['isRemoteEnabled' => false]);
            $dompdf->loadHtml(view('invoices.partials.document', compact('invoice', 'property', 'formatter'))->render());
            $dompdf->setPaper('A4');
            $dompdf->render();

            return $dompdf->output();
        }
        $lines = [
            $property?->name ?? 'HotelDesk',
            'INVOICE '.$invoice->invoice_number,
            'Issue date: '.$invoice->issue_date?->format('m/d/Y'),
            'Status: '.$payment->status->label().'    Method: '.ucwords(str_replace('_', ' ', $payment->method)),
            'Customer: '.$payment->client->full_name,
            'Email: '.($payment->client->email ?: '—'),
            'Reservation: '.$reservation->code.' | Room '.$reservation->room?->room_number.' - '.$reservation->room?->roomType?->name,
            'Stay: '.$reservation->check_in?->format('m/d/Y').' - '.$reservation->check_out?->format('m/d/Y'),
            'Accommodation payment',
            'Quantity: 1    Total: '.$formatter->format($payment->amount),
            'Subtotal: '.$formatter->format($payment->amount),
            'TOTAL: '.$formatter->format($payment->amount),
            'Reference: '.$payment->reference,
        ];

        $content = "BT\n/F1 11 Tf\n";
        $y = 760;
        foreach ($lines as $line) {
            $content .= sprintf("1 0 0 1 50 %d Tm (%s) Tj\n", $y, $this->escape($line));
            $y -= 28;
        }
        $content .= 'ET';

        $objects = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n",
            "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
            "5 0 obj\n<< /Length ".strlen($content)." >>\nstream\n".$content."\nendstream\nendobj\n",
        ];

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";

        return $pdf;
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    }
}
