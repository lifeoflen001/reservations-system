<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Support\Facades\DB;

class ClientService
{
    public function create(array $data, ?int $actorId = null): Client
    {
        return DB::transaction(fn () => Client::create([...$data, 'created_by' => $actorId, 'updated_by' => $actorId, 'is_active' => $data['is_active'] ?? true]));
    }

    public function update(Client $client, array $data, ?int $actorId = null): Client
    {
        return DB::transaction(function () use ($client, $data, $actorId) { $client = Client::query()->lockForUpdate()->findOrFail($client->id); $client->update([...$data, 'updated_by' => $actorId]); return $client->refresh(); });
    }

    public function archive(Client $client, ?int $actorId = null): Client
    {
        return DB::transaction(function () use ($client, $actorId) { $client = Client::query()->lockForUpdate()->findOrFail($client->id); $client->update(['is_active' => false, 'updated_by' => $actorId]); return $client->refresh(); });
    }

    public function potentialDuplicates(array $data, ?int $ignoreId = null)
    {
        $email = strtolower(trim((string) ($data['email'] ?? ''))); $phone = preg_replace('/\D+/', '', (string) ($data['phone'] ?? '')); $document = strtolower(trim((string) ($data['document_number'] ?? '')));
        if ($email === '' && $phone === '' && $document === '') return collect();
        return Client::query()->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->where(function ($q) use ($email, $phone, $document) {
            if ($email !== '') $q->orWhereRaw('LOWER(email) = ?', [$email]);
            if ($phone !== '') $q->orWhereRaw("REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', '') = ?", [$phone]);
            if ($document !== '') $q->orWhereRaw('LOWER(document_number) = ?', [$document]);
        })->limit(5)->get();
    }
}
