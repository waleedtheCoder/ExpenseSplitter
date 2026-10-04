<?php

namespace App\Http\Controllers;

use App\Exceptions\AiUnavailableException;
use App\Models\Group;
use App\Services\ExpenseAiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class InsightsController extends Controller
{
    public function show(Request $request, Group $group, ExpenseAiService $ai): View
    {
        $this->authorize('view', $group);

        $month = $this->month($request);
        $stats = $this->stats($group, $month);
        $summary = Cache::get($this->cacheKey($group, $stats));

        return view('groups.insights', [
            'group' => $group,
            'month' => $month,
            'stats' => $stats,
            'summary' => $summary,
            'aiEnabled' => $ai->enabled(),
        ]);
    }

    /** Ask Claude for a summary of the month. Cached until the month's expenses change. */
    public function generate(Request $request, Group $group, ExpenseAiService $ai): RedirectResponse
    {
        $this->authorize('view', $group);

        $month = $this->month($request);
        $stats = $this->stats($group, $month);
        $back = redirect()->route('groups.insights', [$group, 'month' => $month->format('Y-m')]);

        if ($stats['expense_count'] === 0) {
            return $back;
        }

        try {
            $summary = $ai->insights($group->name, $stats);
        } catch (AiUnavailableException $e) {
            return $back->withErrors(['summary' => $e->getMessage()]);
        }

        Cache::forever($this->cacheKey($group, $stats), $summary);

        return $back;
    }

    private function month(Request $request): Carbon
    {
        $value = $request->input('month');

        if (is_string($value) && preg_match('/^\d{4}-\d{2}$/', $value)) {
            return Carbon::createFromFormat('Y-m-d', $value.'-01')->startOfMonth();
        }

        return now()->startOfMonth();
    }

    /** @return array<string, mixed> */
    private function stats(Group $group, Carbon $month): array
    {
        $previous = $month->copy()->subMonth();

        $current = $group->expenses()->with('payer')
            ->whereBetween('created_at', [$month, $month->copy()->endOfMonth()])
            ->get();

        $prior = $group->expenses()
            ->whereBetween('created_at', [$previous, $previous->copy()->endOfMonth()])
            ->get();

        $byCategory = fn ($expenses) => $expenses
            ->groupBy(fn ($e) => $e->category ?? 'Uncategorised')
            ->map(fn ($items) => round($items->sum('amount'), 2))
            ->sortDesc()
            ->all();

        $largest = $current->sortByDesc('amount')->first();

        return [
            'month' => $month->format('F Y'),
            'expense_count' => $current->count(),
            'total' => round($current->sum('amount'), 2),
            'previous_month_total' => round($prior->sum('amount'), 2),
            'by_category' => $byCategory($current),
            'previous_month_by_category' => $byCategory($prior),
            'paid_by_member' => $current
                ->groupBy(fn ($e) => $e->payer->name)
                ->map(fn ($items) => round($items->sum('amount'), 2))
                ->sortDesc()
                ->all(),
            'largest_expense' => $largest
                ? ['description' => $largest->description, 'amount' => (float) $largest->amount]
                : null,
        ];
    }

    private function cacheKey(Group $group, array $stats): string
    {
        return "insights:{$group->id}:".md5(json_encode($stats));
    }
}
