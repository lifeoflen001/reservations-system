@extends('layouts.app')
@section('content')
<x-page-header title="Finance" subtitle="Real-time money position, movements and outstanding guest balances."><a class="ui-button ui-button--secondary" href="{{ route('payments.index') }}"><x-ui.icon name="card" size="16" /> Guest payments</a></x-page-header>
@include('finance.partials.nav')
@php($available = $accounts->sum('current_balance'))
<x-kpi-grid :items="[
 ['label' => 'Available funds', 'value' => $formatter->format($available), 'icon' => 'currency', 'tone' => 'success', 'context' => 'All active accounts', 'href' => route('finance.accounts')],
 ['label' => 'Money in today', 'value' => $formatter->format($moneyIn), 'icon' => 'arrow-right', 'tone' => 'info', 'context' => 'Posted credits', 'href' => route('finance.transactions', ['direction' => 'credit', 'date' => 'today'])],
 ['label' => 'Money out today', 'value' => $formatter->format($moneyOut), 'icon' => 'arrow-left', 'tone' => 'danger', 'context' => 'Posted debits', 'href' => route('finance.transactions', ['direction' => 'debit', 'date' => 'today'])],
 ['label' => 'Net today', 'value' => $formatter->format($net), 'icon' => 'chart', 'tone' => 'warning', 'context' => 'In less out'],
 ['label' => 'Outstanding guest balances', 'value' => $formatter->format($outstanding), 'icon' => 'alert', 'tone' => 'danger', 'context' => 'Live reservation balances', 'href' => route('reservations.index', ['balance' => 'outstanding'])]
]" />
<div class="dashboard-grid">
    <x-ui.card title="Where the money is" icon="building"><x-data.table caption="Financial account balances"><thead><tr><th>Account</th><th>Type</th><th>Balance</th><th>Currency</th></tr></thead><tbody>@forelse($accounts as $account)<tr><td><strong>{{ $account->name }}</strong>@if($account->masked_account_number)<small class="table-muted">{{ $account->masked_account_number }}</small>@endif</td><td>{{ str_replace('_', ' ', ucfirst($account->type)) }}</td><td><strong>{{ $formatter->format($account->current_balance) }}</strong></td><td>{{ $account->currency }}</td></tr>@empty<tr><td colspan="4">No financial accounts configured.</td></tr>@endforelse</tbody></x-data.table></x-ui.card>
    <x-ui.card title="Recent ledger activity" icon="document"><x-data.table caption="Recent financial transactions"><thead><tr><th>Transaction</th><th>Description</th><th>Movement</th></tr></thead><tbody>@forelse($recent as $transaction)<tr><td><strong>{{ $transaction->transaction_number }}</strong><small class="table-muted">{{ $transaction->transaction_date?->format('m/d/Y H:i') }}</small></td><td>{{ $transaction->description }}</td><td class="{{ $transaction->direction === 'credit' ? 'text-success' : 'text-danger' }}">{{ $transaction->direction === 'credit' ? '+' : '-' }}{{ $formatter->format($transaction->amount) }}</td></tr>@empty<tr><td colspan="3">No posted finance transactions yet.</td></tr>@endforelse</tbody></x-data.table></x-ui.card>
</div>
@endsection
