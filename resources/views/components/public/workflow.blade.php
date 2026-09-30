@php
    $stages = [
        ['icon' => 'calendar', 'title' => 'Reservation', 'description' => 'Create and manage the stay.'],
        ['icon' => 'users', 'title' => 'Guest', 'description' => 'Maintain the guest record.'],
        ['icon' => 'bed', 'title' => 'Room', 'description' => 'Assign and manage accommodation.'],
        ['icon' => 'wrench', 'title' => 'Operations', 'description' => 'Coordinate rooms, tasks and teams.'],
        ['icon' => 'card', 'title' => 'POS', 'description' => 'Add purchases or room charges.'],
        ['icon' => 'currency', 'title' => 'Payment', 'description' => 'Record the settlement.'],
        ['icon' => 'document', 'title' => 'Finance', 'description' => 'Track accounts and reconciliation.'],
        ['icon' => 'chart', 'title' => 'Reporting', 'description' => 'Review hotel activity.'],
    ];
@endphp

<ol class="public-workflow" aria-label="Connected hotel workflow">
    @foreach($stages as $stage)
        <li class="public-workflow__stage">
            <span class="public-workflow__icon"><x-ui.icon :name="$stage['icon']" size="19" /></span>
            <span class="public-workflow__copy">
                <strong>{{ $stage['title'] }}</strong>
                <small>{{ $stage['description'] }}</small>
            </span>
        </li>
    @endforeach
</ol>
