<?php

namespace App\Http\Controllers;

use App\Services\OperationalDataTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class OperationalDataTransferController extends Controller
{
    public function export(Request $request, string $resource, string $format, OperationalDataTransferService $transfer)
    {
        abort_unless(in_array($format, ['csv', 'pdf'], true), 404);
        abort_unless($transfer->canExport($request->user(), $resource), 403);

        if ($format === 'csv') return $transfer->csv($resource, $request->user());

        $data = $transfer->export($resource, $request->user());
        $html = view('exports.operational-table', $data)->render();
        $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', count($data['headers']) > 8 ? 'landscape' : 'portrait');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$data['filename'].'pdf"',
        ]);
    }

    public function template(Request $request, string $resource, OperationalDataTransferService $transfer)
    {
        abort_unless($transfer->canImport($request->user(), $resource), 403);
        return $transfer->csv($resource, $request->user(), true);
    }

    public function import(Request $request, string $resource, OperationalDataTransferService $transfer): RedirectResponse
    {
        abort_unless($transfer->canImport($request->user(), $resource), 403);
        $request->validate(['file' => ['required', 'file', 'max:10240', 'mimes:csv,txt']]);

        try {
            $count = $transfer->import($resource, $request->file('file'), $request->user());
        } catch (ValidationException $exception) {
            throw $exception;
        }

        return back()->with('success', $count.' '.str($transfer->definition($resource)['label'])->lower().' imported successfully.');
    }
}
