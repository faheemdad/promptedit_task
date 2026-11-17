<?php

namespace App\Http\Controllers;

use App\Actions\Generations\CreateGeneration;
use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Http\Requests\GenerationRequest;
use App\Http\Resources\GenerationResource;
use App\Services\Billing\InsufficientCreditsException;
use App\Services\Pricing\PricingCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GenerationController extends Controller
{
    public function index(Request $request): Response
    {
        $generations = $request->user()->generations()->latest()->paginate(12);

        return Inertia::render('generations/Index', [
            'generations' => GenerationResource::collection($generations),
            'sizes' => array_map(fn (ImageSize $s) => ['value' => $s->value, 'label' => $s->label()], ImageSize::cases()),
            'qualities' => array_map(fn (ImageQuality $q) => ['value' => $q->value, 'label' => $q->label()], ImageQuality::cases()),
        ]);
    }

    public function estimate(GenerationRequest $request, PricingCalculator $pricing): JsonResponse
    {
        $estimate = $pricing->estimate($request->prompt(), $request->size(), $request->quality(), count($request->referenceImages()));

        return response()->json([
            ...$estimate->toArray(),
            'affordable' => $request->user()->credit_balance >= $estimate->userMax,
        ]);
    }

    public function store(GenerationRequest $request, CreateGeneration $createGeneration): RedirectResponse
    {
        try {
            $createGeneration->handle(
                $request->user(),
                $request->prompt(),
                $request->size(),
                $request->quality(),
                $request->referenceImages(),
            );
        } catch (InsufficientCreditsException) {
            return back()->withErrors(['credits' => 'You don\'t have enough credits for this generation. Buy credits to continue.']);
        }

        return to_route('generations.index')->with('success', 'Generation queued.');
    }
}
