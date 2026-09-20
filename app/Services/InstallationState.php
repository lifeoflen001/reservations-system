<?php

namespace App\Services;

use App\Models\Installation;

class InstallationState
{
    public function current(): ?Installation
    {
        return Installation::query()->first();
    }

    public function isComplete(): bool
    {
        return $this->current()?->isComplete() ?? false;
    }

    public function markComplete(array $values): Installation
    {
        $installation = $this->current() ?? new Installation;
        $installation->fill($values + ['status' => 'complete', 'completed_at' => now()]);
        $installation->status = 'complete';
        $installation->completed_at ??= now();
        $installation->save();

        return $installation;
    }
}
