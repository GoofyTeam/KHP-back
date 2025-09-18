<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Models\Company;
use App\Models\Order;
use App\Models\Room;
use App\Models\Table;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companies = Company::query()->with('users')->get();

        foreach ($companies as $company) {
            if ($company->users->isEmpty()) {
                continue;
            }

            $rooms = $this->ensureRoomsForCompany($company);
            $tables = $this->createTablesForCompany($company, $rooms);

            foreach ($this->orderStatuses() as $status) {
                $user = $company->users->random();
                $table = $tables->random();

                Order::query()->create(array_merge([
                    'table_id' => $table->id,
                    'company_id' => $company->id,
                    'user_id' => $user->id,
                    'status' => $status,
                ], $this->buildTimeline($status)));
            }
        }
    }

    /**
     * @return Collection<int, Room>
     */
    private function ensureRoomsForCompany(Company $company): Collection
    {
        $existing = Room::query()->where('company_id', $company->id)->get();

        if ($existing->isNotEmpty()) {
            return $existing;
        }

        return Room::factory()
            ->count(2)
            ->for($company)
            ->create();
    }

    /**
     * @param  Collection<int, Room>  $rooms
     * @return Collection<int, Table>
     */
    private function createTablesForCompany(Company $company, Collection $rooms): Collection
    {
        $tables = collect();

        foreach (range(1, 4) as $index) {
            $tables->push(
                Table::factory()
                    ->for($rooms->random(), 'room')
                    ->for($company)
                    ->state([
                        'label' => sprintf('T%02d-%s', $index, $company->id),
                        'seats' => random_int(2, 6),
                    ])
                    ->create()
            );
        }

        return $tables;
    }

    /**
     * @return array<int, OrderStatus>
     */
    private function orderStatuses(): array
    {
        return [
            OrderStatus::PENDING,
            OrderStatus::SERVED,
            OrderStatus::PAYED,
            OrderStatus::CANCELED,
        ];
    }

    /**
     * @return array<string, Carbon|null>
     */
    private function buildTimeline(OrderStatus $status): array
    {
        $pendingAt = Carbon::now()->subMinutes(random_int(30, 720));

        $timeline = [
            'pending_at' => $pendingAt,
            'served_at' => null,
            'payed_at' => null,
            'canceled_at' => null,
        ];

        if ($status === OrderStatus::CANCELED) {
            $timeline['canceled_at'] = $pendingAt->copy()->addMinutes(random_int(5, 45));

            return $timeline;
        }

        if (in_array($status, [OrderStatus::SERVED, OrderStatus::PAYED], true)) {
            $timeline['served_at'] = $pendingAt->copy()->addMinutes(random_int(10, 60));
        }

        if ($status === OrderStatus::PAYED) {
            $reference = $timeline['served_at'] ?? $pendingAt;
            $timeline['payed_at'] = $reference->copy()->addMinutes(random_int(5, 30));
        }

        return $timeline;
    }
}
