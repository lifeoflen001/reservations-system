<nav class="ui-tabs page-tabs" aria-label="Finance sections">
    <a class="ui-tab {{ request()->routeIs('finance.overview') ? 'is-active' : '' }}" href="{{ route('finance.overview') }}">Overview</a>
    <a class="ui-tab {{ request()->routeIs('finance.transactions') ? 'is-active' : '' }}" href="{{ route('finance.transactions') }}">Transactions</a>
    <a class="ui-tab {{ request()->routeIs('finance.expenses*') ? 'is-active' : '' }}" href="{{ route('finance.expenses') }}">Expenses</a>
    <a class="ui-tab {{ request()->routeIs('finance.accounts*') ? 'is-active' : '' }}" href="{{ route('finance.accounts') }}">Accounts</a>
    <a class="ui-tab {{ request()->routeIs('finance.transfers*') ? 'is-active' : '' }}" href="{{ route('finance.transfers') }}">Transfers</a>
    <a class="ui-tab {{ request()->routeIs('finance.reports') ? 'is-active' : '' }}" href="{{ route('finance.reports') }}">Reports</a>
    <a class="ui-tab {{ request()->routeIs('finance.petty-cash*') ? 'is-active' : '' }}" href="{{ route('finance.petty-cash') }}">Petty cash</a>
    <a class="ui-tab {{ request()->routeIs('finance.reconciliation*') ? 'is-active' : '' }}" href="{{ route('finance.reconciliation') }}">Reconciliation</a>
</nav>
