<?php

namespace App\Http\Controllers\Clients;

use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clients\StoreClientRequest;
use App\Models\Client;
use App\Models\Reservation;
use App\Services\ClientService;
use App\Services\MiniDashboardMetricsService;
use App\Support\CurrencyFormatter;
use App\Support\TablePagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use LogicException;

class ClientController extends Controller
{
    public function __construct(private readonly ClientService $clients) {}

    public function index(Request $request, CurrencyFormatter $formatter, MiniDashboardMetricsService $metricsService)
    {
        Gate::authorize('viewAny', Client::class);
        $eligible = [ReservationStatus::CheckedIn->value, ReservationStatus::CheckedOut->value];
        $scope = (string) $request->string('scope', 'all');
        $now = now();
        $query = Client::query()->withCount(['reservations as stay_count' => fn (Builder $q) => $q->whereIn('status', $eligible)])->withSum(['payments as total_spent' => fn (Builder $q) => $q->successful()], 'amount')
            ->when($request->filled('search'), function (Builder $q) use ($request) { $search = '%'.(string) $request->string('search').'%'; $q->where(fn ($q) => $q->where('first_name', 'like', $search)->orWhere('middle_name', 'like', $search)->orWhere('last_name', 'like', $search)->orWhere('email', 'like', $search)->orWhere('phone', 'like', $search)->orWhere('country', 'like', $search)->orWhere('city', 'like', $search)->when(auth()->user()->hasPermission('clients.view_sensitive'), fn ($q) => $q->orWhere('document_number', 'like', $search))); })
            ->when($request->filled('status') && (string) $request->string('status') !== 'all', fn (Builder $q) => $q->where('is_active', $request->string('status') === 'active'));
        $query->when($scope === 'in_house', fn (Builder $q) => $q->whereHas('reservations', fn (Builder $reservation) => $reservation->where('status', ReservationStatus::CheckedIn->value)))
            ->when($scope === 'upcoming', fn (Builder $q) => $q->whereHas('reservations', fn (Builder $reservation) => $reservation->whereIn('status', [ReservationStatus::Pending->value, ReservationStatus::Confirmed->value])->where('check_in', '>', $now)))
            ->when($scope === 'returning', fn (Builder $q) => $q->whereIn('id', Reservation::query()->select('client_id')->whereNotNull('client_id')->whereIn('status', $eligible)->groupBy('client_id')->havingRaw('COUNT(*) > 1')));
        $sorts = ['name' => ['last_name', 'asc'], 'stays' => ['stay_count', 'desc'], 'spent' => ['total_spent', 'desc'], 'created' => ['created_at', 'desc']];
        $sort = $sorts[(string) $request->get('sort', 'created')] ?? $sorts['created'];
        if ($sort[0] === 'last_name') $query->orderBy('last_name', 'asc')->orderBy('first_name', 'asc'); else $query->orderBy($sort[0], $sort[1]);
        $query->orderBy('clients.id');
        $clients = $query->paginate(TablePagination::perPage($request, 15))->withQueryString();
        $openClient = $request->filled('client') ? Client::with(['reservations.room.roomType', 'reservations.source', 'reservations.payments', 'payments'])->withSum(['payments as total_spent' => fn (Builder $q) => $q->successful()], 'amount')->find($request->integer('client')) : null;
        $editClient = $request->filled('edit') ? Client::find($request->integer('edit')) : null;
        $duplicateMatches = session('duplicate_matches') ? Client::whereIn('id', session('duplicate_matches'))->get() : collect();
        return view('clients.index', compact('clients', 'openClient', 'editClient', 'duplicateMatches', 'formatter') + ['openNew' => $request->boolean('new'), 'kpis' => $metricsService->clients()]);
    }

    public function create() { Gate::authorize('create', Client::class); return redirect()->route('clients.index', ['new' => 1]); }
    public function show(Client $client) { Gate::authorize('view', $client); return redirect()->route('clients.index', ['client' => $client->id]); }
    public function edit(Client $client) { Gate::authorize('update', $client); return redirect()->route('clients.index', ['edit' => $client->id]); }

    public function store(StoreClientRequest $request)
    {
        Gate::authorize('create', Client::class); $data = $this->validatedForActor($request->validated()); $matches = $this->clients->potentialDuplicates($data);
        if ($matches->isNotEmpty() && ! $request->boolean('force')) return redirect()->route('clients.index', ['new' => 1])->withInput()->with('duplicate_matches', $matches->pluck('id')->all())->with('warning', 'An existing client may already match these details. Review the matches before continuing.');
        $client = $this->clients->create($data, $request->user()->id);
        return redirect()->route('clients.index', ['client' => $client->id])->with('success', 'Client created successfully.');
    }

    public function update(StoreClientRequest $request, Client $client)
    {
        Gate::authorize('update', $client); try { $client = $this->clients->update($client, $this->validatedForActor($request->validated(), $client), $request->user()->id); } catch (LogicException $e) { return back()->withInput()->with('error', $e->getMessage()); }
        return redirect()->route('clients.index', ['client' => $client->id])->with('success', 'Client updated successfully.');
    }

    public function destroy(Request $request, Client $client)
    {
        Gate::authorize('delete', $client); $this->clients->archive($client, $request->user()->id);
        return redirect()->route('clients.index')->with('success', 'Client archived. Historical reservations and payments were preserved.');
    }

    private function validatedForActor(array $data, ?Client $client = null): array
    {
        if (! auth()->user()->hasPermission('clients.view_sensitive') && ! auth()->user()->hasPermission('clients.manage')) $data = array_diff_key($data, array_flip(['document_type', 'document_number', 'document_expiry']));
        return $data;
    }
}
