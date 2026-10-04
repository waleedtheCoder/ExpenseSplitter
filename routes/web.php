<?php

use App\Http\Controllers\AiExpenseController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\InsightsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettlementController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return redirect()->route('groups.index');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('groups', GroupController::class)->except(['edit', 'update']);
    Route::post('/groups/{group}/members', [GroupController::class, 'addMember'])->name('groups.members.add');

    Route::get('/groups/{group}/expenses/create', [ExpenseController::class, 'create'])->name('expenses.create');
    Route::post('/groups/{group}/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
    Route::delete('/groups/{group}/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');

    Route::post('/groups/{group}/expenses/ai/receipt', [AiExpenseController::class, 'scanReceipt'])
        ->middleware('throttle:20,1')->name('expenses.ai.receipt');
    Route::post('/groups/{group}/expenses/ai/text', [AiExpenseController::class, 'parseText'])
        ->middleware('throttle:20,1')->name('expenses.ai.text');

    Route::get('/groups/{group}/insights', [InsightsController::class, 'show'])->name('groups.insights');
    Route::post('/groups/{group}/insights', [InsightsController::class, 'generate'])
        ->middleware('throttle:10,1')->name('groups.insights.generate');

    Route::post('/groups/{group}/settlements', [SettlementController::class, 'store'])->name('settlements.store');
});

require __DIR__.'/auth.php';
