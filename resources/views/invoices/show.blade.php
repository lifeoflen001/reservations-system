@extends('layouts.app')
@section('content')
    <x-page-header :title="'Invoice '.$invoice->invoice_number" subtitle="Payment receipt and reservation charge.">
        @can('print', $invoice)<a class="ui-button ui-button--secondary" href="{{ route('invoices.print', $invoice) }}" target="_blank"><x-ui.icon name="print" size="16" /> Print</a>@endcan
        @can('download', $invoice)<a class="ui-button ui-button--primary" href="{{ route('invoices.download', $invoice) }}"><x-ui.icon name="download" size="16" /> Download PDF</a>@endcan
    </x-page-header>
    <section class="ui-card invoice-page-card"><div class="ui-card__body"><a class="text-link" href="{{ route('payments.index') }}">← Back to payments</a><div class="invoice-page-document">@include('invoices.partials.document')</div></div></section>
@endsection
