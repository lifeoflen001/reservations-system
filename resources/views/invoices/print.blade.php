@extends('layouts.print')
@section('content')
    <main class="print-page">@include('invoices.partials.document')</main>
    <script>window.addEventListener('load', () => window.print());</script>
@endsection
