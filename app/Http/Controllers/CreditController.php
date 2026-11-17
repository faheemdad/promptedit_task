<?php

namespace App\Http\Controllers;

use App\Services\Billing\CreditLedger;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CreditController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('credits/Index', [
            'packs' => config('billing.credit_packs'),
            'transactions' => $request->user()->creditTransactions()
                ->latest('id')
                ->paginate(20)
                ->through(fn ($t) => [
                    'id' => $t->id,
                    'type' => $t->type,
                    'amount' => $t->amount,
                    'description' => $t->description,
                    'generation_id' => $t->generation_id,
                    'created_at' => $t->created_at,
                ]),
        ]);
    }

    /** Fake purchase: no payment, the credits are simply added. */
    public function store(Request $request, CreditLedger $ledger): RedirectResponse
    {
        $validated = $request->validate([
            'pack' => ['required', 'integer', Rule::in(config('billing.credit_packs'))],
        ]);

        $ledger->purchase($request->user(), Money::fromDollars($validated['pack']));

        return back()->with('success', "Added \${$validated['pack']}.00 in credits.");
    }
}
