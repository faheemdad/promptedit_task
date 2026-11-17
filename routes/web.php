<?php

use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\GenerationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::middleware('auth')->group(function () {
    Route::redirect('dashboard', 'generations')->name('dashboard');

    Route::get('generations', [GenerationController::class, 'index'])->name('generations.index');
    Route::post('generations', [GenerationController::class, 'store'])->name('generations.store')->middleware('throttle:20,1');
    Route::post('generations/estimate', [GenerationController::class, 'estimate'])->name('generations.estimate');

    Route::get('credits', [CreditController::class, 'index'])->name('credits.index');
    Route::post('credits', [CreditController::class, 'store'])->name('credits.store');

    Route::get('admin/report', ReportController::class)->name('admin.report')->middleware('can:view-admin');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
