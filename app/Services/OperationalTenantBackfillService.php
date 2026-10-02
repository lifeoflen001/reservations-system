<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class OperationalTenantBackfillService
{
    /**
     * Add ownership to legacy operational rows without rewriting existing
     * relationships. Ambiguous relationships are reported and left alone.
     *
     * @return array<string, mixed>
     */
    public function run(bool $dryRun = false): array
    {
        $property = DB::table('properties')
            ->where('status', 'active')
            ->whereNotNull('organization_id')
            ->orderBy('id')
            ->first();

        $report = [
            'dry_run' => $dryRun,
            'organization_id' => (int) ($property?->organization_id ?? 0),
            'default_property_id' => (int) ($property?->id ?? 0),
            'tables' => [],
            'anomalies' => [],
            'financial_totals' => $this->financialTotals(),
        ];

        if (! $property) {
            $report['anomalies'][] = ['table' => 'properties', 'record_id' => null, 'reason' => 'No active organization-backed property is available.'];

            return $report;
        }

        $work = function () use (&$report, $dryRun, $property): void {
            $organizationId = (int) $property->organization_id;
            $propertyId = (int) $property->id;

            $this->backfillOrganization('clients', $organizationId, $report, $dryRun);
            $this->backfillProperty('departments', $propertyId, $report, $dryRun);
            $this->backfillProperty('floors', $propertyId, $report, $dryRun);
            $this->backfillProperty('room_categories', $propertyId, $report, $dryRun);
            $this->backfillProperty('room_types', $propertyId, $report, $dryRun);
            $this->backfillProperty('amenities', $propertyId, $report, $dryRun);

            $this->backfillProperty('rooms', $propertyId, $report, $dryRun, function (object $row): ?int {
                return $this->singlePropertyFrom([
                    ['table' => 'floors', 'id' => $row->floor_id ?? null],
                    ['table' => 'room_categories', 'id' => $row->room_category_id ?? null],
                    ['table' => 'room_types', 'id' => $row->room_type_id ?? null],
                ]);
            });
            $this->backfillProperty('reservations', $propertyId, $report, $dryRun, fn (object $row): ?int => $this->singlePropertyFrom([
                ['table' => 'rooms', 'id' => $row->room_id ?? null],
            ]));
            $this->backfillProperty('room_blocks', $propertyId, $report, $dryRun, fn (object $row): ?int => $this->singlePropertyFrom([
                ['table' => 'rooms', 'id' => $row->room_id ?? null],
            ]));
            $this->backfillProperty('payments', $propertyId, $report, $dryRun, fn (object $row): ?int => $this->singlePropertyFrom([
                ['table' => 'reservations', 'id' => $row->reservation_id ?? null],
            ]));
            $this->backfillProperty('invoices', $propertyId, $report, $dryRun, fn (object $row): ?int => $this->singlePropertyFrom([
                ['table' => 'payments', 'id' => $row->payment_id ?? null],
            ]));
            $this->backfillProperty('housekeeping_tasks', $propertyId, $report, $dryRun, fn (object $row): ?int => $this->singlePropertyFrom([
                ['table' => 'rooms', 'id' => $row->room_id ?? null],
            ]));
            $this->backfillProperty('maintenance_tasks', $propertyId, $report, $dryRun, fn (object $row): ?int => $this->singlePropertyFrom([
                ['table' => 'rooms', 'id' => $row->room_id ?? null],
            ]));

            $this->backfillProperty('financial_accounts', $propertyId, $report, $dryRun);
            $this->backfillProperty('expense_categories', $propertyId, $report, $dryRun);
            $this->backfillProperty('financial_transactions', $propertyId, $report, $dryRun, fn (object $row): ?int => $this->singlePropertyFrom([
                ['table' => 'financial_accounts', 'id' => $row->account_id ?? null],
            ]));
            $this->backfillProperty('expenses', $propertyId, $report, $dryRun, fn (object $row): ?int => $this->singlePropertyFrom([
                ['table' => 'financial_accounts', 'id' => $row->account_id ?? null],
            ]));
            $this->backfillProperty('fund_transfers', $propertyId, $report, $dryRun, function (object $row) use (&$report): ?int {
                return $this->singlePropertyFrom([
                    ['table' => 'financial_accounts', 'id' => $row->from_account_id ?? null],
                    ['table' => 'financial_accounts', 'id' => $row->to_account_id ?? null],
                ], $report, 'fund_transfers', $row->id);
            });
            $this->backfillProperty('finance_reconciliations', $propertyId, $report, $dryRun, fn (object $row): ?int => $this->singlePropertyFrom([
                ['table' => 'financial_accounts', 'id' => $row->account_id ?? null],
            ]));
            $this->backfillProperty('daily_cash_closes', $propertyId, $report, $dryRun, fn (object $row): ?int => $this->singlePropertyFrom([
                ['table' => 'financial_accounts', 'id' => $row->account_id ?? null],
            ]));
            $this->backfillProperty('payment_refunds', $propertyId, $report, $dryRun, function (object $row) use (&$report): ?int {
                return $this->singlePropertyFrom([
                    ['table' => 'payments', 'id' => $row->payment_id ?? null],
                    ['table' => 'financial_accounts', 'id' => $row->account_id ?? null],
                ], $report, 'payment_refunds', $row->id);
            });

            $this->backfillProperty('pos_outlets', $propertyId, $report, $dryRun);
            $this->backfillProperty('pos_categories', $propertyId, $report, $dryRun);
            $this->backfillProperty('pos_products', $propertyId, $report, $dryRun, function (object $row) use (&$report): ?int {
                return $this->singlePropertyFrom([
                    ['table' => 'pos_categories', 'id' => $row->category_id ?? null],
                    ['table' => 'pos_outlets', 'id' => $row->outlet_id ?? null],
                ], $report, 'pos_products', $row->id);
            });
            $this->backfillProperty('pos_shifts', $propertyId, $report, $dryRun, fn (object $row): ?int => $this->singlePropertyFrom([
                ['table' => 'pos_outlets', 'id' => $row->outlet_id ?? null],
            ]));
            $this->backfillProperty('pos_orders', $propertyId, $report, $dryRun, function (object $row) use (&$report): ?int {
                return $this->singlePropertyFrom([
                    ['table' => 'pos_outlets', 'id' => $row->outlet_id ?? null],
                    ['table' => 'pos_shifts', 'id' => $row->shift_id ?? null],
                    ['table' => 'reservations', 'id' => $row->reservation_id ?? null],
                    ['table' => 'rooms', 'id' => $row->room_id ?? null],
                ], $report, 'pos_orders', $row->id);
            });
            $this->backfillProperty('pos_room_charges', $propertyId, $report, $dryRun, function (object $row) use (&$report): ?int {
                return $this->singlePropertyFrom([
                    ['table' => 'pos_orders', 'id' => $row->order_id ?? null],
                    ['table' => 'reservations', 'id' => $row->reservation_id ?? null],
                    ['table' => 'rooms', 'id' => $row->room_id ?? null],
                ], $report, 'pos_room_charges', $row->id);
            });
            $this->backfillProperty('pos_audits', $propertyId, $report, $dryRun, function (object $row) use (&$report): ?int {
                return $this->singlePropertyFrom([
                    ['table' => 'pos_orders', 'id' => $row->order_id ?? null],
                    ['table' => 'pos_shifts', 'id' => $row->shift_id ?? null],
                    ['table' => 'pos_products', 'id' => $row->product_id ?? null],
                ], $report, 'pos_audits', $row->id);
            });

            $this->backfillProperty('tasks', $propertyId, $report, $dryRun, function (object $row) use (&$report): ?int {
                return $this->singlePropertyFrom([
                    ['table' => 'rooms', 'id' => $row->room_id ?? null],
                    ['table' => 'reservations', 'id' => $row->reservation_id ?? null],
                    ['table' => 'housekeeping_tasks', 'id' => $row->housekeeping_task_id ?? null],
                    ['table' => 'maintenance_tasks', 'id' => $row->maintenance_task_id ?? null],
                ], $report, 'tasks', $row->id);
            });
            $this->backfillProperty('task_tags', $propertyId, $report, $dryRun);

            $this->backfillOrganization('announcements', $organizationId, $report, $dryRun);
            $this->backfillNotifications($organizationId, $report, $dryRun);
            $this->backfillProperty('channel_connections', $propertyId, $report, $dryRun);
            $this->backfillProperty('external_reservations', $propertyId, $report, $dryRun, function (object $row) use (&$report): ?int {
                return $this->singlePropertyFrom([
                    ['table' => 'channel_connections', 'id' => $row->channel_connection_id ?? null],
                    ['table' => 'reservations', 'id' => $row->reservation_id ?? null],
                ], $report, 'external_reservations', $row->id);
            });
            $this->backfillProperty('email_delivery_logs', $propertyId, $report, $dryRun, fn (object $row): ?int => $this->singlePropertyFrom([
                ['table' => 'reservations', 'id' => $row->reservation_id ?? null],
            ]), false);
            $this->backfillProperty('gateway_transactions', $propertyId, $report, $dryRun, fn (object $row): ?int => $this->singlePropertyFrom([
                ['table' => 'reservations', 'id' => $row->reservation_id ?? null],
                ['table' => 'payments', 'id' => $row->payment_id ?? null],
            ]), false);
        };

        if ($dryRun) {
            $work();
        } else {
            DB::transaction($work);
        }

        $report['financial_totals_after'] = $this->financialTotals();
        $report['valid'] = $report['anomalies'] === [];

        return $report;
    }

    /** @return array<string, string|int|float|null> */
    public function financialTotals(): array
    {
        $sum = static function (string $table, string $column): string {
            if (! DB::getSchemaBuilder()->hasTable($table)) {
                return '0.00';
            }

            return number_format((float) DB::table($table)->sum($column), 2, '.', '');
        };

        return [
            'reservation_revenue' => $sum('reservations', 'total_amount'),
            'payment_total' => $sum('payments', 'amount'),
            'financial_credits' => number_format((float) DB::table('financial_transactions')->where('direction', 'credit')->sum('amount'), 2, '.', ''),
            'financial_debits' => number_format((float) DB::table('financial_transactions')->where('direction', 'debit')->sum('amount'), 2, '.', ''),
            'expense_total' => $sum('expenses', 'amount'),
            'transfer_total' => $sum('fund_transfers', 'amount'),
            'pos_sales' => $sum('pos_orders', 'total'),
            'pos_room_charges' => $sum('pos_room_charges', 'amount'),
        ];
    }

    /** @param array<string, mixed> $report */
    private function backfillOrganization(string $table, int $organizationId, array &$report, bool $dryRun): void
    {
        $this->backfillRows($table, 'organization_id', $report, $dryRun, fn (object $row): ?int => $organizationId);
    }

    /** @param array<string, mixed> $report */
    private function backfillProperty(string $table, int $defaultPropertyId, array &$report, bool $dryRun, ?callable $resolver = null, bool $required = true): void
    {
        $this->backfillRows($table, 'property_id', $report, $dryRun, function (object $row) use ($defaultPropertyId, $resolver, &$report, $table, $required): ?int {
            $anomaliesBefore = count($report['anomalies']);
            $resolved = $resolver ? $resolver($row) : null;

            // A relationship conflict must remain unresolved for review. Do
            // not hide it by assigning the default property.
            if (count($report['anomalies']) > $anomaliesBefore) {
                return null;
            }

            return $resolved ?? ($required ? $defaultPropertyId : null);
        });
    }

    /** @param array<string, mixed> $report */
    private function backfillNotifications(int $organizationId, array &$report, bool $dryRun): void
    {
        if (! DB::getSchemaBuilder()->hasTable('notifications')) {
            return;
        }

        $this->backfillRows('notifications', 'organization_id', $report, $dryRun, function (object $row) use ($organizationId): ?int {
            $userId = $row->notifiable_type === 'App\\Models\\User' ? $row->notifiable_id : null;

            return $userId && DB::table('organization_memberships')->where('user_id', $userId)->where('status', 'active')->value('organization_id')
                ?: $organizationId;
        });
    }

    /** @param array<string, mixed> $report */
    private function backfillRows(string $table, string $column, array &$report, bool $dryRun, callable $resolver): void
    {
        if (! DB::getSchemaBuilder()->hasTable($table)) {
            return;
        }

        $query = DB::table($table)->orderBy('id');
        $entry = [
            'before_count' => (clone $query)->count(),
            'backfilled' => 0,
            'existing_owned' => 0,
            'null_ownership' => 0,
            'orphan_ownership' => 0,
        ];

        foreach ($query->get() as $row) {
            $current = $row->{$column} ?? null;
            if ($current !== null) {
                if (! DB::table($column === 'organization_id' ? 'organizations' : 'properties')->where('id', $current)->exists()) {
                    $entry['orphan_ownership']++;
                    $report['anomalies'][] = ['table' => $table, 'record_id' => $row->id, 'reason' => $column.' references a missing owner.'];
                } else {
                    $entry['existing_owned']++;
                }
                continue;
            }

            $ownerId = $resolver($row);
            if ($ownerId === null) {
                $entry['null_ownership']++;
                continue;
            }

            if (! $dryRun) {
                DB::table($table)->where('id', $row->id)->update([$column => $ownerId, 'updated_at' => now()]);
            }
            $entry['backfilled']++;
        }

        $entry['after_count'] = $entry['before_count'];
        $report['tables'][$table.'::'.$column] = $entry;
    }

    /**
     * Resolve a set of related records to one property. A mismatch is an
     * anomaly, never an instruction to rewrite the relationship.
     *
     * @param array<int, array{table:string,id:mixed}> $references
     * @param array<string, mixed>|null $report
     */
    private function singlePropertyFrom(array $references, ?array &$report = null, ?string $table = null, ?int $recordId = null): ?int
    {
        $propertyIds = [];
        foreach ($references as $reference) {
            if (! $reference['id']) {
                continue;
            }
            $propertyId = DB::table($reference['table'])->where('id', $reference['id'])->value('property_id');
            if ($propertyId !== null) {
                $propertyIds[] = (int) $propertyId;
            }
        }

        $propertyIds = array_values(array_unique($propertyIds));
        if (count($propertyIds) > 1) {
            $report['anomalies'][] = [
                'table' => $table,
                'record_id' => $recordId,
                'reason' => 'Related records disagree on property ownership.',
                'property_ids' => $propertyIds,
            ];

            return null;
        }

        return $propertyIds[0] ?? null;
    }
}
