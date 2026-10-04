<?php

namespace App\Http\Controllers;

use App\Exceptions\AiUnavailableException;
use App\Models\Group;
use App\Services\ExpenseAiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * AI helpers for the "Add Expense" form. Neither action saves anything: they pre-fill the
 * form (via flashed old input) so the user can check the result before submitting it.
 */
class AiExpenseController extends Controller
{
    public function scanReceipt(Request $request, Group $group, ExpenseAiService $ai): RedirectResponse
    {
        $this->authorize('addExpense', $group);

        $request->validate([
            'receipt' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp,pdf', 'max:5120'],
        ]);

        try {
            $fields = $ai->scanReceipt($request->file('receipt'));
        } catch (AiUnavailableException $e) {
            return back()->withErrors(['receipt' => $e->getMessage()]);
        }

        return redirect()->route('expenses.create', $group)
            ->withInput($fields)
            ->with('ai_status', 'Filled in from your receipt. Check the details, choose who shares it, then save.');
    }

    public function parseText(Request $request, Group $group, ExpenseAiService $ai): RedirectResponse
    {
        $this->authorize('addExpense', $group);

        $data = $request->validate([
            'text' => ['required', 'string', 'max:500'],
        ]);

        try {
            $fields = $ai->parseText($data['text'], $group->members, $request->user()->id);
        } catch (AiUnavailableException $e) {
            return back()->withInput()->withErrors(['text' => $e->getMessage()]);
        }

        return redirect()->route('expenses.create', $group)
            ->withInput(array_filter($fields, fn ($v) => $v !== null) + ['text' => $data['text']])
            ->with('ai_status', 'Filled in from your description. Check the details, then save.');
    }
}
