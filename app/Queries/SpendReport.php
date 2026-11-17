<?php

namespace App\Queries;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Credit spend, provider cost and profit for succeeded generations, grouped
 * by the UTC day they completed. Pixelforge bills on that same day, so our
 * daily provider cost lines up with GET /v1/usage. All amounts are nano-USD.
 */
class SpendReport
{
    public function __construct(
        private readonly Carbon $start,
        private readonly Carbon $end,
    ) {}

    /** @return Collection<int, array<string, mixed>> */
    public function byDay(): Collection
    {
        return $this->aggregate(
            $this->base()
                ->selectRaw('DATE(completed_at) as day')
                ->groupByRaw('DATE(completed_at)')
                ->orderByDesc('day'),
        );
    }

    /** @return Collection<int, array<string, mixed>> */
    public function byUser(): Collection
    {
        return $this->aggregate(
            $this->base()
                ->join('users', 'users.id', '=', 'generations.user_id')
                ->selectRaw('users.id as user_id, users.name as user_name, users.email as user_email')
                ->groupBy('users.id', 'users.name', 'users.email')
                ->orderByRaw('SUM(charged_cost) DESC'),
        );
    }

    /** @return Collection<int, array<string, mixed>> */
    public function byDayAndUser(): Collection
    {
        return $this->aggregate(
            $this->base()
                ->join('users', 'users.id', '=', 'generations.user_id')
                ->selectRaw('DATE(completed_at) as day, users.id as user_id, users.name as user_name')
                ->groupByRaw('DATE(completed_at), users.id, users.name')
                ->orderByDesc('day')
                ->orderBy('users.name'),
        );
    }

    /** @return Builder<Generation> */
    private function base(): Builder
    {
        return Generation::query()
            ->where('status', GenerationStatus::Succeeded)
            ->whereBetween('completed_at', [$this->start->copy()->startOfDay(), $this->end->copy()->endOfDay()]);
    }

    /**
     * @param  Builder<Generation>  $query
     * @return Collection<int, array<string, mixed>>
     */
    private function aggregate(Builder $query): Collection
    {
        return $query
            ->selectRaw('COUNT(*) as generations, SUM(charged_cost) as charged, SUM(provider_cost) as provider_cost')
            ->toBase()
            ->get()
            ->map(fn (object $row) => [
                ...(array) $row,
                'generations' => (int) $row->generations,
                'charged' => (int) $row->charged,
                'provider_cost' => (int) $row->provider_cost,
                'profit' => (int) $row->charged - (int) $row->provider_cost,
            ]);
    }
}
