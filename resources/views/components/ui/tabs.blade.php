@props(['tabs' => []])

<nav class="ui-tabs" role="tablist">
    @foreach ($tabs as $tab)
        <a href="{{ $tab['href'] ?? '#' }}" class="ui-tab {{ ($tab['active'] ?? false) ? 'is-active' : '' }}" role="tab">{{ $tab['label'] }}</a>
    @endforeach
</nav>
