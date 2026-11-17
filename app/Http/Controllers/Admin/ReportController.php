<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Queries\SpendReport;
use App\Services\Pixelforge\PixelforgeClient;
use App\Services\Pixelforge\PixelforgeException;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    /** Pixelforge's usage endpoint accepts at most 92 days. */
    private const MAX_DAYS = 92;

    public function __invoke(Request $request, PixelforgeClient $pixelforge): Response
    {
        $validated = $request->validate([
            'start' => ['nullable', 'date_format:Y-m-d'],
            'end' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start'],
        ]);

        $end = Carbon::parse($validated['end'] ?? now()->toDateString(), 'UTC');
        $start = Carbon::parse($validated['start'] ?? $end->copy()->subDays(29)->toDateString(), 'UTC');
        $start = $start->max($end->copy()->subDays(self::MAX_DAYS - 1));

        $report = new SpendReport($start, $end);
        $usage = $this->pixelforgeUsage($pixelforge, $start, $end);

        return Inertia::render('admin/Report', [
            'range' => ['start' => $start->toDateString(), 'end' => $end->toDateString()],
            'days' => $report->byDay()->map(fn (array $day) => [
                ...$day,
                // What Pixelforge says it charged us that day, for reconciliation.
                'pixelforge_cost' => $usage['days'][$day['day']] ?? ($usage['available'] ? 0 : null),
            ]),
            'users' => $report->byUser(),
            'breakdown' => $report->byDayAndUser(),
            'pixelforge' => [
                'available' => $usage['available'],
                'total_cost' => $usage['total'],
            ],
        ]);
    }

    /** @return array{available: bool, days: array<string, int>, total: int|null} */
    private function pixelforgeUsage(PixelforgeClient $pixelforge, Carbon $start, Carbon $end): array
    {
        try {
            $usage = Cache::remember(
                "pixelforge.usage.{$start->toDateString()}.{$end->toDateString()}",
                60,
                fn () => $pixelforge->usage($start->toDateString(), $end->toDateString()),
            );
        } catch (PixelforgeException) {
            return ['available' => false, 'days' => [], 'total' => null];
        }

        return [
            'available' => true,
            'days' => collect($usage['data'])
                ->mapWithKeys(fn (array $day) => [$day['date'] => Money::fromDecimal($day['cost_usd'])])
                ->all(),
            'total' => Money::fromDecimal($usage['total']['cost_usd']),
        ];
    }
}
